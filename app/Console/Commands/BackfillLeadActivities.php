<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Anotacao;
use App\Models\arquivo;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Models\UsuarioTagHistorico;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gera os eventos de activity log das linhas que já existiam antes dos
 * observers entrarem.
 *
 * Duas propriedades que o comando precisa ter, e que os testes fixam:
 *
 * 1. Idempotente. A identidade de um evento é a tripla (subject_type,
 *    subject_id, event). O comando carrega as triplas existentes e insere só
 *    as ausentes, então repetir a execução não duplica nada. É por isso que o
 *    subject é sempre a linha de origem, nunca o lead: com o lead como
 *    subject, três trocas de tag colidiriam numa tripla só.
 *
 * 2. Retroativo. O created_at de cada evento é o da linha que o originou. Sem
 *    isso todos os eventos históricos colapsariam no instante da execução, e a
 *    ordenação da timeline — a razão de ser da feature — iria junto.
 *
 * Linhas soft-deleted geram dois eventos: a criação, em created_at, e a
 * remoção, em deleted_at.
 */
class BackfillLeadActivities extends Command
{
    protected $signature = 'activities:backfill-leads
                            {--tenant= : Processa apenas este tenant, em vez de todos}';

    protected $description = 'Gera os eventos retroativos do activity log a partir das tabelas de origem';

    public function handle(CurrentTenant $tenantAtivo): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::whereKey($this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($tenants as $tenant) {
            // As tabelas de origem são tenant-scoped e o TenantScope é
            // fail-closed: sem isto, a primeira leitura lança.
            $tenantAtivo->set($tenant);

            $criados = $this->processarTenant($tenant);
            $total += $criados;

            $this->line(sprintf('tenant %d (%s): %d evento(s) criado(s)', $tenant->id, $tenant->slug, $criados));
        }

        $tenantAtivo->clear();

        $this->info(sprintf('Concluído: %d evento(s) no total.', $total));

        return self::SUCCESS;
    }

    private function processarTenant(Tenant $tenant): int
    {
        $eventos = $this->coletarEventos($tenant);

        if ($eventos === []) {
            return 0;
        }

        $novos = array_values(array_filter(
            $eventos,
            fn (array $evento) => ! $this->jaRegistrado($evento),
        ));

        foreach (array_chunk($novos, 500) as $lote) {
            DB::table('activity_log')->insert($lote);
        }

        return count($novos);
    }

    /**
     * Triplas (subject_type, subject_id, event) já presentes no log do tenant.
     * Carregadas uma vez, em memória, para não fazer uma consulta por evento.
     */
    private array $existentes;

    private function jaRegistrado(array $evento): bool
    {
        $chave = $evento['subject_type'].'|'.$evento['subject_id'].'|'.$evento['event'];

        if (isset($this->existentes[$chave])) {
            return true;
        }

        // Marca para que duplicatas dentro do próprio lote também sejam
        // descartadas, não só as que já estavam no banco.
        $this->existentes[$chave] = true;

        return false;
    }

    private function carregarExistentes(): void
    {
        $this->existentes = Activity::query()
            ->whereNotNull('subject_id')
            ->get(['subject_type', 'subject_id', 'event'])
            ->mapWithKeys(fn ($a) => [$a->subject_type.'|'.$a->subject_id.'|'.$a->event => true])
            ->all();
    }

    private function coletarEventos(Tenant $tenant): array
    {
        $this->carregarExistentes();

        $eventos = [];
        $adicionar = function (string $tipo, $id, string $event, ?int $leadId, string $descricao, $data) use (&$eventos, $tenant) {
            if ($leadId === null || $data === null) {
                return;
            }

            $eventos[] = [
                'tenant_id' => $tenant->id,
                'lead_id' => $leadId,
                'log_name' => 'default',
                'description' => $descricao,
                'subject_type' => $tipo,
                'subject_id' => $id,
                'event' => $event,
                'causer_type' => null,
                'causer_id' => null,
                'properties' => json_encode(['backfill' => true]),
                'created_at' => $data,
                'updated_at' => $data,
            ];
        };

        foreach (Usuario::all() as $lead) {
            $adicionar(Usuario::class, $lead->id, 'lead_criado', $lead->id, 'Lead cadastrado no sistema', $lead->created_at);
        }

        foreach (UsuarioTagHistorico::all() as $historico) {
            $adicionar(UsuarioTagHistorico::class, $historico->id, 'status_alterado', $historico->usuario_id, 'Estágio do lead alterado', $historico->created_at);
        }

        $comSoftDelete = [
            [Anotacao::withTrashed()->get(), 'anotacao', 'anotacao_removida', 'Anotação adicionada', 'Anotação removida', fn ($m) => $m->usuario_id],
            [arquivo::withTrashed()->get(), 'arquivo', 'arquivo_removido', 'Arquivo anexado', 'Arquivo removido', fn ($m) => $m->usuario_id],
        ];

        $projetos = Projeto::all()->keyBy('id');

        $comSoftDelete[] = [
            ProjetoAnotacao::withTrashed()->get(), 'projeto_anotacao', 'projeto_anotacao_removida',
            'Anotação de projeto adicionada', 'Anotação de projeto removida',
            fn ($m) => $projetos->get($m->projeto_id)?->usuario_id,
        ];
        $comSoftDelete[] = [
            ProjetoAnexo::withTrashed()->get(), 'projeto_anexo', 'projeto_anexo_removido',
            'Anexo de projeto adicionado', 'Anexo de projeto removido',
            fn ($m) => $projetos->get($m->projeto_id)?->usuario_id,
        ];

        foreach ($comSoftDelete as [$colecao, $eventoCriado, $eventoRemovido, $descCriado, $descRemovido, $lead]) {
            foreach ($colecao as $model) {
                $adicionar($model::class, $model->id, $eventoCriado, $lead($model), $descCriado, $model->created_at);

                if ($model->deleted_at !== null) {
                    $adicionar($model::class, $model->id, $eventoRemovido, $lead($model), $descRemovido, $model->deleted_at);
                }
            }
        }

        foreach ($projetos as $projeto) {
            $adicionar(Projeto::class, $projeto->id, 'projeto', $projeto->usuario_id, 'Projeto criado', $projeto->created_at);
        }

        foreach (Tarefa::withTrashed()->get() as $tarefa) {
            $adicionar(Tarefa::class, $tarefa->id, 'tarefa_criada', $tarefa->usuario_id, 'Tarefa criada', $tarefa->created_at);

            if ($tarefa->concluido && $tarefa->concluido_em) {
                $adicionar(Tarefa::class, $tarefa->id, 'tarefa_concluida', $tarefa->usuario_id, 'Tarefa concluída', $tarefa->concluido_em);
            }
        }

        return $eventos;
    }
}
