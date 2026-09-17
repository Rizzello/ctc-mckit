<?php

namespace App\Livewire;

use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class SessionsBrowser extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $roomId = null;

    #[Url]
    public ?string $date = null;

    #[Url]
    public bool $showRemoved = false;

    #[Url]
    public bool $mySessions = false;

    public function clearFilters(): void
    {
        $this->search = '';
        $this->roomId = null;
        $this->date = null;
        $this->showRemoved = false;
        $this->mySessions = false;
    }

    public function render(): View
    {
        $query = ConferenceSession::query()->with(['room', 'speakers', 'mcs'])->ordered();

        $currentUser = $this->currentUser();

        if ($this->showRemoved && $currentUser->is_admin) {
            $query->removed();
        } else {
            $query->active();
        }

        /** @var Collection<int, string> $dates */
        $dates = (clone $query)
            ->reorder()
            ->whereNotNull('starts_at')
            ->selectRaw('DATE(starts_at) as schedule_date')
            ->distinct()
            ->orderBy('schedule_date')
            ->pluck('schedule_date');

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->roomId !== null) {
            $query->forRoom($this->roomId);
        }

        if ($this->date !== null) {
            $query->whereDate('starts_at', $this->date);
        }

        if ($this->mySessions) {
            $query->assignedTo($currentUser);
        }

        /** @var Collection<int, ConferenceSession> $conferenceSessions */
        $conferenceSessions = $query->get();

        return view('livewire.sessions-browser', [
            'conferenceSessions' => $conferenceSessions,
            'currentUser' => $currentUser,
            'dates' => $dates,
            'rooms' => Room::query()->orderBy('name')->get(),
        ]);
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
