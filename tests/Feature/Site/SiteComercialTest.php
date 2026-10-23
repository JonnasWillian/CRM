<?php

namespace Tests\Feature\Site;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O site público (página inicial, preços, termos, privacidade) é HTML servido
 * pelo servidor, com as marcações que o Google lê: título, descrição,
 * canônica, Open Graph e JSON-LD. As telas do app (Inertia) ficam fora do
 * índice. Nome do produto e planos vêm da configuração, em um lugar só.
 */
class SiteComercialTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_ve_a_pagina_inicial_com_seo_e_chamada_para_cadastro(): void
    {
        config(['app.name' => 'MeuCRM', 'app.url' => 'https://meucrm.com.br']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="pt-BR"', $html);
        $this->assertStringContainsString('<title>MeuCRM', $html);
        $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{60,160}"/', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://meucrm.com.br/"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('property="og:image" content="https://meucrm.com.br/og.png"', $html);
        $this->assertStringContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        // Links de navegação são relativos: funcionam em qualquer host (APP_URL só vale para canônica, OG e sitemap).
        $this->assertStringContainsString('href="/register"', $html);
        $this->assertStringContainsString('href="/precos"', $html);
        $this->assertStringNotContainsString('href="https://meucrm.com.br/register"', $html);
        $this->assertStringNotContainsString('name="robots" content="noindex', $html);
    }

    public function test_quem_esta_logado_vai_para_o_dashboard(): void
    {
        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }

    public function test_precos_mostra_os_planos_da_configuracao(): void
    {
        config(['comercial.planos' => [
            ['chave' => 'teste', 'nome' => 'Plano Teste', 'preco_mensal' => 10, 'preco_anual' => 8, 'descricao' => 'x', 'inclui' => ['Uma coisa'], 'destaque' => false],
        ]]);

        $html = $this->get('/precos')->assertOk()->getContent();

        $this->assertStringContainsString('Plano Teste', $html);
        $this->assertStringContainsString('Uma coisa', $html);
        $this->assertStringContainsString('"price":"10', $html); // JSON-LD Offer
    }

    public function test_termos_e_privacidade_existem(): void
    {
        $this->get('/termos')->assertOk()->assertSee('Termos de uso');
        $this->get('/privacidade')->assertOk()->assertSee('LGPD');
    }

    public function test_robots_e_sitemap(): void
    {
        config(['app.url' => 'https://meucrm.com.br']);

        $robots = $this->get('/robots.txt')->assertOk()->assertHeader('content-type', 'text/plain; charset=UTF-8')->getContent();
        $this->assertStringContainsString('Sitemap: https://meucrm.com.br/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /dashboard', $robots);
        $this->assertStringContainsString('Disallow: /api/', $robots);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>https://meucrm.com.br/</loc>', $sitemap);
        $this->assertStringContainsString('<loc>https://meucrm.com.br/precos</loc>', $sitemap);
        $this->assertStringNotContainsString('/dashboard', $sitemap);
    }

    public function test_telas_do_app_ficam_fora_do_indice_e_compartilham_o_nome_do_produto(): void
    {
        config(['app.name' => 'MeuCRM']);

        $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex', false);

        // Visitante também recebe os planos (o cadastro mostra o plano escolhido no site).
        $this->get('/register')->assertInertia(fn ($page) => $page->has('app.planos', 3));
        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('app.nome', 'MeuCRM')->where('app.dias_de_teste', 14)->has('app.planos', 3)->has('app.planos.0', fn ($p) => $p->hasAll(['chave', 'nome', 'preco_mensal', 'preco_anual'])->etc()));
    }
}
