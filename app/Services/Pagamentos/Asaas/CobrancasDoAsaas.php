<?php

namespace App\Services\Pagamentos\Asaas;

use App\Models\Fatura;
use App\Models\Tenant;
use App\Services\Assinaturas\AvisosDeAssinatura;
use App\Services\Assinaturas\Faturamento;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Faturas cobradas pelo Asaas. O sistema continua decidindo quanto e quando
 * cobrar (assentos, crédito de troca, renovação); o Asaas só recebe e avisa.
 * Por isso uma cobrança avulsa por fatura, e não as assinaturas de valor
 * fixo do Asaas.
 *
 * Confirmação: o aviso do webhook só diz qual cobrança olhar. A situação, o
 * valor e o cliente vêm de uma consulta à API, e só então pagar() ativa o
 * plano — o mesmo caminho do comando fatura:pagar.
 */
class CobrancasDoAsaas
{
    /** Situações que contam como paga. Cartão chega em CONFIRMED (o dinheiro cai em 32 dias); Pix e boleto em RECEIVED. */
    public const PAGA = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'];

    /** Canceladas há até N dias ainda são conferidas: a cobrança lá pode ter sido paga. */
    private const CANCELADAS_CONFERIDAS_DIAS = 10;

    private const ESTORNOS = [
        'PAYMENT_REFUNDED' => 'Estorno',
        'PAYMENT_PARTIALLY_REFUNDED' => 'Estorno parcial',
        'PAYMENT_CHARGEBACK_REQUESTED' => 'Contestação no cartão',
    ];

    public function __construct(
        private readonly ApiDoAsaas $api,
        private readonly Faturamento $faturamento,
        private readonly AvisosDeAssinatura $avisos,
    ) {}

    public function ativo(): bool
    {
        return $this->api->ativo();
    }

    /**
     * A cobrança da fatura no Asaas, criada na primeira vez que alguém vai
     * pagar. Quem paga escolhe Pix, boleto ou cartão na página do Asaas.
     *
     * @throws ValidationException empresa sem CPF/CNPJ
     * @throws DomainException fatura paga ou cancelada
     * @throws FalhaNoAsaas
     */
    public function garantir(Fatura $fatura, ?string $email = null): Fatura
    {
        if ($fatura->status !== 'pendente') {
            throw new DomainException("A fatura #{$fatura->id} não está aberta para pagamento.");
        }
        if ($fatura->cobranca_id !== null) {
            return $fatura;
        }
        // Fora da transação: um cliente criado lá não pode ser esquecido aqui se a cobrança falhar.
        $cliente = $this->cliente($fatura->tenant, $email);

        return DB::transaction(function () use ($fatura, $cliente) {
            $f = Fatura::whereKey($fatura->id)->lockForUpdate()->firstOrFail();
            if ($f->status !== 'pendente') {
                throw new DomainException("A fatura #{$f->id} não está aberta para pagamento.");
            }
            if ($f->cobranca_id !== null) {
                return $f; // outro clique chegou antes
            }

            $cobranca = $this->api->criarCobranca([
                'customer' => $cliente,
                'billingType' => 'UNDEFINED',
                'value' => (float) $f->valor,
                // Fatura atrasada ainda pode ser paga: o Asaas não aceita vencimento no passado.
                'dueDate' => ($f->vence_em->lt(Carbon::today()) ? Carbon::today() : $f->vence_em)->toDateString(),
                'description' => config('app.name').": plano {$f->planoNome()}, ciclo {$f->ciclo}, {$f->usuarios} ".($f->usuarios === 1 ? 'usuário' : 'usuários').". Fatura #{$f->id}.",
                'externalReference' => "fatura:{$f->id}",
            ]);
            $f->forceFill(['cobranca_id' => $cobranca['id'], 'link_pagamento' => $cobranca['invoiceUrl'] ?? null])->save();

            return $f;
        });
    }

    /**
     * QR Code e copia e cola da cobrança.
     *
     * @return array{imagem:string, copia_e_cola:string, expira_em:?string}
     */
    public function pix(Fatura $f): array
    {
        if ($f->cobranca_id === null) {
            throw new DomainException("A fatura #{$f->id} ainda não tem cobrança no Asaas.");
        }
        $qr = $this->api->qrCodePix($f->cobranca_id);

        return [
            'imagem' => 'data:image/png;base64,'.($qr['encodedImage'] ?? ''),
            'copia_e_cola' => (string) ($qr['payload'] ?? ''),
            'expira_em' => $qr['expirationDate'] ?? null,
        ];
    }

