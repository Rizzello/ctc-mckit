<?php

namespace App\Livewire;

use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Agenda extends Component
{
    private const PIXELS_PER_MINUTE = 3;

    private const MINIMUM_SESSION_HEIGHT = 96;

    private const MINIMUM_SERVICE_SESSION_HEIGHT = 64;

    #[Url]
    public ?string $date = null;

    public function render(): View
    {
        $dates = ConferenceSession::query()
            ->active()
            ->whereNotNull('starts_at')
            ->selectRaw('DATE(starts_at) as schedule_date')
            ->distinct()
            ->orderBy('schedule_date')
            ->pluck('schedule_date');

        $selectedDate = $this->date ?? $this->defaultDate($dates);
        $this->date = $selectedDate;

        $query = ConferenceSession::query()
            ->active()
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->with(['room', 'speakers', 'mcs'])
            ->ordered();

        if ($selectedDate !== null) {
            $query->whereDate('starts_at', $selectedDate);
        }

        /** @var Collection<int, ConferenceSession> $conferenceSessions */
        $conferenceSessions = $query->get();
        $firstStart = $conferenceSessions->first()?->starts_at;
        $lastEnd = $conferenceSessions->sortByDesc('ends_at')->first()?->ends_at;
        $calendarStart = $firstStart === null ? null : CarbonImmutable::parse($firstStart)->startOfHour();
        $calendarEnd = $lastEnd === null ? null : CarbonImmutable::parse($lastEnd)->ceilHour();
        /** @var Collection<int, Room> $rooms */
        $rooms = Room::query()->orderBy('name')->get();
        $positionedSessions = $calendarStart === null
            ? collect()
            : $rooms->mapWithKeys(fn (Room $room): array => [
                $room->id => $this->positionSessions(
                    $conferenceSessions
                        ->filter(fn (ConferenceSession $conferenceSession): bool => $conferenceSession->is_plenum_session || $conferenceSession->room_id === $room->id)
                        ->values(),
                    $calendarStart,
                ),
            ]);
        $calendarHeights = [
            $calendarStart === null || $calendarEnd === null
                ? 0
                : $calendarStart->diffInMinutes($calendarEnd) * self::PIXELS_PER_MINUTE,
            ...$positionedSessions
                ->map(fn (Collection $sessions): int => $sessions->last()['bottom'] ?? 0)
                ->all(),
        ];
        $calendarHeight = max($calendarHeights);

        return view('livewire.agenda', [
            'conferenceSessions' => $conferenceSessions,
            'dates' => $dates,
            'calendarStart' => $calendarStart,
            'calendarEnd' => $calendarEnd,
            'calendarHeight' => $calendarHeight,
            'currentUser' => $this->currentUser(),
            'pixelsPerHour' => 60 * self::PIXELS_PER_MINUTE,
            'rooms' => $rooms,
            'positionedSessions' => $positionedSessions,
        ]);
    }

    /**
     * @param  Collection<int, ConferenceSession>  $conferenceSessions
     * @return Collection<int, array{conferenceSession: ConferenceSession, top: int, height: int, bottom: int}>
     */
    private function positionSessions(Collection $conferenceSessions, CarbonImmutable $calendarStart): Collection
    {
        $bottom = 0;
        $positionedSessions = [];

        foreach ($conferenceSessions as $conferenceSession) {
            $startsAt = CarbonImmutable::parse($conferenceSession->starts_at);
            $endsAt = CarbonImmutable::parse($conferenceSession->ends_at);
            $scheduledTop = intdiv($startsAt->getTimestamp() - $calendarStart->getTimestamp(), 60) * self::PIXELS_PER_MINUTE;
            $height = max(
                intdiv($endsAt->getTimestamp() - $startsAt->getTimestamp(), 60) * self::PIXELS_PER_MINUTE,
                $conferenceSession->is_service_session
                    ? self::MINIMUM_SERVICE_SESSION_HEIGHT
                    : self::MINIMUM_SESSION_HEIGHT,
            );
            $top = max($scheduledTop, $bottom);
            $bottom = $top + $height;

            $positionedSessions[] = [
                'conferenceSession' => $conferenceSession,
                'top' => $top,
                'height' => $height,
                'bottom' => $bottom,
            ];
        }

        return collect($positionedSessions);
    }

    /** @param Collection<int, string> $dates */
    private function defaultDate(Collection $dates): ?string
    {
        if ($dates->isEmpty()) {
            return null;
        }

        $today = CarbonImmutable::today()->toDateString();

        return $dates->first(fn (string $date): bool => $date >= $today) ?? $dates->last();
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
