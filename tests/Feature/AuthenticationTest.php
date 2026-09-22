<?php

namespace Tests\Feature;

use App\Mail\LoginChallengeMail;
use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_valid_magic_link_authenticates_and_redirects_to_the_spa_without_its_token(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson(route('api.v1.auth.challenge'), ['email' => $user->email])->assertOk();
        $mail = $this->sentMail();

        $this->get($mail->magicUrl)->assertRedirect(route('agenda'));

        $this->assertAuthenticatedAs($user);
        self::assertNotNull(LoginChallenge::query()->sole()->consumed_at);

        Auth::logout();
        $this->get($mail->magicUrl)->assertRedirect(route('login', ['error' => 'magic-link-invalid']));
        $this->assertGuest();
    }

    public function test_console_command_creates_or_enables_an_administrator(): void
    {
        $this->artisan('app:create-admin admin@example.test --name="Conference Admin"')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'name' => 'Conference Admin',
            'is_admin' => true,
            'enabled' => true,
        ]);
    }

    private function sentMail(): LoginChallengeMail
    {
        $mail = null;

        Mail::assertSent(LoginChallengeMail::class, function (LoginChallengeMail $sent) use (&$mail): bool {
            $mail = $sent;

            return true;
        });

        self::assertInstanceOf(LoginChallengeMail::class, $mail);

        return $mail;
    }
}
