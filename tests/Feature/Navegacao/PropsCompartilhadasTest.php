<?php

namespace Tests\Feature\Navegacao;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O front decide o que mostrar (menu, botões de transferir, convidar,
 * excluir) a partir das props compartilhadas. Elas precisam trazer o mapa
 * completo de permissões, os papéis e as mensagens de flash — não só duas
 * permissões escolhidas a dedo.
 */
class PropsCompartilhadasTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_admin_recebe_o_mapa_completo_de_permissoes_e_o_papel(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');

        // Os nomes de permission têm ponto ("agentes.manage"), então o mapa é
        // conferido inteiro em vez de por caminho pontilhado.
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($p) => $p['agentes.manage'] === true
                && $p['configuracoes.manage'] === true
                && $p['leads.view-all'] === true
                && $p['leads.manage'] === true
                && $p['projetos.manage'] === true
                && $p['tarefas.manage'] === true)
            ->where('auth.roles', ['admin']));
    }

    public function test_vendedor_recebe_as_permissoes_negadas_como_false(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant, 'vendedor');

        $this->actingAs($vendedor)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($p) => $p['agentes.manage'] === false
                && $p['leads.view-all'] === false
                && $p['leads.manage'] === true)
            ->where('auth.roles', ['vendedor']));
    }

    public function test_flash_de_sucesso_e_erro_chega_ao_front(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');

        $this->actingAs($admin)
            ->withSession(['sucesso' => 'Lead cadastrado.', 'erro' => 'Falhou.'])
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.sucesso', 'Lead cadastrado.')
                ->where('flash.erro', 'Falhou.'));
    }
}
