<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SpaRoutingTest extends TestCase
{
    public function test_the_client_routes_are_registered_without_capturing_backend_routes(): void
    {
        foreach (['/', '/agenda', '/sessions', '/sessions/1', '/live', '/admin/users', '/admin/sync'] as $path) {
            self::assertNotNull(Route::getRoutes()->match(Request::create($path, 'GET')));
        }

        self::assertSame('api.v1.snapshot', Route::getRoutes()->match(Request::create('/api/v1/snapshot', 'GET'))->getName());
        self::assertSame('auth.magic', Route::getRoutes()->match(Request::create('/login/magic/1/token', 'GET'))->getName());
    }

    public function test_csrf_cookie_endpoint_is_available_to_the_spa(): void
    {
        $this->get(route('csrf.cookie'))->assertNoContent()->assertCookie('XSRF-TOKEN');
    }

    public function test_authenticated_users_are_redirected_away_from_the_login_shell(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('agenda'));
    }

    public function test_the_spa_shell_redirects_to_the_development_server_when_the_production_build_is_absent(): void
    {
        config()->set('app.frontend_url', 'http://frontend.test');

        $this->get(route('login', ['return' => 'sessions']))
            ->assertRedirect('http://frontend.test/login?return=sessions');
    }

    public function test_web_logout_remains_a_post_only_session_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
