<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Todos os e-mails de assinatura e cobrança, num lugar só. `tipo` escolhe o
 * texto; `dados` vem pronto de AvisosDeAssinatura (datas e valores já
 * formatados). Tom: o que aconteceu, o que muda para a equipe e o que fazer.
 */
class AvisoDeAssinaturaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  array<string, mixed>  $dados */
    public function __construct(public readonly string $tipo, public readonly array $dados) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->conteudo()['assunto']);
    }

    public function content(): Content
    {
        $c = $this->conteudo();

        return new Content(markdown: 'mail.assinatura', with: [
            'titulo' => $c['titulo'],
            'paragrafos' => $c['paragrafos'],
            'botao' => $c['botao'],
        ]);
    }

    /** @return array{assunto:string, titulo:string, paragrafos:list<string>, botao:?array{rotulo:string, url:string}} */
    public function conteudo(): array
    {
        $d = $this->dados;
        $empresa = $d['empresa'] ?? '';
        $plano = $d['plano'] ?? '';
        $f = $d['fatura'] ?? [];
        $dias = (int) ($d['dias'] ?? 0);
        $emDias = $dias <= 0 ? 'hoje' : ($dias === 1 ? 'amanhã' : "em {$dias} dias");
        $verPlanos = ['rotulo' => 'Ver planos e faturas', 'url' => $d['url'] ?? url('/')];
        $comoPagar = ! empty($d['instrucoes']) ? "Como pagar: {$d['instrucoes']}" : 'Responda este e-mail para receber os dados de pagamento.';
        $suspensao = ! empty($d['suspende_em']) ? "Em {$d['suspende_em']}, o acesso será suspenso: só a tela de planos e a exportação de leads continuam disponíveis." : null;

        $c = match ($this->tipo) {
            'teste_acabando' => [
                "Seu teste termina {$emDias}",
                "O teste da {$empresa} termina {$emDias}",
                ["O período de teste termina em {$d['teste_termina_em']}. Para a equipe continuar criando e editando depois disso, escolha um plano.", 'Os dados ficam guardados em qualquer caso.'],
                ['rotulo' => 'Escolher plano'] + $verPlanos,
            ],
            'fatura_gerada' => [
                "Fatura de {$f['valor']} do plano {$f['plano']}",
                "Fatura #{$f['id']} da {$empresa}",
                ["Plano {$f['plano']}, ciclo {$f['ciclo']}, {$f['usuarios']} ".($f['usuarios'] === 1 ? 'usuário' : 'usuários').": {$f['valor']}. Vence em {$f['vence_em']}.", $comoPagar, 'O plano é ativado ou renovado assim que o pagamento for confirmado.'],
                ['rotulo' => 'Ver fatura'] + $verPlanos,
            ],
            'fatura_vencendo' => [
                "A fatura de {$f['valor']} vence {$emDias}",
                "A fatura da {$empresa} vence {$emDias}",
                ["A fatura #{$f['id']} de {$f['valor']} vence em {$f['vence_em']} e ainda não foi paga.", $comoPagar, 'Sem o pagamento, a equipe trabalha normalmente por mais '.config('comercial.carencia_dias', 3).' dias e depois passa a só consultar.'],
                ['rotulo' => 'Ver fatura'] + $verPlanos,
            ],
            'plano_termina' => [
                "Seu plano {$plano} termina em {$d['vigente_ate']}",
                "O plano da {$empresa} termina em {$d['vigente_ate']}",
                ['A renovação foi cancelada. Depois dessa data, a equipe só consulta os dados.', 'Mudou de ideia? Retome a renovação na tela de assinatura.'],
                ['rotulo' => 'Retomar a renovação'] + $verPlanos,
            ],
            'teste_encerrado' => [
                'Seu teste terminou',
                "O teste da {$empresa} terminou",
                array_values(array_filter(['A equipe continua consultando os dados, mas não cria nem edita nada até um plano ser contratado.', $suspensao])),
                ['rotulo' => 'Escolher plano'] + $verPlanos,
            ],
            'pagamento_atrasado' => [
                "Pagamento pendente do plano {$plano}",
                "O plano da {$empresa} venceu",
                ["O plano {$plano} venceu em {$d['vigente_ate']} e a renovação ainda não foi paga.", "A equipe continua trabalhando normalmente até {$d['carencia_ate']}. Depois disso, só consulta.", $comoPagar],
                ['rotulo' => 'Ver fatura'] + $verPlanos,
            ],
            'acesso_limitado' => [
                "Acesso da {$empresa} limitado a consulta",
                'A equipe agora só consulta',
                array_values(array_filter(["Desde {$d['somente_leitura_desde']}, nada é criado nem editado. Regularize o plano para voltar a trabalhar.", $suspensao])),
                $verPlanos,
            ],
            'suspensao_proxima' => [
                "O acesso da {$empresa} será suspenso em {$d['suspende_em']}",
                "Acesso suspenso em {$d['suspende_em']}",
                ['Depois dessa data, só a tela de planos e a exportação de leads continuam disponíveis. Os dados continuam guardados.', 'Para evitar a suspensão, regularize o plano.'],
                $verPlanos,
            ],
            'suspensa' => [
                "Acesso da {$empresa} suspenso",
                'Acesso suspenso',
                ['O acesso foi suspenso por falta de pagamento. Os dados continuam guardados e voltam a aparecer assim que o pagamento for confirmado.', 'A exportação de leads continua disponível.'],
                $verPlanos,
            ],
            'pagamento_confirmado' => [
                "Pagamento confirmado: plano {$f['plano']} até {$d['vigente_ate']}",
                'Pagamento confirmado',
                ["Recebemos o pagamento da fatura #{$f['id']} ({$f['valor']}). O plano {$f['plano']} da {$empresa} está ativo até {$d['vigente_ate']}."],
                $verPlanos,
            ],
            'comercial_fatura_gerada' => [
                "Nova fatura #{$f['id']}: {$empresa}, {$f['valor']}",
                "Fatura #{$f['id']} para cobrar",
                ["{$empresa}: plano {$f['plano']} ({$f['ciclo']}), {$f['usuarios']} usuários, {$f['valor']}, vence em {$f['vence_em']}. Motivo: {$f['motivo']}.", "Quando o pagamento chegar, confirme no servidor: php artisan fatura:pagar {$f['id']} --referencia=\"...\""],
                null,
            ],
        };

        return ['assunto' => $c[0], 'titulo' => $c[1], 'paragrafos' => $c[2], 'botao' => $c[3]];
    }
}
