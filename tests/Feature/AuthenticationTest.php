<?php

namespace Tests\Feature;

use App\Livewire\Auth\RequestLogin;
use App\Livewire\Auth\VerifyLoginCode;
use App\Mail\LoginChallengeMail;
use App\Models\LoginChallenge;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_view_the_login_page_and_authenticated_users_are_redirected_to_agenda(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Send login link and code');

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('agenda'));
    }

    public function test_enabled_user_receives_a_login_challenge_and_email_without_persisting_plaintext_credentials(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'HOST@EXAMPLE.TEST']);
        $sentMail = null;

        Livewire::test(RequestLogin::class)
            ->set('email', ' Host@Example.Test ')
            ->call('send')
            ->assertRedirect(route('login.code'));

        Mail::assertSent(LoginChallengeMail::class, function (LoginChallengeMail $mail) use (&$sentMail, $user): bool {
            $sentMail = $mail;

            return $mail->hasTo($user->email);
        });

        self::assertInstanceOf(LoginChallengeMail::class, $sentMail);
        self::assertMatchesRegularExpression('/^\d{6}$/', $sentMail->otp);
        self::assertStringContainsString('login/magic/', $sentMail->magicUrl);
        self::assertStringContainsString('expire', $sentMail->render());

        $challenge = LoginChallenge::query()->sole();
        self::assertNotSame($sentMail->otp, $challenge->otp_hash);
        self::assertNotSame($sentMail->magicUrl, $challenge->magic_token_hash);
        self::assertSame(hash('sha256', $this->tokenFromUrl($sentMail->magicUrl)), $challenge->magic_token_hash);
        self::assertSame('host@example.test', $user->fresh()->email);
    }

    public function test_unknown_and_disabled_addresses_receive_the_same_redirect_without_email(): void
    {
        Mail::fake();
        $disabledUser = User::factory()->disabled()->create(['email' => 'disabled@example.test']);

        Livewire::test(RequestLogin::class)
            ->set('email', 'unknown@example.test')
            ->call('send')
            ->assertRedirect(route('login.code'));

        Livewire::test(RequestLogin::class)
            ->set('email', $disabledUser->email)
            ->call('send')
            ->assertRedirect(route('login.code'));

        Mail::assertNothingSent();
        self::assertDatabaseCount('login_challenges', 0);
    }

    public function test_a_new_login_challenge_invalidates_previous_unconsumed_challenges(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        Livewire::test(RequestLogin::class)->set('email', $user->email)->call('send');
        $firstChallenge = LoginChallenge::query()->sole();
        Livewire::test(RequestLogin::class)->set('email', $user->email)->call('send');

        $firstChallenge->refresh();

        self::assertNotNull($firstChallenge->consumed_at);
        self::assertDatabaseCount('login_challenges', 2);
        Mail::assertSent(LoginChallengeMail::class, 2);
    }

    public function test_correct_otp_authenticates_consumes_the_challenge_and_invalidates_other_challenges(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $otherChallenge = LoginChallenge::factory()->create(['user_id' => $user->id]);
        $mail = $this->requestLoginFor($user);
        $challenge = LoginChallenge::query()->latest('id')->firstOrFail();

        $this->withSession(['login_challenge_id' => $challenge->id]);

        Livewire::test(VerifyLoginCode::class)
            ->set('otp', $mail->otp)
            ->call('verify')
            ->assertRedirect(route('agenda'));

        $this->assertAuthenticatedAs($user);
        self::assertNotNull($challenge->fresh()->consumed_at);
        self::assertNotNull($otherChallenge->fresh()->consumed_at);
        self::assertNotNull($user->fresh()->last_login_at);
    }

    public function test_incorrect_otp_increments_attempts_and_five_failures_make_the_challenge_unusable(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $mail = $this->requestLoginFor($user);
        $challenge = LoginChallenge::query()->sole();
        $incorrectOtp = $mail->otp === '000000' ? '000001' : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withSession(['login_challenge_id' => $challenge->id]);

            Livewire::test(VerifyLoginCode::class)
                ->set('otp', $incorrectOtp)
                ->call('verify')
                ->assertDispatched('toast', type: 'error', message: 'This sign-in code is invalid or has expired.');
        }

        $challenge->refresh();
        self::assertSame(5, $challenge->attempts);
        self::assertNotNull($challenge->consumed_at);

        $this->withSession(['login_challenge_id' => $challenge->id]);
        Livewire::test(VerifyLoginCode::class)->set('otp', $mail->otp)->call('verify');

        $this->assertGuest();
    }

    public function test_expired_consumed_and_disabled_challenges_cannot_authenticate_with_the_correct_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $mail = $this->requestLoginFor($user);
        $challenge = LoginChallenge::query()->sole();
        $challenge->forceFill(['expires_at' => CarbonImmutable::now()->subSecond()])->save();

        $this->withSession(['login_challenge_id' => $challenge->id]);
        Livewire::test(VerifyLoginCode::class)->set('otp', $mail->otp)->call('verify');
        $this->assertGuest();

        $challenge->forceFill(['expires_at' => CarbonImmutable::now()->addMinutes(10), 'consumed_at' => now()])->save();
        $this->withSession(['login_challenge_id' => $challenge->id]);
        Livewire::test(VerifyLoginCode::class)->set('otp', $mail->otp)->call('verify');
        $this->assertGuest();

        $challenge->forceFill(['consumed_at' => null])->save();
        $user->forceFill(['enabled' => false])->save();
        $this->withSession(['login_challenge_id' => $challenge->id]);
        Livewire::test(VerifyLoginCode::class)->set('otp', $mail->otp)->call('verify');
        $this->assertGuest();
    }

    public function test_valid_magic_link_authenticates_and_redirects_to_agenda_without_the_token(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $mail = $this->requestLoginFor($user);

        $this->get($mail->magicUrl)->assertRedirect(route('agenda'));

        $this->assertAuthenticatedAs($user);
        self::assertNotNull(LoginChallenge::query()->sole()->consumed_at);
        self::assertNotNull($user->fresh()->last_login_at);

        Auth::logout();
        $this->get($mail->magicUrl)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_expired_consumed_and_disabled_magic_links_do_not_authenticate(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $mail = $this->requestLoginFor($user);

        $invalidUrl = str_replace('/'.$this->tokenFromUrl($mail->magicUrl).'?', '/invalid?', $mail->magicUrl);

        $this->get($invalidUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'This sign-in link is invalid or has expired.');
        $this->assertGuest();

        $challenge = LoginChallenge::query()->sole();
        $challenge->forceFill(['expires_at' => CarbonImmutable::now()->subSecond()])->save();
        $this->get($mail->magicUrl)->assertRedirect(route('login'));
        $this->assertGuest();

        $challenge->forceFill(['expires_at' => CarbonImmutable::now()->addMinutes(10), 'consumed_at' => null])->save();
        $user->forceFill(['enabled' => false])->save();
        $this->get($mail->magicUrl)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_request_and_otp_verification_are_rate_limited(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            Livewire::test(RequestLogin::class)->set('email', $user->email)->call('send');
        }

        Livewire::test(RequestLogin::class)
            ->set('email', $user->email)
            ->call('send')
            ->assertDispatched('toast', type: 'error', message: 'Too many sign-in requests. Please try again later.');

        $challenge = LoginChallenge::query()->latest('id')->firstOrFail();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withSession(['login_challenge_id' => $challenge->id]);
            Livewire::test(VerifyLoginCode::class)->set('otp', '000000')->call('verify');
        }

        $this->withSession(['login_challenge_id' => $challenge->id]);
        Livewire::test(VerifyLoginCode::class)
            ->set('otp', '000000')
            ->call('verify')
            ->assertDispatched('toast', type: 'error', message: 'Too many verification attempts. Please request a new sign-in email.');
    }

    public function test_authenticated_users_can_log_out_with_post_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Signed out.');

        $this->assertGuest();
        $this->get(route('logout'))->assertMethodNotAllowed();
    }

    public function test_console_command_creates_or_enables_an_administrator(): void
    {
        $this->artisan('app:create-admin admin@example.test --name="Conference Admin"')
            ->assertSuccessful();

        self::assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'name' => 'Conference Admin',
            'is_admin' => true,
            'enabled' => true,
        ]);
    }

    private function requestLoginFor(User $user): LoginChallengeMail
    {
        $sentMail = null;

        Livewire::test(RequestLogin::class)->set('email', $user->email)->call('send');

        Mail::assertSent(LoginChallengeMail::class, function (LoginChallengeMail $mail) use (&$sentMail): bool {
            $sentMail = $mail;

            return true;
        });

        self::assertInstanceOf(LoginChallengeMail::class, $sentMail);

        return $sentMail;
    }

    private function tokenFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        self::assertIsString($path);
        $segments = explode('/', trim($path, '/'));

        return (string) end($segments);
    }
}
