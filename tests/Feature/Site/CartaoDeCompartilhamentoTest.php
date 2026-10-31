<?php

namespace Tests\Feature\Site;

use Tests\TestCase;

/** /og-card: a página capturada em 1200×630 para gerar public/og.png. Fica fora do índice. */
class CartaoDeCompartilhamentoTest extends TestCase
{
    public function test_mostra_nome_e_teste_gratis_e_fica_fora_do_indice(): void
    {
        config(['app.name' => 'MeuCRM', 'comercial.dias_de_teste' => 21]);

        $this->withoutVite()->get('/og-card')->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('MeuCRM')
            ->assertSee('21 dias grátis');
        $this->get('/robots.txt')->assertSee('Disallow: /og-card');
    }
}
