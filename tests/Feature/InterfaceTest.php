<?php

namespace Tests\Feature;

use App\Actions\AddSessionNote;
use App\Livewire\Admin\Users\CreateUser;
use App\Livewire\Admin\Users\EditUser;
use App\Livewire\Agenda;
use App\Livewire\LiveSchedule;
use App\Livewire\SessionDetail;
use App\Livewire\SessionsBrowser;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionNote;
use App\Models\Speaker;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enabled_users_can_reach_live_and_normal_users_do_not_see_administration_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('live'))
            ->assertOk()
            ->assertSee('Live')
            ->assertDontSee('>Users<', false)
            ->assertDontSee('>Sync<', false);
    }

    public function test_live_uses_the_configured_application_timezone_for_client_rendering(): void
    {
        config(['app.timezone' => 'Europe/Rome']);
        $user = User::factory()->create();
        ConferenceSession::factory()->create([
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
        ]);

        $this->actingAs($user);

        Livewire::test(LiveSchedule::class)
            ->assertSee('Europe', false)
            ->assertSee('Rome', false);
    }

    public function test_agenda_shows_the_complete_schedule_and_highlights_the_current_users_assignments(): void
    {
        $user = User::factory()->create();
        $assignedSession = ConferenceSession::factory()->create([
            'title' => 'My session',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
        ]);
        $assignedSession->mcs()->attach($user);
        ConferenceSession::factory()->create([
            'title' => 'Another MC session',
            'starts_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 11:00:00 UTC'),
        ]);

        $this->actingAs($user)->get(route('agenda'))->assertOk()->assertSee('Agenda');
        Livewire::test(Agenda::class)->assertSee('My session')->assertSee('Another MC session')->assertSee('Your assigned sessions');
    }

    public function test_agenda_expands_back_to_back_short_sessions_without_overlapping(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        ConferenceSession::factory()->create([
            'room_id' => $room->id,
            'title' => 'First short session',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 09:15:00 UTC'),
        ]);
        ConferenceSession::factory()->create([
            'room_id' => $room->id,
            'title' => 'Second short session',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:15:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 09:30:00 UTC'),
        ]);

        $this->actingAs($user);

        Livewire::test(Agenda::class)
            ->assertSee('First short session')
            ->assertSee('Second short session')
            ->assertSee('top: 96px;', false)
            ->assertSee('height: 96px;', false);
    }

    public function test_agenda_keeps_sessions_aligned_to_their_start_time_when_a_slot_is_available(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        ConferenceSession::factory()->serviceSession()->create([
            'room_id' => $room->id,
            'title' => 'Opening keynote',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:15:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 09:40:00 UTC'),
        ]);
        ConferenceSession::factory()->create([
            'room_id' => $room->id,
            'title' => 'First talk',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:45:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:25:00 UTC'),
        ]);

        $this->actingAs($user);

        Livewire::test(Agenda::class)
            ->assertSee('Opening keynote')
            ->assertSee('First talk')
            ->assertSee('top: 135px;', false)
            ->assertSee('height: 120px;', false);
    }

    public function test_agenda_displays_a_plenary_session_in_every_room_column(): void
    {
        $user = User::factory()->create();
        $firstRoom = Room::factory()->create(['name' => 'Main room']);
        Room::factory()->create(['name' => 'Side room']);
        ConferenceSession::factory()->create([
            'room_id' => $firstRoom->id,
            'title' => 'All-room keynote',
            'is_plenum_session' => true,
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
        ]);

        $this->actingAs($user);

        $component = Livewire::test(Agenda::class);

        $this->assertSame(2, substr_count($component->html(true), 'All-room keynote'));
        $this->assertSame(2, substr_count($component->html(true), 'Plenary'));
    }

    public function test_admin_users_see_users_and_sync_navigation_and_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('live'))
            ->assertOk()
            ->assertSee('>Users<', false)
            ->assertSee('>Sync<', false);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Users');
        $this->actingAs($admin)->get(route('admin.sessionize'))->assertOk()->assertSee('Sessionize');
    }

    public function test_disabled_and_unauthenticated_users_cannot_access_operational_content(): void
    {
        $disabledUser = User::factory()->disabled()->create();

        $this->get(route('live'))->assertRedirect(route('login'));
        $this->actingAs($disabledUser)->get(route('live'))->assertForbidden();
    }

    public function test_live_screen_excludes_removed_sessions_and_marks_current_session(): void
    {
        $user = User::factory()->create();
        $currentSession = ConferenceSession::factory()->create([
            'title' => 'Current session',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
            'mc_description' => 'Welcome the audience and introduce the speaker.',
        ]);
        $currentSession->mcs()->attach($user);
        ConferenceSession::factory()->removed()->create(['title' => 'Removed session']);

        $this->actingAs($user);
        $this->travelTo(CarbonImmutable::parse('2027-10-14 09:00:00 UTC'));

        Livewire::test(LiveSchedule::class)
            ->assertSee('Current session')
            ->assertSee('Previous')
            ->assertSee('Next')
            ->assertSee('Expand host preparation')
            ->assertDontSee('Removed session');

        $this->travelBack();
    }

    public function test_live_reader_loads_active_sessions_and_excludes_removed_sessions(): void
    {
        $user = User::factory()->create();
        $assignedSession = ConferenceSession::factory()->create(['title' => 'Active session']);
        $assignedSession->mcs()->attach($user);
        ConferenceSession::factory()->create(['title' => 'Another MC session']);
        ConferenceSession::factory()->removed()->create(['title' => 'Removed session']);

        $this->actingAs($user);

        Livewire::test(LiveSchedule::class)
            ->assertSee('Active session')
            ->assertDontSee('Another MC session')
            ->assertDontSee('Removed session');
    }

    public function test_live_reader_excludes_sessions_without_complete_scheduling_times(): void
    {
        $user = User::factory()->create();
        $scheduledSession = ConferenceSession::factory()->create(['title' => 'Scheduled session']);
        $scheduledSession->mcs()->attach($user);
        ConferenceSession::factory()->create([
            'title' => 'Incomplete session',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->actingAs($user);

        Livewire::test(LiveSchedule::class)
            ->assertSee('Scheduled session')
            ->assertDontSee('Incomplete session');
    }

    public function test_sessions_browser_searches_titles_and_speakers_and_hides_removed_by_default(): void
    {
        $user = User::factory()->create();
        $titleMatch = ConferenceSession::factory()->create(['title' => 'Resilient PHP']);
        $speakerMatch = ConferenceSession::factory()->create(['title' => 'Different title']);
        $speaker = Speaker::factory()->create(['name' => 'Amina Khan']);
        $speakerMatch->speakers()->attach($speaker, ['sort_order' => 0]);
        ConferenceSession::factory()->removed()->create(['title' => 'Removed session']);

        $this->actingAs($user);

        Livewire::test(SessionsBrowser::class)->set('search', 'Resilient')->assertSee($titleMatch->title)->assertDontSee($speakerMatch->title)->assertDontSee('Removed session');
        Livewire::test(SessionsBrowser::class)->set('search', 'Amina')->assertSee($speakerMatch->title);
    }

    public function test_normal_users_cannot_force_removed_sessions_in_the_sessions_browser(): void
    {
        $user = User::factory()->create();
        ConferenceSession::factory()->removed()->create(['title' => 'Removed only for admins']);

        $this->actingAs($user);

        Livewire::test(SessionsBrowser::class)
            ->set('showRemoved', true)
            ->assertDontSee('Removed only for admins')
            ->assertDontSee('Show removed sessions');
    }

    public function test_administrators_can_intentionally_inspect_removed_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        ConferenceSession::factory()->removed()->create(['title' => 'Removed for inspection']);

        $this->actingAs($admin);

        Livewire::test(SessionsBrowser::class)
            ->set('showRemoved', true)
            ->assertSee('Removed for inspection')
            ->assertSee('Show removed sessions');
    }

    public function test_agenda_excludes_removed_sessions(): void
    {
        $user = User::factory()->create();
        ConferenceSession::factory()->removed()->create(['title' => 'Removed from agenda']);

        $this->actingAs($user);

        Livewire::test(Agenda::class)->assertDontSee('Removed from agenda');
    }

    public function test_sessions_browser_offers_available_dates_and_filters_sessions_by_the_selected_day(): void
    {
        $user = User::factory()->create();
        $firstDaySession = ConferenceSession::factory()->create([
            'title' => 'First day session',
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
        ]);
        $secondDaySession = ConferenceSession::factory()->create([
            'title' => 'Second day session',
            'starts_at' => CarbonImmutable::parse('2027-10-15 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-15 10:00:00 UTC'),
        ]);

        $this->actingAs($user);

        Livewire::test(SessionsBrowser::class)
            ->assertSee('All dates')
            ->assertSee('2027-10-14')
            ->assertSee('2027-10-15')
            ->set('date', '2027-10-14')
            ->assertSee($firstDaySession->title)
            ->assertDontSee($secondDaySession->title);
    }

    public function test_sessions_browser_filters_to_current_users_sessions_and_identifies_all_assigned_mcs(): void
    {
        $user = User::factory()->create(['name' => 'Current MC']);
        $coMc = User::factory()->create(['name' => 'Co-MC']);
        $assignedSession = ConferenceSession::factory()->create(['title' => 'Assigned session']);
        $assignedSession->mcs()->attach([$user->id, $coMc->id]);
        ConferenceSession::factory()->create(['title' => 'Other session']);

        $this->actingAs($user);

        Livewire::test(SessionsBrowser::class)
            ->set('mySessions', true)
            ->assertSee('Assigned session')
            ->assertSee('Current MC')
            ->assertSee('Co-MC')
            ->assertDontSee('Other session')
            ->assertSee('Your session')
            ->call('clearFilters')
            ->assertSet('mySessions', false)
            ->assertSee('Other session');
    }

    public function test_enabled_users_can_update_mc_content_and_add_notes_with_their_own_author(): void
    {
        $user = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();

        $this->actingAs($user);

        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->set('mcDescription', 'Welcome the audience.')
            ->set('mcScript', 'Please welcome our speaker.')
            ->call('saveMcContent')
            ->assertDispatched('toast', type: 'success', message: 'Session preparation saved.')
            ->set('noteBody', 'Check the stage timer.')
            ->call('addNote')
            ->assertDispatched('toast', type: 'success', message: 'Note added.')
            ->assertDispatched('note-added');

        $this->assertSame('Welcome the audience.', $conferenceSession->fresh()->mc_description);
        $this->assertSame($user->id, $conferenceSession->fresh()->notes->sole()->user_id);

        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->assertDontSee('Edit note')
            ->assertDontSee('Delete note');
    }

    public function test_non_admin_users_can_read_notes_but_cannot_add_them(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $conferenceSession = ConferenceSession::factory()->create();

        (new AddSessionNote)->handle($admin, $conferenceSession, 'Stage microphone is on channel two.');

        $this->actingAs($user);

        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->assertSee('Stage microphone is on channel two.')
            ->assertDontSee($admin->name)
            ->assertDontSee('Add note')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_administrator_can_edit_and_delete_notes_with_toast_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $conferenceSession = ConferenceSession::factory()->create();
        $note = SessionNote::factory()->create([
            'conference_session_id' => $conferenceSession->id,
            'body' => 'Original technical note.',
        ]);

        $this->actingAs($admin);

        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->assertSee('Edit')
            ->assertSee('Delete')
            ->call('editNote', $note->id)
            ->assertSet('editingNoteId', $note->id)
            ->assertSet('editingNoteBody', 'Original technical note.')
            ->set('editingNoteBody', 'Updated technical note.')
            ->call('updateNote')
            ->assertDispatched('toast', type: 'success', message: 'Note updated.')
            ->assertSet('editingNoteId', null)
            ->assertSee('Updated technical note.')
            ->call('deleteNote', $note->id)
            ->assertDispatched('toast', type: 'success', message: 'Note deleted.');

        $this->assertDatabaseMissing('session_notes', ['id' => $note->id]);
    }

    public function test_removed_session_detail_is_restricted_to_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $removedSession = ConferenceSession::factory()->removed()->create(['title' => 'Removed detail']);

        $this->actingAs($user)->get(route('sessions.show', $removedSession))->assertForbidden();
        $this->actingAs($admin)->get(route('sessions.show', $removedSession))->assertOk()->assertSee('Removed detail');
    }

    public function test_admin_user_creation_flashes_a_success_toast(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->set('name', 'New operator')
            ->set('email', 'operator@example.com')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('User created.', session('success'));
    }

    public function test_admin_user_update_flashes_a_success_toast(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Old name']);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['user' => $user])
            ->set('name', 'Updated name')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('User updated.', session('success'));
    }

    public function test_only_administrators_receive_assignment_controls_and_disabled_users_are_not_selectable(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $enabledMc = User::factory()->create(['name' => 'Enabled MC']);
        $availableMc = User::factory()->create(['name' => 'Available MC']);
        $disabledMc = User::factory()->disabled()->create(['name' => 'Disabled MC']);
        $conferenceSession = ConferenceSession::factory()->create();
        $conferenceSession->mcs()->attach($enabledMc);

        $this->actingAs($user);
        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->assertSee($enabledMc->name)
            ->assertDontSee('Assign MC')
            ->assertDontSee('Remove');

        $this->actingAs($admin);
        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->assertSee('Assign MC')
            ->assertSee('Choose enabled user')
            ->assertSee($availableMc->name)
            ->assertDontSee('value="'.$enabledMc->id.'"', false)
            ->assertDontSee($disabledMc->name)
            ->set('assignUserId', $availableMc->id)
            ->call('assignMc')
            ->assertDispatched('toast', type: 'success', message: 'MC assigned.')
            ->assertSee('Remove');

        $this->assertTrue($conferenceSession->fresh()->mcs->contains($availableMc));
    }
}
