<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
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

    public function test_guests_and_disabled_users_cannot_access_the_offline_manifest(): void
    {
        $this->get(route('offline.manifest'))->assertRedirect(route('login'));

        $disabledUser = User::factory()->disabled()->create();

        $this->actingAs($disabledUser)->get(route('offline.manifest'))->assertForbidden();
    }

    public function test_offline_manifest_contains_only_active_mc_facing_pages(): void
    {
        $user = User::factory()->create();
        $firstSession = ConferenceSession::factory()->create(['starts_at' => now()->addHour()]);
        $secondSession = ConferenceSession::factory()->create(['starts_at' => now()->addHours(2)]);
        $removedSession = ConferenceSession::factory()->removed()->create();

        $response = $this->actingAs($user)->getJson(route('offline.manifest'));

        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        self::assertSame(['version', 'urls'], array_keys($response->json()));
        self::assertIsString($response->json('version'));

        $urls = $response->json('urls');

        self::assertSame([
            route('agenda'),
            route('sessions.index'),
            route('live'),
            route('sessions.show', $firstSession),
            route('sessions.show', $secondSession),
        ], $urls);
        self::assertNotContains(route('sessions.show', $removedSession), $urls);
        self::assertNotContains(route('admin.users.index'), $urls);

        foreach ($urls as $url) {
            self::assertStringStartsWith(url('/'), $url);
        }

        $response->assertDontSee($user->email)->assertDontSee('private_cache_namespace');
    }
}
