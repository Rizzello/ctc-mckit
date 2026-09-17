<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\Speaker;
use App\Models\User;
use Database\Seeders\DemoScheduleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoScheduleSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_a_two_day_schedule_with_handover_and_keynotes(): void
    {
        $this->seed(DemoScheduleSeeder::class);

        $openingKeynote = ConferenceSession::query()->where('sessionize_id', 'demo-session-opening-keynote')->firstOrFail();
        $closingKeynote = ConferenceSession::query()->where('sessionize_id', 'demo-session-closing-keynote')->firstOrFail();
        $lunchHandover = ConferenceSession::query()->where('sessionize_id', 'demo-session-lunch-handover')->firstOrFail();

        $this->assertSame(2, User::query()->count());
        $this->assertSame(9, ConferenceSession::query()->count());
        $this->assertSame(8, Speaker::query()->count());
        $this->assertTrue($openingKeynote->is_plenum_session);
        $this->assertTrue($closingKeynote->is_plenum_session);
        $this->assertTrue($lunchHandover->is_service_session);
        $this->assertSame('Serena Conti', $openingKeynote->mcs->sole()->name);
        $this->assertSame('Matteo Riva', $closingKeynote->mcs->sole()->name);
        $this->assertCount(2, $lunchHandover->mcs);
        $this->assertSame(3, SessionNote::query()->count());
    }

    public function test_it_can_be_run_multiple_times_without_duplicate_records(): void
    {
        $this->seed(DemoScheduleSeeder::class);
        $this->seed(DemoScheduleSeeder::class);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(9, ConferenceSession::query()->count());
        $this->assertSame(8, Speaker::query()->count());
        $this->assertSame(3, SessionNote::query()->count());
    }
}
