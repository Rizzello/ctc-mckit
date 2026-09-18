<section class="max-w-xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-sky-800">Administration</p>
        <h1 class="text-3xl font-bold">Edit user</h1>
    </div>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
        <label class="grid gap-1 font-semibold" for="name">Name<input id="name" wire:model="name" x-bind:readonly="$store.connectivity.offline" class="min-h-11 rounded-md border border-slate-300 px-3"></label>
        @error('name')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <label class="grid gap-1 font-semibold" for="email">Email<input id="email" wire:model="email" x-bind:readonly="$store.connectivity.offline" type="email" class="min-h-11 rounded-md border border-slate-300 px-3"></label>
        @error('email')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <label class="flex min-h-11 items-center gap-3"><input wire:model="isAdmin" x-bind:disabled="$store.connectivity.offline" type="checkbox" class="size-5 accent-sky-700"> Administrator</label>
        <label class="flex min-h-11 items-center gap-3"><input wire:model="enabled" x-bind:disabled="$store.connectivity.offline" type="checkbox" class="size-5 accent-sky-700"> Enabled</label>
        @error('user')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="flex flex-wrap gap-3">
            <button type="submit" x-bind:disabled="$store.connectivity.offline" class="min-h-11 rounded-md bg-sky-800 px-4 font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300">Save changes</button>
            @if ($user->enabled)<button wire:click="disable" x-bind:disabled="$store.connectivity.offline" type="button" class="min-h-11 rounded-md border border-red-700 px-4 font-semibold text-red-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:text-slate-400">Disable user</button>@endif
        </div>
    </form>
</section>
