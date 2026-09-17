<section class="space-y-5">
    <div><p class="text-sm font-semibold text-sky-800">Preparation</p><h1 class="text-3xl font-bold tracking-tight">Sessions</h1></div>
    <div class="grid gap-3 sm:grid-cols-3">
        <label class="grid gap-1 text-sm font-semibold" for="search">Search<input id="search" wire:model.live.debounce.300ms="search" class="min-h-11 rounded-md border border-slate-300 bg-white px-3 focus-visible:outline-2 focus-visible:outline-sky-700" placeholder="Title or speaker"></label>
        <label class="grid gap-1 text-sm font-semibold" for="browser-room">Room<select id="browser-room" wire:model.live="roomId" class="min-h-11 rounded-md border border-slate-300 bg-white px-3"><option value="">All rooms</option>@foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-sm font-semibold" for="browser-date">Date<select id="browser-date" wire:model.live="date" class="min-h-11 rounded-md border border-slate-300 bg-white px-3"><option value="">All dates</option>@foreach ($dates as $scheduleDate)<option value="{{ $scheduleDate }}">{{ \Carbon\CarbonImmutable::parse($scheduleDate)->isoFormat('ddd D MMM') }}</option>@endforeach</select></label>
    </div>
    <div class="flex flex-wrap gap-3"><label class="flex min-h-11 items-center gap-3 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold"><input wire:model.live="mySessions" type="checkbox" class="size-5 accent-sky-700"> My sessions only</label>@if ($currentUser->is_admin)<label class="flex min-h-11 items-center gap-3 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold"><input wire:model.live="showRemoved" type="checkbox" class="size-5 accent-sky-700"> Show removed sessions</label>@endif @if ($search !== '' || $roomId !== null || $date !== null || $showRemoved || $mySessions)<button wire:click="clearFilters" type="button" class="min-h-11 rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Clear filters</button>@endif</div>
    <div class="space-y-3">
        @forelse ($conferenceSessions as $conferenceSession)
            @php($isAssigned = $conferenceSession->mcs->contains('id', $currentUser->id))
            <a href="{{ route('sessions.show', $conferenceSession) }}" wire:navigate @class(['block rounded-xl border p-4 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700', 'border-sky-500 bg-sky-50' => $isAssigned, 'border-slate-200 bg-white' => ! $isAssigned])>
                <p class="text-sm font-semibold text-slate-700">{{ $conferenceSession->starts_at?->format('D H:i') }} · {{ $conferenceSession->room?->name ?? 'Room to be confirmed' }}</p>
                <h2 class="mt-1 text-lg font-bold">{{ $conferenceSession->title }}</h2>
                <p class="mt-2 text-sm">{{ $conferenceSession->speakers->pluck('name')->join(', ') ?: 'No speakers listed' }}</p>
                <p class="mt-1 text-sm text-slate-700">MC: {{ $conferenceSession->mcs->pluck('name')->join(', ') ?: 'No MC assigned' }}</p>
                @if ($isAssigned)<p class="mt-2 text-xs font-bold uppercase tracking-wide text-sky-800">Your session</p>@endif
                @if ($conferenceSession->sessionize_status === \App\Enums\SessionizePresenceStatus::Removed)<p class="mt-2 text-xs font-bold uppercase text-amber-800">Removed from source schedule</p>@endif
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center"><h2 class="font-semibold">No sessions match your search</h2><p class="mt-1 text-sm text-slate-600">Try a different title, speaker, date, room, or assignment filter.</p></div>
        @endforelse
    </div>
</section>
