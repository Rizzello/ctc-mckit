<section class="space-y-6">
    <a href="{{ route('sessions.index') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-semibold text-sky-800">← Sessions</a>

    @if ($conferenceSession->sessionize_status === \App\Enums\SessionizePresenceStatus::Removed)
        <p class="rounded-md border border-amber-300 bg-amber-50 p-3 font-medium text-amber-900">This session was removed from the source schedule.</p>
    @endif

    <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm font-semibold text-sky-800">Imported schedule</p>
        <h1 class="text-2xl font-bold">{{ $conferenceSession->title }}</h1>
        <p class="text-sm font-semibold">{{ $conferenceSession->starts_at?->format('l, D M · H:i') }}–{{ $conferenceSession->ends_at?->format('H:i') }} · {{ $conferenceSession->room?->name ?? 'Room to be confirmed' }}</p>
        <p class="text-sm">{{ collect($conferenceSession->categories)->join(' · ') }}</p>
        <details>
            <summary class="min-h-11 cursor-pointer py-2 font-semibold text-sky-800">About this session</summary>
            <p class="whitespace-pre-line text-slate-700">{{ $conferenceSession->description }}</p>
        </details>
        <div class="space-y-3">
            @forelse ($conferenceSession->speakers as $speaker)
                <article class="rounded-md bg-slate-50 p-3">
                    <div class="flex gap-3">
                        @if ($speaker->photo_url)
                            <img src="{{ $speaker->photo_url }}" alt="" class="size-14 rounded-full object-cover">
                        @endif
                        <div>
                            <h2 class="font-bold">{{ $speaker->name }}</h2>
                            <p class="text-sm font-medium text-slate-700">{{ $speaker->tagline }}</p>
                        </div>
                    </div>
                    @if ($speaker->bio)
                        <details class="mt-2">
                            <summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-sky-800">Speaker bio</summary>
                            <p class="whitespace-pre-line text-sm text-slate-700">{{ $speaker->bio }}</p>
                        </details>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-600">No speakers listed.</p>
            @endforelse
        </div>
    </section>

    <section class="space-y-3 rounded-xl border border-sky-200 bg-sky-50 p-4">
        <h2 class="text-xl font-bold">Host preparation</h2>
        <form wire:submit="saveMcContent" class="space-y-3">
            <label class="grid gap-1 text-sm font-semibold" for="mc-description">
                Host briefing
                <textarea id="mc-description" wire:model="mcDescription" rows="4" class="rounded-md border border-slate-300 bg-white p-3"></textarea>
            </label>
            @error('mc_description')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <label class="grid gap-1 text-sm font-semibold" for="mc-script">
                Suggested wording
                <textarea id="mc-script" wire:model="mcScript" rows="8" class="rounded-md border border-slate-300 bg-white p-3"></textarea>
            </label>
            @error('mc_script')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <button type="submit" class="min-h-11 rounded-md bg-sky-800 px-4 font-semibold text-white">Save preparation</button>
        </form>
    </section>

    <section x-data x-on:mc-assigned.window="$refs.mcDialog.close()" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold">Assigned MCs</h2>
                <p class="mt-1 text-sm text-slate-600">People responsible for this session.</p>
            </div>
            @can('assignMc', [$conferenceSession, auth()->user()])
                <button x-on:click="$refs.mcDialog.showModal()" type="button" class="min-h-11 shrink-0 rounded-md bg-slate-900 px-4 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Assign MC</button>
            @endcan
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($conferenceSession->mcs as $mc)
                <article class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="font-semibold">{{ $mc->name }}</p>
                    @can('unassignMc', [$conferenceSession, $mc])
                        <button wire:click="unassignMc({{ $mc->id }})" type="button" class="min-h-11 shrink-0 rounded-md px-3 text-sm font-semibold text-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Remove</button>
                    @endcan
                </article>
            @empty
                <p class="text-sm text-slate-600">No MC assigned.</p>
            @endforelse
        </div>

        @can('assignMc', [$conferenceSession, auth()->user()])
            <dialog x-ref="mcDialog" x-on:click.self="$refs.mcDialog.close()" x-on:cancel="$event.preventDefault(); $refs.mcDialog.close()" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-slate-300 bg-white p-0 shadow-2xl">
                <div class="space-y-5 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-sky-800">Session staffing</p>
                            <h3 class="text-xl font-bold">Assign MC</h3>
                        </div>
                        <button x-on:click="$refs.mcDialog.close()" type="button" aria-label="Close MC management" title="Close" class="grid size-11 place-items-center rounded-md text-2xl text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">×</button>
                    </div>

                    <form wire:submit="assignMc" class="flex gap-2">
                        <label class="sr-only" for="assign-user">Assign MC</label>
                        <select id="assign-user" wire:model="assignUserId" class="min-h-11 grow rounded-md border border-slate-300 bg-white px-3">
                            <option value="">Choose enabled user</option>
                            @foreach ($assignableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="min-h-11 rounded-md bg-slate-900 px-4 font-semibold text-white">Assign</button>
                    </form>
                    @error('assignUserId')
                        <p class="text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    @error('user')
                        <p class="text-sm text-red-700">{{ $message }}</p>
                    @enderror

                </div>
            </dialog>
        @endcan
    </section>

    <section x-data x-on:note-added.window="$refs.noteDialog.close()" x-on:note-updated.window="$refs.editNoteDialog.close()" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-sky-800">Shared technical notes</p>
                <h2 class="text-xl font-bold">Notes</h2>
                <p class="mt-1 text-sm text-slate-600">Keep practical session details visible to everyone working on the event.</p>
            </div>
            @can('create', \App\Models\SessionNote::class)
                <button x-on:click="$refs.noteDialog.showModal()" type="button" class="min-h-11 shrink-0 rounded-md bg-slate-900 px-4 font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Add note</button>
            @endcan
        </div>

        @can('create', \App\Models\SessionNote::class)
            <dialog x-ref="noteDialog" x-on:click.self="$refs.noteDialog.close()" x-on:cancel="$event.preventDefault(); $refs.noteDialog.close()" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-slate-300 bg-white p-0 shadow-2xl">
                <form wire:submit="addNote" class="space-y-5 p-5">
                <div class="sticky top-0 flex items-center justify-between gap-3 bg-white pb-3">
                    <div>
                        <p class="text-sm font-semibold text-sky-800">Technical session note</p>
                        <h3 class="text-xl font-bold">Add note</h3>
                    </div>
                    <button x-on:click="$refs.noteDialog.close()" type="button" aria-label="Close note form" title="Close" class="grid size-11 place-items-center rounded-md text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                    </button>
                </div>

                <label class="grid gap-2 text-sm font-semibold" for="note-body">
                    Note
                    <textarea id="note-body" wire:model="noteBody" rows="7" aria-describedby="note-body-error" class="min-h-36 rounded-md border border-slate-300 p-3 text-base leading-relaxed focus-visible:outline-2 focus-visible:outline-sky-700" placeholder="Add a technical detail for this session."></textarea>
                </label>
                @error('body')
                    <p id="note-body-error" class="text-sm text-red-700">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-3">
                    <button x-on:click="$refs.noteDialog.close()" type="button" class="min-h-11 rounded-md px-4 font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-md bg-sky-800 px-4 font-semibold text-white data-loading:cursor-wait data-loading:opacity-60">Save note</button>
                </div>
                </form>
            </dialog>
        @endcan

        <div class="space-y-3">
            @forelse ($conferenceSession->notes->sortBy('created_at') as $note)
                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm text-slate-600">{{ $note->created_at->format('D M · H:i') }}</p>
                        @can('update', $note)
                            <div class="flex shrink-0 gap-2">
                                <button wire:click="editNote({{ $note->id }})" type="button" class="min-h-11 rounded-md px-3 text-sm font-semibold text-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Edit</button>
                                @can('delete', $note)
                                    <button wire:click="deleteNote({{ $note->id }})" wire:confirm="Delete this note?" type="button" class="min-h-11 rounded-md px-3 text-sm font-semibold text-red-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Delete</button>
                                @endcan
                            </div>
                        @endcan
                    </div>
                    <p class="mt-3 whitespace-pre-line leading-relaxed text-slate-800">{{ $note->body }}</p>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5 text-center">
                    <p class="font-semibold">No notes yet</p>
                    <p class="mt-1 text-sm text-slate-600">Add a practical detail, such as a stage, equipment, or timing requirement.</p>
                </div>
            @endforelse
        </div>

        @can('update', $conferenceSession->notes->first() ?? new \App\Models\SessionNote)
            @if ($editingNoteId !== null)
                <dialog x-ref="editNoteDialog" x-init="$nextTick(() => { if (!$el.open) $el.showModal(); })" x-on:click.self="$refs.editNoteDialog.close()" x-on:cancel="$event.preventDefault(); $refs.editNoteDialog.close()" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border border-slate-300 bg-white p-0 shadow-2xl">
                    <form wire:submit="updateNote" class="space-y-5 p-5">
                        <div class="sticky top-0 flex items-center justify-between gap-3 bg-white pb-3">
                            <div>
                                <p class="text-sm font-semibold text-sky-800">Technical session note</p>
                                <h3 class="text-xl font-bold">Edit note</h3>
                            </div>
                            <button x-on:click="$refs.editNoteDialog.close()" type="button" aria-label="Close note form" title="Close" class="grid size-11 place-items-center rounded-md text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                            </button>
                        </div>
                        <label class="grid gap-2 text-sm font-semibold" for="edit-note-body">
                            Note
                            <textarea id="edit-note-body" wire:model="editingNoteBody" rows="7" aria-describedby="edit-note-body-error" class="min-h-36 rounded-md border border-slate-300 p-3 text-base leading-relaxed focus-visible:outline-2 focus-visible:outline-sky-700"></textarea>
                        </label>
                        @error('editingNoteBody')
                            <p id="edit-note-body-error" class="text-sm text-red-700">{{ $message }}</p>
                        @enderror
                        <div class="flex justify-end gap-3">
                            <button x-on:click="$refs.editNoteDialog.close()" type="button" class="min-h-11 rounded-md px-4 font-semibold text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Cancel</button>
                            <button type="submit" class="min-h-11 rounded-md bg-sky-800 px-4 font-semibold text-white data-loading:cursor-wait data-loading:opacity-60">Save note</button>
                        </div>
                    </form>
                </dialog>
            @endif
        @endcan

    </section>
</section>
