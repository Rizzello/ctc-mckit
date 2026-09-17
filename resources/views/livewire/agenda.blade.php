<section class="space-y-5">
    <div>
        <p class="text-sm font-semibold text-sky-800">Conference overview</p>
        <h1 class="text-3xl font-bold tracking-tight">Agenda</h1>
    </div>

    @if ($dates->isNotEmpty())
        <nav aria-label="Agenda days" class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($dates as $scheduleDate)
                <button
                    wire:click="$set('date', '{{ $scheduleDate }}')"
                    type="button"
                    @class([
                        'min-h-11 shrink-0 rounded-md border px-4 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700',
                        'border-sky-800 bg-sky-800 text-white' => $date === $scheduleDate,
                        'border-slate-300 bg-white text-slate-700' => $date !== $scheduleDate,
                    ])
                >
                    {{ \Carbon\CarbonImmutable::parse($scheduleDate)->isoFormat('ddd D MMM') }}
                </button>
            @endforeach
        </nav>
    @endif

    @if ($conferenceSessions->isEmpty() || $calendarStart === null || $calendarEnd === null)
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
            <h2 class="font-semibold">No schedule imported yet</h2>
            <p class="mt-1 text-sm text-slate-600">Sessions will appear here once the event agenda is available.</p>
        </div>
    @else
        <div class="-mx-4 overflow-x-auto overflow-y-hidden">
            <div class="grid min-w-[42rem]" style="grid-template-columns: 4.5rem repeat({{ $rooms->count() }}, minmax(15rem, 1fr));">
                <div class="sticky left-0 z-10 border-b border-slate-200 bg-slate-50 p-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                    Time
                </div>

                @foreach ($rooms as $room)
                    <div class="border-b border-l border-slate-200 bg-slate-50 p-3 text-sm font-bold">{{ $room->name }}</div>
                @endforeach

                @php($hourCount = $calendarStart->diffInHours($calendarEnd))

                <div
                    class="relative col-span-full grid"
                    style="grid-template-columns: 4.5rem repeat({{ $rooms->count() }}, minmax(15rem, 1fr)); height: {{ $calendarHeight }}px;"
                >
                    <div class="sticky left-0 z-10 border-r border-slate-200 bg-white">
                        @for ($hour = 0; $hour < $hourCount; $hour++)
                            <div
                                class="absolute w-full px-3 text-xs font-semibold text-slate-500"
                                style="top: {{ $hour * 120 }}px;"
                            >
                                {{ $calendarStart->addHours($hour)->format('H:i') }}
                            </div>
                        @endfor
                    </div>

                    @foreach ($rooms as $room)
                        <div class="relative border-l border-slate-200" aria-label="{{ $room->name }} schedule">
                            @for ($hour = 0; $hour <= $hourCount; $hour++)
                                <div class="absolute inset-x-0 border-t border-slate-100" style="top: {{ $hour * 120 }}px;"></div>
                            @endfor

                            @foreach ($positionedSessions->get($room->id, collect()) as $positionedSession)
                                @php($conferenceSession = $positionedSession['conferenceSession'])
                                @php($isAssigned = $conferenceSession->mcs->contains('id', $currentUser->id))

                                <a
                                    href="{{ route('sessions.show', $conferenceSession) }}"
                                    wire:navigate
                                    @class([
                                        'absolute inset-x-1 rounded-md border p-2 text-left text-xs shadow-sm focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700',
                                        'border-sky-500 bg-sky-100 text-sky-950' => $isAssigned,
                                        'border-slate-300 bg-white text-slate-900' => ! $isAssigned,
                                    ])
                                    style="top: {{ $positionedSession['top'] }}px; height: {{ $positionedSession['height'] }}px;"
                                >
                                    <span class="block font-bold">
                                        {{ $conferenceSession->starts_at->format('H:i') }}–{{ $conferenceSession->ends_at->format('H:i') }}
                                        · {{ $conferenceSession->starts_at->diffInMinutes($conferenceSession->ends_at) }} min
                                    </span>
                                    <span class="mt-1 block break-words font-semibold">{{ $conferenceSession->title }}</span>
                                    <span class="mt-1 block break-words text-slate-700">{{ $conferenceSession->speakers->pluck('name')->join(', ') }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <p class="text-sm text-slate-700">
            <span class="inline-block size-3 rounded-sm bg-sky-200 align-middle"></span>
            Your assigned sessions
        </p>
    @endif
</section>
