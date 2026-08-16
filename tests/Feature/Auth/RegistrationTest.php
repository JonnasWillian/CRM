<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_registration_seeds_default_tags_and_status_for_the_tenant(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $tags = $this->postJson('/api/tags');
        $tags->assertOk();
        $this->assertNotEmpty($tags->json());

        $status = $this->postJson('/api/status');
        $status->assertOk();
        $this->assertNotEmpty($status->json());
    }

    public function test_two_registrations_create_two_distinct_tenants(): void
    {
        $this->post('/register', [
            'name' => 'User One',
            'email' => 'user-one@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $userOne = User::where('email', 'user-one@example.com')->firstOrFail();

        $this->post('/logout');

        $this->post('/register', [
            'name' => 'User Two',
            'email' => 'user-two@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $userTwo = User::where('email', 'user-two@example.com')->firstOrFail();

        $this->assertNotNull($userOne->tenant_id);
        $this->assertNotNull($userTwo->tenant_id);
        $this->assertNotEquals($userOne->tenant_id, $userTwo->tenant_id);
    }
}
