<?php

namespace Tests\Feature\Importacao;

use App\Models\Importacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * importacoes:limpar (agendado todo dia): apaga o CSV das importações
 * paradas há mais de `leads.importacao.reter_arquivo_dias`, em todas as
 * empresas. O registro e os contadores ficam, para o histórico.
 */
class LimparImportacoesTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function importacao(User $autor, string $arquivo, int $diasParada): int
    {
        Storage::disk('local')->put($arquivo, "nome;email\nAna;ana@ex.test\n");

        return DB::table('importacoes')->insertGetId([
            'tenant_id' => $autor->tenant_id, 'user_id' => $autor->id, 'arquivo' => $arquivo, 'nome_original' => 'leads.csv',
            'status' => Importacao::CONCLUIDA, 'total_linhas' => 1, 'criadas' => 1,
            'created_at' => now()->subDays($diasParada), 'updated_at' => now()->subDays($diasParada),
        ]);
    }

    public function test_apaga_so_os_arquivos_antigos_de_todas_as_empresas_e_mantem_o_registro(): void
    {
        Storage::fake('local');
        $a = $this->agente($this->novoTenant(), 'admin');
        $b = $this->agente($this->novoTenant(), 'admin');
        $velhaA = $this->importacao($a, 'importacoes/a.csv', 8);
        $velhaB = $this->importacao($b, 'importacoes/b.csv', 30);
        $recente = $this->importacao($a, 'importacoes/recente.csv', 2);

        $this->artisan('importacoes:limpar')->expectsOutputToContain('2 arquivo(s) removido(s).')->assertSuccessful();

        Storage::disk('local')->assertMissing(['importacoes/a.csv', 'importacoes/b.csv']);
        Storage::disk('local')->assertExists('importacoes/recente.csv');
        $this->assertSame(['', ''], DB::table('importacoes')->whereIn('id', [$velhaA, $velhaB])->pluck('arquivo')->all());
        $this->assertSame(1, (int) DB::table('importacoes')->where('id', $velhaA)->value('criadas'));
        $this->assertSame('importacoes/recente.csv', DB::table('importacoes')->where('id', $recente)->value('arquivo'));
    }

    public function test_rodar_de_novo_nao_reprocessa_e_respeita_o_prazo_configurado(): void
    {
        Storage::fake('local');
        $autor = $this->agente($this->novoTenant(), 'admin');
        $this->importacao($autor, 'importacoes/a.csv', 10);
        $this->artisan('importacoes:limpar')->assertSuccessful();

        $this->artisan('importacoes:limpar')->expectsOutputToContain('0 arquivo(s) removido(s).')->assertSuccessful();

        config(['leads.importacao.reter_arquivo_dias' => 1]);
        $this->importacao($autor, 'importacoes/b.csv', 2);
        $this->artisan('importacoes:limpar')->expectsOutputToContain('1 arquivo(s) removido(s).')->assertSuccessful();
    }
}
