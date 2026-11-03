<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Tests\TestCase;

/**
 * O slug do tenant é único no banco (tenants_slug_unique). A factory gera o
 * slug como o cadastro faz: slug do nome mais um sufixo aleatório. Sorteá-lo
 * de um segundo nome de empresa colidia de vez em quando e derrubava a suíte
 * com UniqueConstraintViolationException em testes que nem olhavam o tenant
 * — mais no CI, onde o Faker roda em pt_BR e tem bem menos nomes de empresa.
 */
class TenantFactoryTest extends TestCase
{
    public function test_slug_segue_o_nome_e_ganha_sufixo_proprio(): void
    {
        $a = Tenant::factory()->make(['nome' => 'Conroy LLC']);
        $b = Tenant::factory()->make(['nome' => 'Conroy LLC']);

        $this->assertStringStartsWith('conroy-llc-', $a->slug);
        $this->assertStringStartsWith('conroy-llc-', $b->slug);
        $this->assertNotSame($a->slug, $b->slug);
    }
}
