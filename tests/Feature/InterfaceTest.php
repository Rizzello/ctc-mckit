<?php

namespace Tests\Feature;

use App\Actions\AddSessionNote;
use App\Livewire\Agenda;
use App\Livewire\LiveSchedule;
use App\Livewire\SessionDetail;
use App\Livewire\SessionsBrowser;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_enabled_users_can_reach_live_and_normal_users_do_not_see_admin_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('live'))
            ->assertOk()
            ->assertSee('Live')
            ->assertDontSee('>Admin<', false);
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

    public function test_agenda_expands_short_sessions_without_overlapping_the_next_session(): void
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
            ->assertSee('top: 116px;', false)
            ->assertSee('height: 112px;', false);
    }

    public function test_admin_users_see_admin_navigation_and_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('live'))->assertOk()->assertSee('>Admin<', false);
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
        ConferenceSession::factory()->create(['title' => 'Active session']);
        ConferenceSession::factory()->removed()->create(['title' => 'Removed session']);

        $this->actingAs($user);

        Livewire::test(LiveSchedule::class)->assertSee('Active session')->assertDontSee('Removed session');
    }

    public function test_live_reader_excludes_sessions_without_complete_scheduling_times(): void
    {
        $user = User::factory()->create();
        ConferenceSession::factory()->create(['title' => 'Scheduled session']);
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
            ->assertSee('MC content saved.')
            ->set('noteBody', 'Check the stage timer.')
            ->call('addNote')
            ->assertSee('Note added.')
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
            ->assertDontSee('Add note');
    }

    public function test_only_administrators_receive_assignment_controls_and_disabled_users_are_not_selectable(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $enabledMc = User::factory()->create(['name' => 'Enabled MC']);
        $disabledMc = User::factory()->disabled()->create(['name' => 'Disabled MC']);
        $conferenceSession = ConferenceSession::factory()->create();

        $this->actingAs($user);
        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])->assertDontSee('Choose enabled user');

        $this->actingAs($admin);
        Livewire::test(SessionDetail::class, ['conferenceSession' => $conferenceSession])
            ->assertSee('Choose enabled user')
            ->assertSee($enabledMc->name)
            ->assertDontSee($disabledMc->name)
            ->set('assignUserId', $enabledMc->id)
            ->call('assignMc')
            ->assertSee('MC assigned.');

        $this->assertTrue($conferenceSession->fresh()->mcs->contains($enabledMc));
    }
}
