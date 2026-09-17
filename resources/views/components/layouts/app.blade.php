<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'MC Kit') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
        <header x-data="{ menuOpen: false }" x-on:keydown.escape.window="menuOpen = false" class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="mx-auto grid h-14 max-w-5xl grid-cols-[1fr_auto_1fr] items-center px-4">
                <a href="{{ route('agenda') }}" wire:navigate class="min-h-11 rounded-md px-1 py-3 text-sm font-bold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">MC Kit</a>
                <p class="truncate px-3 text-sm font-semibold text-slate-700">{{ request()->routeIs('admin.users.*') ? 'Admin / Users' : (request()->routeIs('admin.sessionize') ? 'Admin / Sessionize' : (request()->routeIs('sessions.show') ? 'Sessions / Detail' : (request()->routeIs('sessions.*') ? 'Sessions' : (request()->routeIs('live') ? 'Live' : 'Agenda')))) }}</p>
                <button x-on:click="menuOpen = true" x-bind:aria-expanded="menuOpen" type="button" aria-label="Open menu" aria-controls="primary-menu" class="ml-auto grid size-11 place-items-center rounded-md text-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"><span aria-hidden="true" class="text-2xl leading-none">☰</span></button>
            </div>
            <div x-cloak x-show="menuOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/30" aria-hidden="true" x-on:click="menuOpen = false"></div>
            <nav id="primary-menu" x-cloak x-show="menuOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" aria-label="Primary navigation" class="fixed inset-y-0 right-0 z-50 flex w-[min(20rem,calc(100%-2rem))] flex-col bg-white p-4 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3"><p class="font-bold">MC Kit</p><button x-on:click="menuOpen = false" type="button" aria-label="Close menu" class="grid size-11 place-items-center rounded-md text-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">×</button></div>
                <div class="mt-4 grid gap-1">
                    <a href="{{ route('agenda') }}" wire:navigate x-on:click="menuOpen = false" aria-current="{{ request()->routeIs('agenda', 'schedule') ? 'page' : 'false' }}" @class(['min-h-11 rounded-md px-4 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700', 'bg-sky-100 text-sky-950' => request()->routeIs('agenda', 'schedule'), 'text-slate-700 hover:bg-slate-100' => !request()->routeIs('agenda', 'schedule')])>Agenda</a>
                    <a href="{{ route('sessions.index') }}" wire:navigate x-on:click="menuOpen = false" aria-current="{{ request()->routeIs('sessions.*') ? 'page' : 'false' }}" @class(['min-h-11 rounded-md px-4 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700', 'bg-sky-100 text-sky-950' => request()->routeIs('sessions.*'), 'text-slate-700 hover:bg-slate-100' => !request()->routeIs('sessions.*')])>Sessions</a>
                    <a href="{{ route('live') }}" wire:navigate x-on:click="menuOpen = false" aria-current="{{ request()->routeIs('live') ? 'page' : 'false' }}" @class(['min-h-11 rounded-md px-4 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700', 'bg-sky-100 text-sky-950' => request()->routeIs('live'), 'text-slate-700 hover:bg-slate-100' => !request()->routeIs('live')])>Live</a>
                    @can('viewAny', App\Models\User::class)<a href="{{ route('admin.users.index') }}" wire:navigate x-on:click="menuOpen = false" aria-current="{{ request()->routeIs('admin.*') ? 'page' : 'false' }}" @class(['min-h-11 rounded-md px-4 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700', 'bg-sky-100 text-sky-950' => request()->routeIs('admin.*'), 'text-slate-700 hover:bg-slate-100' => !request()->routeIs('admin.*')])>Admin</a>@endcan
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-auto border-t border-slate-200 pt-4">@csrf<button type="submit" class="min-h-11 w-full rounded-md border border-slate-300 px-4 text-left text-sm font-semibold text-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700">Log out</button></form>
            </nav>
        </header>
        <main class="mx-auto max-w-5xl px-4 py-6">{{ $slot }}</main>
        @livewireScripts
    </body>
</html>
