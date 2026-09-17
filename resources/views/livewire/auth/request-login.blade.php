<section class="w-full max-w-sm space-y-8 rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl sm:p-8">
    <div class="space-y-3 text-center">
        <p class="text-sm font-bold uppercase tracking-[0.2em] text-sky-300">MC Kit</p>
        <h1 class="text-3xl font-bold tracking-tight">Sign in</h1>
        <p class="text-sm leading-6 text-slate-300">We will email you a sign-in link and a 6-digit code.</p>
    </div>

    <form wire:submit="send" class="space-y-5">
        <label class="grid gap-2 text-sm font-semibold" for="email">
            Email address
            <input id="email" wire:model="email" type="email" autocomplete="email" inputmode="email" class="min-h-11 rounded-md border border-slate-600 bg-white px-3 text-base text-slate-950 outline-none focus-visible:ring-2 focus-visible:ring-sky-300" required autofocus>
        </label>
        @error('email')
            <p class="text-sm font-medium text-red-300">{{ $message }}</p>
        @enderror
        <button type="submit" class="min-h-11 w-full rounded-md bg-sky-400 px-4 font-semibold text-slate-950 transition hover:bg-sky-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-200 disabled:cursor-wait disabled:opacity-60" wire:loading.attr="disabled">
            Send login link and code
        </button>
    </form>
</section>
