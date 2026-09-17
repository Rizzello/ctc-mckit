<section x-data="liveReader(@js($liveSessions), @js($timezone))" x-init="start()" class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-sky-800">Read-only presenter mode</p>
            <h1 class="text-3xl font-bold tracking-tight">Live</h1>
        </div>
        <p x-text="stateLabel" class="rounded-full bg-slate-200 px-3 py-1 text-xs font-bold uppercase tracking-wide"></p>
    </div>

    <template x-if="sessions.length === 0">
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
            <h2 class="font-semibold">No sessions assigned to you</h2>
            <p class="mt-1 text-sm text-slate-600">Live mode will be ready when an administrator assigns a session to you.</p>
        </div>
    </template>

    <template x-if="session">
        <div class="space-y-5">
            <div class="flex gap-3">
                <button x-on:click="previous()" x-bind:disabled="index === 0" type="button" class="min-h-11 flex-1 rounded-md border border-slate-300 bg-white px-4 font-semibold disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
                <button x-on:click="next()" x-bind:disabled="index === sessions.length - 1" type="button" class="min-h-11 flex-1 rounded-md bg-sky-800 px-4 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Next</button>
            </div>

            <article class="space-y-4 rounded-xl border border-sky-300 bg-white p-4 shadow-sm">
                <div>
                    <p x-text="timeRange" class="text-sm font-bold text-sky-800"></p>
                    <p x-text="session.room || 'Room to be confirmed'" class="mt-1 text-sm font-semibold text-slate-700"></p>
                    <h2 x-text="session.title" class="mt-3 text-2xl font-bold tracking-tight"></h2>
                    <p class="mt-2 text-sm text-slate-700">MC: <span x-text="session.mcs.join(', ') || 'No MC assigned'"></span></p>
                </div>

                <div class="space-y-3">
                    <template x-for="speaker in session.speakers" :key="speaker.name">
                        <article class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="flex gap-3">
                                <img x-show="speaker.photoUrl" x-bind:src="speaker.photoUrl" alt="" class="size-14 rounded-full object-cover">
                                <div>
                                    <h3 x-text="speaker.name" class="font-bold"></h3>
                                    <p x-text="speaker.tagline" class="text-sm text-slate-700"></p>
                                </div>
                            </div>
                            <details x-show="speaker.bio" class="mt-3">
                                <summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-sky-800">Speaker bio</summary>
                                <p x-text="speaker.bio" class="whitespace-pre-line text-sm text-slate-700"></p>
                            </details>
                        </article>
                    </template>
                </div>

                <section x-show="session.mcDescription || session.mcScript" class="space-y-3 rounded-lg bg-sky-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold">Host preparation</h3>
                        <button x-on:click="$refs.preparationDialog.showModal()" type="button" aria-label="Expand host preparation" title="Expand host preparation" class="grid size-11 place-items-center rounded-md text-sky-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5"><circle cx="11" cy="11" r="6"></circle><path d="m16 16 4 4"></path><path d="M11 8v6M8 11h6"></path></svg>
                        </button>
                    </div>
                    <template x-if="session.mcDescription">
                        <div>
                            <h4 class="font-semibold">Host briefing</h4>
                            <p x-text="session.mcDescription" class="mt-1 whitespace-pre-line text-slate-800"></p>
                        </div>
                    </template>
                    <template x-if="session.mcScript">
                        <div>
                            <h4 class="font-semibold">Suggested wording</h4>
                            <p x-text="session.mcScript" class="mt-1 whitespace-pre-line text-slate-800"></p>
                        </div>
                    </template>

                    <dialog x-ref="preparationDialog" class="fixed inset-0 m-0 h-dvh max-h-none w-screen max-w-none overflow-y-auto border-0 bg-white p-0 text-slate-950 shadow-none">
                        <section class="mx-auto max-w-3xl space-y-5 p-5 text-lg leading-relaxed">
                            <div class="sticky top-0 flex items-center justify-between gap-3 bg-white pb-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-sky-800">Host preparation</p>
                                    <h2 x-text="session.title" class="truncate text-2xl font-bold"></h2>
                                    <p x-text="session.speakers.map((speaker) => speaker.name).join(', ') || 'No speakers listed'" class="truncate text-base text-slate-700"></p>
                                </div>
                                <button x-on:click="$refs.preparationDialog.close()" type="button" aria-label="Close host preparation" title="Close" class="grid size-11 place-items-center rounded-md text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path d="m6 6 12 12M18 6 6 18"></path></svg>
                                </button>
                            </div>
                            <template x-if="session.mcDescription">
                                <div>
                                    <h3 class="font-bold">Host briefing</h3>
                                    <p x-text="session.mcDescription" class="mt-2 whitespace-pre-line text-slate-800"></p>
                                </div>
                            </template>
                            <template x-if="session.mcScript">
                                <div>
                                    <h3 class="font-bold">Suggested wording</h3>
                                    <p x-text="session.mcScript" class="mt-2 whitespace-pre-line text-slate-800"></p>
                                </div>
                            </template>
                        </section>
                    </dialog>
                </section>

                <section class="space-y-3">
                    <h3 class="text-lg font-bold">Notes</h3>
                    <template x-if="session.notes.length === 0"><p class="text-sm text-slate-600">No notes yet.</p></template>
                    <template x-for="note in session.notes" :key="note.createdAt">
                        <article class="border-l-4 border-slate-300 pl-3">
                            <p x-text="note.body" class="whitespace-pre-line"></p>
                            <p class="mt-1 text-sm text-slate-600"><time x-text="formatDate(note.createdAt)"></time></p>
                        </article>
                    </template>
                </section>

                <details class="border-t border-slate-200 pt-3">
                    <summary class="min-h-11 cursor-pointer py-2 font-semibold text-sky-800">About this session</summary>
                    <p x-text="session.description" class="whitespace-pre-line text-slate-700"></p>
                </details>
            </article>
        </div>
    </template>
</section>
