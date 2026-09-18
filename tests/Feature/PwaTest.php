<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_pages_receive_an_opaque_private_cache_namespace(): void
    {
        $user = User::factory()->create(['email' => 'mc@example.test']);

        $response = $this->actingAs($user)->get(route('agenda'));
        $namespace = session('private_cache_namespace');

        $this->assertIsString($namespace);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $namespace);
        $this->assertNotSame((string) $user->id, $namespace);
        $this->assertNotSame($user->email, $namespace);
        $response->assertSee('data-private-cache-namespace="'.$namespace.'"', false);
    }

    public function test_guest_pages_do_not_receive_a_private_cache_namespace(): void
    {
        $this->get(route('login'))
            ->assertDontSee('data-private-cache-namespace', false)
            ->assertSee('manifest.webmanifest', false);
    }

    public function test_a_new_authenticated_session_receives_a_new_private_cache_namespace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('agenda'));
        $firstNamespace = session('private_cache_namespace');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('agenda'));
        $secondNamespace = session('private_cache_namespace');

        $this->assertIsString($firstNamespace);
        $this->assertIsString($secondNamespace);
        $this->assertNotSame($firstNamespace, $secondNamespace);
    }

    public function test_public_pwa_assets_are_present(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('service-worker.js'));
        $this->assertFileExists(public_path('offline.html'));
    }
}
