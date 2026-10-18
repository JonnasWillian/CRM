<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_perfil_mostra_a_empresa_e_o_admin_renomeia(): void
    {
        $tenant = \App\Models\Tenant::factory()->create(['nome' => 'Nome Antigo']);
        app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->assignRole('admin');
        $vendedor = User::factory()->create(['tenant_id' => $tenant->id]);
        $vendedor->assignRole('vendedor');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($vendedor)->get('/profile')->assertOk()->assertInertia(fn ($page) => $page
            ->where('empresa.nome', 'Nome Antigo')
            ->where('empresa.papel', 'vendedor')
            ->where('empresa.editavel', false));
        $this->actingAs($vendedor)->patch('/profile/empresa', ['nome' => 'Tentativa'])->assertForbidden();

        $this->actingAs($admin)->patch('/profile/empresa', ['nome' => 'Acme Vendas'])->assertRedirect('/profile');
        $this->assertSame('Acme Vendas', $tenant->fresh()->nome);
        $this->actingAs($admin)->patch('/profile/empresa', ['nome' => ''])->assertSessionHasErrors('nome');
    }
}