    /**
     * O que fazer com um aviso do webhook. Devolve o resultado, que fica
     * registrado no evento.
     *
     * @throws FalhaNoAsaas a API não respondeu ao conferir (o webhook pede reenvio)
     */
    public function processarEvento(array $evento): string
    {
        $tipo = (string) ($evento['event'] ?? '');
        $id = $evento['payment']['id'] ?? null;
        if (! is_string($id) || $id === '') {
            return 'ignorado: aviso sem cobrança';
        }

        // Pela cobrança; se a fatura perdeu o vínculo (cobrança removida e
        // restaurada no painel), pela referência que a cobrança carrega.
        $f = Fatura::where('cobranca_id', $id)->first();
        if ($f === null && preg_match('/^fatura:(\d+)$/', (string) ($evento['payment']['externalReference'] ?? ''), $m) === 1) {
            $f = Fatura::find((int) $m[1]);
        }
        if ($f === null) {
            return 'ignorado: cobrança que não é de uma fatura';
        }

        return match (true) {
            in_array($tipo, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true) => $this->confirmar($f, $id),
            isset(self::ESTORNOS[$tipo]) => $this->alertarEstorno($f, $id, self::ESTORNOS[$tipo]),
            $tipo === 'PAYMENT_DELETED' && $f->cobranca_id === $id => $this->desvincular($f),
            default => "ignorado: {$tipo}",
        };
    }

    /** Confere a cobrança atual da fatura (tela de pagamento e conciliação). */
    public function confirmarSePaga(Fatura $f): string
    {
        return $f->cobranca_id === null ? 'ignorado: fatura sem cobrança' : $this->confirmar($f, $f->cobranca_id);
    }

    /**
     * Faturas pendentes com cobrança no Asaas, e as canceladas há poucos
     * dias (a remoção lá pode ter falhado ou o boleto ainda compensava),
     * conferidas uma a uma. Cobre aviso perdido: servidor fora do ar, fila
     * pausada, ambiente sem endereço público. O erro de uma não para as outras.
     *
     * @return array{confirmadas:int, falhas:int}
     */
    public function conciliar(): array
    {
        $confirmadas = 0;
        $falhas = 0;
        Fatura::whereNotNull('cobranca_id')
            ->where(fn ($q) => $q->where('status', 'pendente')
                ->orWhere(fn ($q) => $q->where('status', 'cancelada')->where('cancelada_em', '>=', Carbon::now()->subDays(self::CANCELADAS_CONFERIDAS_DIAS))))
            ->lazyById(100)
            ->each(function (Fatura $f) use (&$confirmadas, &$falhas) {
                try {
                    $confirmadas += $this->confirmar($f, $f->cobranca_id) === 'paga' ? 1 : 0;
                } catch (FalhaNoAsaas $e) {
                    if ($f->status === 'cancelada' && $e->status === 404) {
                        return; // removida no Asaas, como devia
                    }
                    $falhas++;
                    Log::warning("Asaas: não deu para conferir a fatura #{$f->id}.", ['erro' => $e->getMessage()]);
                } catch (Throwable $e) {
                    $falhas++;
                    Log::error("Asaas: erro ao conferir a fatura #{$f->id}.", ['erro' => $e->getMessage()]);
                }
            });

        return ['confirmadas' => $confirmadas, 'falhas' => $falhas];
    }

