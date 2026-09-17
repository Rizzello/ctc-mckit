<?php

namespace App\Livewire;

use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\Speaker;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class LiveSchedule extends Component
{
    public function render(): View
    {
        /** @var Collection<int, ConferenceSession> $conferenceSessions */
        $conferenceSessions = ConferenceSession::query()
            ->active()
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->with(['room', 'speakers', 'mcs', 'notes'])
            ->ordered()
            ->get();

        return view('livewire.live-schedule', [
            'liveSessions' => $conferenceSessions->map(fn (ConferenceSession $conferenceSession): array => [
                'title' => $conferenceSession->title,
                'startsAt' => $conferenceSession->starts_at === null ? null : CarbonImmutable::parse($conferenceSession->starts_at)->toIso8601String(),
                'endsAt' => $conferenceSession->ends_at === null ? null : CarbonImmutable::parse($conferenceSession->ends_at)->toIso8601String(),
                'room' => $conferenceSession->room?->name,
                'description' => $conferenceSession->description,
                'mcDescription' => $conferenceSession->mc_description,
                'mcScript' => $conferenceSession->mc_script,
                'speakers' => $conferenceSession->speakers->map(fn (Speaker $speaker): array => [
                    'name' => $speaker->name,
                    'tagline' => $speaker->tagline,
                    'bio' => $speaker->bio,
                    'photoUrl' => $speaker->photo_url,
                ])->all(),
                'mcs' => $conferenceSession->mcs->pluck('name')->all(),
                'notes' => $conferenceSession->notes->sortBy('created_at')->map(fn (SessionNote $note): array => [
                    'body' => $note->body,
                    'createdAt' => CarbonImmutable::parse($note->created_at)->toIso8601String(),
                ])->all(),
            ])->all(),
        ]);
    }
}
