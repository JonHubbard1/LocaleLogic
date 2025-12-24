<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'LocaleLogic' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <flux:sidebar sticky stashable class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:brand href="/" class="px-2 dark:hidden">
            <div class="flex items-center gap-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white font-bold text-lg">L</div>
                <div class="font-semibold text-lg">LocaleLogic</div>
            </div>
        </flux:brand>

        <flux:brand href="/" class="px-2 hidden dark:flex">
            <div class="flex items-center gap-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white font-bold text-lg">L</div>
                <div class="font-semibold text-lg text-white">LocaleLogic</div>
            </div>
        </flux:brand>

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" href="{{ route('dashboard') }}">Dashboard</flux:navlist.item>

            <flux:navlist.group expandable heading="Admin" icon="cog-6-tooth">
                <flux:navlist.item href="{{ route('admin.imports') }}">ONSUD Imports</flux:navlist.item>
                <flux:navlist.item href="{{ route('admin.versions') }}">Data Versions</flux:navlist.item>
                <flux:navlist.item href="{{ route('admin.cleanup') }}">System Cleanup</flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group expandable heading="Tools" icon="wrench-screwdriver">
                <flux:navlist.item href="{{ route('tools.lookup') }}">Postcode Lookup</flux:navlist.item>
                <flux:navlist.item href="{{ route('tools.map') }}">Property Map</flux:navlist.item>
                <flux:navlist.item href="{{ route('tools.boundaries') }}">Boundary Viewer</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="arrow-right-start-on-rectangle" wire:click="logout">Logout</flux:navlist.item>
        </flux:navlist>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @fluxScripts
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('logout', () => {
                fetch('{{ route("logout") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                }).then(() => {
                    window.location.href = '{{ route("login") }}';
                });
            });
        });
    </script>
</body>
</html>