    /**
     * Consulta a cobrança na API e, se estiver paga, for desta fatura
     * (externalReference) e cobrir o valor, confirma o pagamento. Não compara
     * o cliente: dois cliques simultâneos na primeira cobrança podiam deixar
     * a empresa com outro cliente do Asaas, e o pagamento continua sendo dela.
     *
     * @throws FalhaNoAsaas
     */
    private function confirmar(Fatura $f, string $cobrancaId): string
    {
        if ($f->status === 'paga' && $f->referencia_gateway === $cobrancaId) {
            return 'já paga'; // cartão: CONFIRMED e, 32 dias depois, RECEIVED
        }

        $c = $this->api->cobranca($cobrancaId);
        $situacao = (string) ($c['status'] ?? '');
        if (! in_array($situacao, self::PAGA, true)) {
            return "recusado: cobrança {$situacao} no Asaas";
        }

        $valor = round((float) ($c['value'] ?? 0), 2);
        if (($c['externalReference'] ?? null) !== "fatura:{$f->id}" || $valor < round((float) $f->valor, 2)) {
            $this->avisos->alertaAoComercial($f, 'Pagamento que não confere', "A cobrança {$cobrancaId} está paga no Asaas, mas não é desta fatura ou o valor (".Fatura::emReais($valor).') não cobre a fatura. O plano não foi ativado. Confira no painel do Asaas e, se estiver certo, confirme com php artisan fatura:pagar.', unicoPor: "{$cobrancaId}:confere");

            return 'recusado: cobrança ou valor não conferem';
        }

        if ($f->status === 'cancelada') {
            $this->avisos->alertaAoComercial($f, 'Fatura cancelada foi paga', "A cobrança {$cobrancaId} foi paga depois que a fatura foi cancelada (o cliente gerou outra ou cancelou a renovação). O plano não mudou. Estorne pelo Asaas ou ative o plano com php artisan assinatura:ativar.", unicoPor: "{$cobrancaId}:cancelada");

            return 'alerta: fatura cancelada foi paga';
        }

        if ($f->status === 'paga') {
            $this->avisos->alertaAoComercial($f, 'Fatura paga duas vezes', "A fatura já estava paga ({$f->gateway}: {$f->referencia_gateway}) e a cobrança {$cobrancaId} também foi paga no Asaas. Estorne uma delas pelo painel do Asaas.", unicoPor: "{$cobrancaId}:dobro");

            return 'alerta: fatura paga duas vezes';
        }

        $this->faturamento->pagar($f, $cobrancaId, 'asaas');

        return 'paga';
    }

    /** Estorno e contestação não mudam o plano sozinhos: a equipe comercial decide. */
    private function alertarEstorno(Fatura $f, string $cobrancaId, string $oQue): string
    {
        $this->avisos->alertaAoComercial($f, $oQue, "{$oQue} na cobrança {$cobrancaId} da fatura #{$f->id} (".Fatura::emReais($f->valor).'). O plano da empresa não mudou: revise a assinatura e decida se ela continua.');

        return 'alerta: '.mb_strtolower($oQue);
    }

    /** Cobrança removida no painel do Asaas: a próxima tentativa de pagar cria outra. */
    private function desvincular(Fatura $f): string
    {
        if ($f->status !== 'pendente') {
            return 'ignorado: cobrança removida de fatura fechada';
        }
        $f->forceFill(['cobranca_id' => null, 'link_pagamento' => null])->save();

        return 'cobrança removida no Asaas';
    }

    /**
     * Id do cliente no Asaas, cadastrado uma vez por empresa. A trava no
     * registro da empresa impede dois cliques simultâneos de criarem dois.
     */
    private function cliente(Tenant $tenant, ?string $email): string
    {
        if ($tenant->asaas_cliente_id !== null) {
            return $tenant->asaas_cliente_id;
        }

        return DB::transaction(fn () => $this->criarCliente(Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail(), $email));
    }

    private function criarCliente(Tenant $t, ?string $email): string
    {
        if ($t->asaas_cliente_id !== null) {
            return $t->asaas_cliente_id; // outro clique criou enquanto este esperava a trava
        }
        if (blank($t->documento)) {
            throw ValidationException::withMessages(['documento' => 'Informe o CPF ou CNPJ de quem paga a fatura.']);
        }

        $c = $this->api->criarCliente(array_filter([
            'name' => $t->nome,
            'cpfCnpj' => $t->documento,
            'email' => $email,
            'externalReference' => "tenant:{$t->id}",
            // Os avisos de cobrança saem do sistema. Os do Asaas duplicariam e custam por envio.
            'notificationDisabled' => true,
        ], fn ($v) => $v !== null));
        $t->forceFill(['asaas_cliente_id' => $c['id']])->save();

        return $c['id'];
    }
}
