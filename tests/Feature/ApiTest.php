<?php

namespace Tests\Feature;

use App\Enums\SyncRunStatus;
use App\Jobs\SyncSessionize;
use App\Mail\LoginChallengeMail;
use App\Models\ConferenceSession;
use App\Models\LoginChallenge;
use App\Models\SessionNote;
use App\Models\Speaker;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_for_a_guest_request_to_me(): void
    {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_returns_403_for_a_disabled_user_request_to_me(): void
    {
        $this->actingAs(User::factory()->disabled()->create())
            ->getJson(route('api.v1.me'))
            ->assertForbidden();
    }

    public function test_returns_the_authenticated_user_without_sensitive_fields(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonMissingPath('data.last_login_at');
    }

    public function test_issues_a_login_challenge_without_revealing_account_existence(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'mc@example.test']);

        $known = $this->postJson(route('api.v1.auth.challenge'), ['email' => ' MC@EXAMPLE.TEST ']);
        $unknown = $this->postJson(route('api.v1.auth.challenge'), ['email' => 'unknown@example.test']);

        $known->assertOk()->assertExactJson(['message' => 'If an account exists for this email, a sign-in message has been sent.']);
        $unknown->assertOk()->assertExactJson(['message' => 'If an account exists for this email, a sign-in message has been sent.']);
        $this->assertDatabaseCount('login_challenges', 1);
        Mail::assertSent(LoginChallengeMail::class, fn (LoginChallengeMail $mail): bool => $mail->hasTo($user->email));
    }

    public function test_does_not_send_a_login_challenge_for_a_disabled_user(): void
    {
        Mail::fake();
        $user = User::factory()->disabled()->create();

        $this->postJson(route('api.v1.auth.challenge'), ['email' => $user->email])
            ->assertOk()
            ->assertExactJson(['message' => 'If an account exists for this email, a sign-in message has been sent.']);

        $this->assertDatabaseCount('login_challenges', 0);
        Mail::assertNothingSent();
    }

    public function test_rate_limits_login_challenge_requests_without_sending_unknown_email(): void
    {
        Mail::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('api.v1.auth.challenge'), ['email' => 'unknown@example.test'])->assertOk();
        }

        $this->postJson(route('api.v1.auth.challenge'), ['email' => 'unknown@example.test'])->assertTooManyRequests();
        Mail::assertNothingSent();
    }

    public function test_verifies_an_otp_using_the_server_side_challenge_context(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $mail = $this->requestChallenge($user->email);

        $this->postJson(route('api.v1.auth.verify-otp'), ['otp' => $mail->otp])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->assertAuthenticatedAs($user);
        self::assertNotNull(LoginChallenge::query()->sole()->consumed_at);
    }

    public function test_rejects_an_invalid_otp_and_preserves_the_generic_error(): void
    {
        Mail::fake();
        $this->requestChallenge(User::factory()->create()->email);

        $this->postJson(route('api.v1.auth.verify-otp'), ['otp' => '000000'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'This sign-in code is invalid or has expired.']);
    }

    public function test_snapshot_contains_the_complete_active_conference_read_model_without_secrets(): void
    {
        $user = User::factory()->create();
        $session = ConferenceSession::factory()->create(['title' => 'Opening', 'mc_description' => 'Welcome', 'mc_script' => 'Introduce the keynote']);
        $speaker = Speaker::factory()->create(['name' => 'Ada Lovelace']);
        $session->speakers()->attach($speaker, ['sort_order' => 0]);
        $session->mcs()->attach($user);
        SessionNote::factory()->create(['conference_session_id' => $session->id, 'body' => 'Check the stage microphone.']);
        $removed = ConferenceSession::factory()->removed()->create(['title' => 'Removed']);

        $response = $this->actingAs($user)->getJson(route('api.v1.snapshot'));

        $response->assertOk()
            ->assertJsonPath('current_user.id', $user->id)
            ->assertJsonPath('sessions.0.title', 'Opening')
            ->assertJsonPath('sessions.0.mc_description', 'Welcome')
            ->assertJsonPath('sessions.0.mc_script', 'Introduce the keynote')
            ->assertJsonPath('sessions.0.speakers.0.name', 'Ada Lovelace')
            ->assertJsonPath('sessions.0.mcs.0.id', $user->id)
            ->assertJsonPath('sessions.0.notes.0.body', 'Check the stage microphone.')
            ->assertJsonMissingPath('sessions.0.notes.0.user_id')
            ->assertJsonMissingPath('sessions.0.mcs.0.email')
            ->assertJsonMissing(['title' => $removed->title]);
    }

    public function test_snapshot_version_is_stable_when_conference_data_is_unchanged(): void
    {
        $user = User::factory()->create();
        ConferenceSession::factory()->create();

        $first = $this->actingAs($user)->getJson(route('api.v1.snapshot'))->json('version');
        $second = $this->actingAs($user)->getJson(route('api.v1.snapshot'))->json('version');

        self::assertSame($first, $second);
    }

    public function test_normal_users_cannot_request_removed_sessions_or_admin_resources(): void
    {
        $user = User::factory()->create();
        $removed = ConferenceSession::factory()->removed()->create();

        $this->actingAs($user)->getJson(route('api.v1.sessions.index', ['show_removed' => 1]))->assertOk()->assertJsonMissing(['id' => $removed->id]);
        $this->actingAs($user)->getJson(route('api.v1.sessions.show', $removed))->assertForbidden();
        $this->actingAs($user)->getJson(route('api.v1.users.index'))->assertForbidden();
    }

    public function test_admin_can_explicitly_read_removed_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $removed = ConferenceSession::factory()->removed()->create();

        $this->actingAs($admin)->getJson(route('api.v1.sessions.index', ['show_removed' => 1]))
            ->assertOk()
            ->assertJsonFragment(['id' => $removed->id]);
        $this->actingAs($admin)->getJson(route('api.v1.sessions.show', $removed))->assertOk();
    }

    public function test_enabled_user_can_update_only_mc_content_through_the_api(): void
    {
        $user = User::factory()->create();
        $session = ConferenceSession::factory()->create(['title' => 'Imported title']);

        $this->actingAs($user)->patchJson(route('api.v1.sessions.mc-content', $session), [
            'mc_description' => 'Welcome.',
            'mc_script' => 'Introduce the speaker.',
            'title' => 'Attempted override',
        ])->assertOk()->assertJsonPath('data.mc_description', 'Welcome.');

        self::assertSame('Imported title', $session->fresh()->title);
    }

    public function test_only_admins_can_mutate_notes_and_mc_assignments(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $session = ConferenceSession::factory()->create();
        $mc = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.v1.sessions.notes.store', $session), ['body' => 'Technical note'])->assertForbidden();
        $this->actingAs($admin)->postJson(route('api.v1.sessions.notes.store', $session), ['body' => 'Technical note'])->assertSuccessful();
        $note = SessionNote::query()->sole();
        $this->actingAs($admin)->patchJson(route('api.v1.notes.update', $note), ['body' => 'Updated note'])->assertOk();
        self::assertSame('Updated note', $note->fresh()->body);
        $this->actingAs($admin)->deleteJson(route('api.v1.notes.destroy', $note))->assertNoContent();

        $this->actingAs($user)->postJson(route('api.v1.sessions.mcs.store', $session), ['user_id' => $mc->id])->assertForbidden();
        $this->actingAs($admin)->postJson(route('api.v1.sessions.mcs.store', $session), ['user_id' => $mc->id])->assertOk();
        $this->actingAs($admin)->deleteJson(route('api.v1.sessions.mcs.destroy', [$session, $mc]))->assertNoContent();
    }

    public function test_admin_can_manage_users_with_validation_and_normalization(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('api.v1.users.store'), [
            'name' => 'New user',
            'email' => ' NEW@EXAMPLE.TEST ',
            'is_admin' => false,
            'enabled' => true,
        ])->assertSuccessful()->assertJsonPath('data.email', 'new@example.test');

        $newUser = User::query()->where('email', 'new@example.test')->sole();
        $this->actingAs($admin)->patchJson(route('api.v1.users.update', $newUser), [
            'name' => 'Updated user',
            'email' => 'updated@example.test',
            'is_admin' => false,
            'enabled' => false,
        ])->assertOk()->assertJsonPath('data.enabled', false);
    }

    public function test_sessionize_admin_api_hides_the_endpoint_and_dispatches_a_job(): void
    {
        Bus::fake();
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/private/view/All']);
        $admin = User::factory()->admin()->create();
        SyncRun::factory()->create(['status' => SyncRunStatus::Completed]);

        $this->actingAs($admin)->getJson(route('api.v1.sessionize.show'))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonMissing(['https://sessionize.com/api/v2/private/view/All']);
        $this->actingAs($admin)->postJson(route('api.v1.sessionize.sync'))
            ->assertCreated();
        Bus::assertDispatched(SyncSessionize::class);
    }

    public function test_api_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.v1.logout'))->assertNoContent();
        $this->assertGuest();
    }

    private function requestChallenge(string $email): LoginChallengeMail
    {
        $mail = null;
        $this->postJson(route('api.v1.auth.challenge'), ['email' => $email])->assertOk();
        Mail::assertSent(LoginChallengeMail::class, function (LoginChallengeMail $sent) use (&$mail): bool {
            $mail = $sent;

            return true;
        });
        self::assertInstanceOf(LoginChallengeMail::class, $mail);

        return $mail;
    }
}
