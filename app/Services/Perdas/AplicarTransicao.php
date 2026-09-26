<?php

namespace App\Services\Perdas;

use App\Models\MotivoPerda;
use App\Models\Perda;
use App\Support\Perdas\Perdivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ponto único de entrada em perda.
 *
 * Existem seis caminhos que podem deixar um lead ou projeto perdido: arrastar
 * o card no Kanban, editar o lead no perfil, mover de funil, cadastrar um lead
 * já perdido, editar um projeto e criar um projeto já perdido. A regra "perder
 * exige motivo" mora aqui, uma vez, e os seis chamam este serviço.
 *
 * A alternativa — declarar a regra em cada FormRequest — a espalharia por
 * quatro arquivos, e um sétimo caminho adicionado depois passaria sem ela sem
 * nada quebrar. É assim que `payload.tag_id = 1` sobreviveu tanto tempo no
 * Dashboard. PerdaEmTodosOsCaminhosTest existe para quebrar nesse caso.
 *
 * Por que a checagem acontece ANTES do save: um observer só roda depois, quando
 * a transição já aconteceu e não há mais o que recusar. Daí `estadoSeriaPerda()`
 * receber os atributos ainda não aplicados.
 *
 * ── Por que ValidationException e não uma exceção própria ──
 *
 * Quatro dos seis controllers envolvem a escrita num `catch (\Exception)` que
 * traduz qualquer falha em 404 "não encontrado". Uma exceção própria seria
 * engolida por ele e o usuário receberia "Lead não encontrado" ao esquecer o
 * motivo — mensagem errada, e um 404 que o frontend não sabe tratar.
 *
 * ValidationException já é tratada explicitamente e ANTES do catch amplo nos
 * seis, e cai no mesmo formato `{erros: {campo: [...]}}` que o frontend lê para
 * destacar campo. Consertar isso com mais um catch em cada controller seria
 * repetir seis vezes o que o canal existente já faz.
 */
class AplicarTransicao
{
    /**
     * @param  Perdivel&Model  $entidade   novo ou existente
     * @param  array<string, mixed>  $atributos  o que será gravado
     * @param  array{motivo_perda_id?: int|null, observacao?: string|null}|null  $perda
     *
     * @throws ValidationException  quando a transição é perda e falta motivo
     */
    public function __invoke(Perdivel&Model $entidade, array $atributos, ?array $perda = null): void
    {
        // `exists` importa: numa entidade ainda não salva, as relações já
        // resolvem a partir dos ids do payload, e `estadoAtualEhPerda()`
        // responderia "já estava perdido" para algo que está nascendo perdido —
        // deixando passar exatamente o caso que precisa de motivo.
        $jaEstavaPerdido = $entidade->exists && $entidade->estadoAtualEhPerda();
        $entraEmPerda = ! $jaEstavaPerdido && $entidade->estadoSeriaPerda($atributos);

        $motivo = $entraEmPerda ? $this->resolverMotivo($perda['motivo_perda_id'] ?? null) : null;

        DB::transaction(function () use ($entidade, $atributos, $perda, $motivo) {
            $entidade->fill($atributos)->save();

            if ($motivo === null) {
                return;
            }

            // O valor é lido depois do save: se a mesma requisição mudou o
            // preço do projeto junto com o status, o que se perdeu é o preço
            // novo, não o anterior.
            $linha = new Perda();
            $linha->motivo_perda_id = $motivo->id;
            $linha->observacao = $perda['observacao'] ?? null;
            $linha->valor = $entidade->valorDaPerda();
            $linha->user_id = auth()->id();
            // Explícito, apesar do DEFAULT CURRENT_TIMESTAMP da coluna: com
            // `$timestamps = false`, o Eloquent não preenche e quem carimba é o
            // banco. O relatório inteiro é recortado por período, então a data
            // não pode depender do relógio do servidor de banco — nem ficar
            // fora do alcance de Carbon::setTestNow() nos testes.
            $linha->created_at = now();
            // Herdado da entidade, não do tenant ativo: a perda não pode
            // pertencer a uma empresa diferente da linha que ela descreve, e
            // isso mantém o serviço utilizável fora do ciclo HTTP.
            $linha->tenant_id = $entidade->tenant_id;

            $entidade->perdas()->save($linha);
        });
    }

    /**
     * Repete a validação que o FormRequest já faz, de propósito: o serviço é
     * chamável de um job ou de um comando, e a invariante não pode depender de
     * quem chamou. O TenantScope faz um motivo de outro tenant não resolver, e
     * o SoftDeletes faz um motivo arquivado não resolver — os dois viram a
     * mesma recusa, porque para quem chama são o mesmo erro: esse motivo não
     * está disponível.
     */
    private function resolverMotivo(?int $motivoId): MotivoPerda
    {
        if ($motivoId === null) {
            throw self::recusar('Informe o motivo da perda.');
        }

        $motivo = MotivoPerda::whereKey($motivoId)->first();

        if ($motivo === null) {
            throw self::recusar('Motivo de perda inválido ou arquivado.');
        }

        return $motivo;
    }

    /**
     * A chave é `motivo_perda_id`, plana e não `perda.motivo_perda_id`, porque
     * é ela que o modal usa para destacar o seletor de motivo.
     */
    private static function recusar(string $mensagem): ValidationException
    {
        return ValidationException::withMessages(['motivo_perda_id' => [$mensagem]]);
    }
}
