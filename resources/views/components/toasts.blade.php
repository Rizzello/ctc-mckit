@php
    $flashToasts = collect(['success', 'error', 'info'])
        ->map(fn (string $type): array => ['type' => $type, 'message' => session($type)])
        ->filter(fn (array $toast): bool => is_string($toast['message']) && $toast['message'] !== '')
        ->values()
        ->all();
@endphp

<div
    x-data="toastCenter(@js($flashToasts))"
    x-init="init()"
    x-on:toast.window="push($event.detail)"
    aria-live="polite"
    aria-atomic="true"
    class="pointer-events-none fixed left-4 top-20 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3"
>
    <template x-for="toast in toasts" :key="toast.id">
        <button
            x-on:pointerdown="startPointer($event)"
            x-on:pointerup="endPointer($event, toast.id)"
            x-on:click="dismiss(toast.id)"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-1 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-1 opacity-0"
            type="button"
            class="pointer-events-auto w-full rounded-lg border p-4 text-left text-sm font-semibold shadow-lg transition duration-200 motion-reduce:transition-none"
            :class="{
                'border-emerald-300 bg-emerald-50 text-emerald-950': toast.type === 'success',
                'border-red-300 bg-red-50 text-red-950': toast.type === 'error',
                'border-sky-300 bg-sky-50 text-sky-950': toast.type === 'info',
            }"
            x-text="toast.message"
        ></button>
    </template>
</div>
