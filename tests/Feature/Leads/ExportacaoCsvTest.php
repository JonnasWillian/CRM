<?php

namespace Tests\Feature\Leads;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Exportar CSV: BOM UTF-8, ';', cabeçalhos iguais aos da importação, filtros e carteira respeitados. */
class ExportacaoCsvTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function linhas(string $csv): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        return array_map(fn ($l) => str_getcsv($l, ';'), array_values(array_filter(explode("\n", substr($csv, 3)))));
    }

    public function test_vendedor_exporta_so_a_carteira_com_os_filtros(): void
    {
        $tenant = $this->novoTenant();
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $this->lead($tenant, $ana, ['nome' => 'Alfa Comercial', 'valor_estimado' => 1500.5]);
        $this->lead($tenant, $ana, ['nome' => 'Beta Serviços']);
        $this->lead($tenant, $bia, ['nome' => 'Gama da Bia']);

        $resposta = $this->actingAs($ana)->get('/api/leads/exportar?busca=alfa');
        $resposta->assertOk();
        $this->assertStringContainsString('text/csv', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename=leads-', $resposta->headers->get('content-disposition'));

        $linhas = $this->linhas($resposta->streamedContent());
        $this->assertSame(['nome', 'email', 'telefone', 'descricao', 'funil', 'estagio', 'valor_estimado', 'probabilidade', 'previsao_fechamento', 'responsavel', 'criado_em', 'ultimo_contato'], $linhas[0]);
        $this->assertCount(2, $linhas);
        $this->assertSame('Alfa Comercial', $linhas[1][0]);
        $this->assertSame('1500,50', $linhas[1][6]);

        $todas = $this->linhas($this->actingAs($ana)->get('/api/leads/exportar')->streamedContent());
        $this->assertCount(3, $todas); // cabeçalho + 2 da Ana
    }

    public function test_exporta_so_os_selecionados(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $a = $this->lead($tenant, $gestor, ['nome' => 'Um']);
        $this->lead($tenant, $gestor, ['nome' => 'Dois']);

        $linhas = $this->linhas($this->actingAs($gestor)->get('/api/leads/exportar?'.http_build_query(['ids' => [$a->id]]))->streamedContent());
        $this->assertCount(2, $linhas);
        $this->assertSame('Um', $linhas[1][0]);
    }
}
