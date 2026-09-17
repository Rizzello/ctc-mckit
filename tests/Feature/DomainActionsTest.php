<?php

namespace Tests\Feature;

use App\Actions\AddSessionNote;
use App\Actions\AssignMcToConferenceSession;
use App\Actions\CreateUser;
use App\Actions\DeleteSessionNote;
use App\Actions\DisableUser;
use App\Actions\UnassignMcFromConferenceSession;
use App\Actions\UpdateConferenceSessionMcContent;
use App\Actions\UpdateSessionNote;
use App\Actions\UpdateUser;
use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_assign_enabled_users_and_the_assignment_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();
        $mc = User::factory()->create();
        $adminMc = User::factory()->admin()->create();
        $action = new AssignMcToConferenceSession;

        $action->handle($admin, $conferenceSession, $mc);
        $action->handle($admin, $conferenceSession, $mc);
        $action->handle($admin, $conferenceSession, $adminMc);

        $this->assertSame([$mc->id, $adminMc->id], $conferenceSession->fresh()->mcs->pluck('id')->sort()->values()->all());
    }

    public function test_non_admin_cannot_assign_an_mc(): void
    {
        $actor = User::factory()->create();
        $conferenceSession = ConferenceSession::factory()->create();

        $this->expectException(AuthorizationException::class);

        (new AssignMcToConferenceSession)->handle($actor, $conferenceSession, User::factory()->create());
    }

    public function test_disabled_users_cannot_be_assigned_as_mcs(): void
    {
        $admin = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();
        $disabledUser = User::factory()->disabled()->create();

        $this->expectException(ValidationException::class);

        (new AssignMcToConferenceSession)->handle($admin, $conferenceSession, $disabledUser);
    }

    public function test_admin_can_safely_unassign_an_mc_multiple_times(): void
    {
        $admin = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();
        $mc = User::factory()->create();
        $conferenceSession->mcs()->attach($mc);
        $action = new UnassignMcFromConferenceSession;

        $action->handle($admin, $conferenceSession, $mc);
        $action->handle($admin, $conferenceSession, $mc);

        $this->assertCount(0, $conferenceSession->fresh()->mcs);
    }

    public function test_enabled_users_can_update_only_local_mc_content(): void
    {
        $actor = User::factory()->create();
        $conferenceSession = ConferenceSession::factory()->create([
            'title' => 'Imported title',
            'description' => 'Imported description',
            'mc_description' => null,
            'mc_script' => null,
        ]);

        (new UpdateConferenceSessionMcContent)->handle($actor, $conferenceSession, 'Welcome everybody.', 'Introduce the speaker.');

        $this->assertSame('Welcome everybody.', $conferenceSession->fresh()->mc_description);
        $this->assertSame('Introduce the speaker.', $conferenceSession->fresh()->mc_script);
        $this->assertSame('Imported title', $conferenceSession->fresh()->title);
        $this->assertSame('Imported description', $conferenceSession->fresh()->description);
    }

    public function test_disabled_users_cannot_update_mc_content(): void
    {
        $conferenceSession = ConferenceSession::factory()->create();

        $this->expectException(AuthorizationException::class);

        (new UpdateConferenceSessionMcContent)->handle(User::factory()->disabled()->create(), $conferenceSession, 'Welcome.', null);
    }

    public function test_only_an_enabled_admin_can_create_a_note_without_spoofing_its_author(): void
    {
        $actor = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();

        $note = (new AddSessionNote)->handle($actor, $conferenceSession, 'Check the presenter microphone.');

        $this->assertSame($actor->id, $note->user_id);
        $this->assertSame($conferenceSession->id, $note->conference_session_id);
    }

    public function test_non_admin_users_cannot_create_notes(): void
    {
        $actor = User::factory()->create();
        $conferenceSession = ConferenceSession::factory()->create();

        $this->expectException(AuthorizationException::class);

        (new AddSessionNote)->handle($actor, $conferenceSession, 'Check the presenter microphone.');
    }

    public function test_non_admin_note_authors_cannot_update_their_note(): void
    {
        $author = User::factory()->create();
        $note = SessionNote::factory()->create(['user_id' => $author]);

        $this->expectException(AuthorizationException::class);

        (new UpdateSessionNote)->handle($author, $note, 'Use the handheld microphone.');
    }

    public function test_non_admin_note_authors_cannot_delete_their_note(): void
    {
        $author = User::factory()->create();
        $note = SessionNote::factory()->create(['user_id' => $author]);

        $this->expectException(AuthorizationException::class);

        (new DeleteSessionNote)->handle($author, $note);
    }

    public function test_admin_can_update_and_delete_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $note = SessionNote::factory()->create(['body' => 'Original note.']);

        (new UpdateSessionNote)->handle($admin, $note, 'Updated by an administrator.');

        $this->assertSame('Updated by an administrator.', $note->fresh()->body);

        (new DeleteSessionNote)->handle($admin, $note);

        $this->assertDatabaseMissing('session_notes', ['id' => $note->id]);
    }

    public function test_note_content_is_validated(): void
    {
        $actor = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();

        $this->expectException(ValidationException::class);

        (new AddSessionNote)->handle($actor, $conferenceSession, '');
    }

    public function test_admin_can_create_users_with_normalized_emails_and_unique_addresses(): void
    {
        $admin = User::factory()->admin()->create();
        $action = new CreateUser;

        $user = $action->handle($admin, 'New MC', '  NEW.MC@Example.TEST  ', false, true);

        $this->assertSame('new.mc@example.test', $user->email);

        $this->expectException(ValidationException::class);

        $action->handle($admin, 'Duplicate MC', 'new.mc@example.test', false, true);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $actor = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        (new CreateUser)->handle($actor, 'New MC', 'new.mc@example.test', false, true);
    }

    public function test_final_enabled_administrator_cannot_be_disabled_or_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            (new DisableUser)->handle($admin, $admin);
            $this->fail('The final enabled administrator must not be disabled.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);

        (new UpdateUser)->handle($admin, $admin, $admin->name, $admin->email, false, true);
    }

    public function test_admin_can_disable_users_without_deleting_notes_or_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $mc = User::factory()->create();
        $conferenceSession = ConferenceSession::factory()->create();
        $conferenceSession->mcs()->attach($mc);
        $note = SessionNote::factory()->create(['user_id' => $mc, 'conference_session_id' => $conferenceSession]);

        (new DisableUser)->handle($admin, $mc);

        $this->assertFalse($mc->fresh()->enabled);
        $this->assertModelExists($note);
        $this->assertCount(1, $conferenceSession->fresh()->mcs);
    }

    public function test_admin_can_update_user_details_and_enablement(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->disabled()->create();

        (new UpdateUser)->handle($admin, $user, 'Updated MC', '  UPDATED@Example.TEST ', false, true);

        $this->assertSame('Updated MC', $user->fresh()->name);
        $this->assertSame('updated@example.test', $user->fresh()->email);
        $this->assertTrue($user->fresh()->enabled);
    }

    public function test_operational_and_admin_gates_enforce_enablement_and_capability(): void
    {
        $enabledUser = User::factory()->create();
        $disabledAdmin = User::factory()->admin()->disabled()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($enabledUser->can('view-operational-content'));
        $this->assertFalse($disabledAdmin->can('view-operational-content'));
        $this->assertFalse($enabledUser->can('sync-sessionize'));
        $this->assertFalse($disabledAdmin->can('sync-sessionize'));
        $this->assertTrue($admin->can('sync-sessionize'));
    }
}
