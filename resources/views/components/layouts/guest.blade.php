<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'MC Kit') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="grid min-h-screen place-items-center bg-slate-950 p-6 text-white antialiased">
        {{ $slot }}
        <x-toasts />
        @livewireScripts
    </body>
</html>
