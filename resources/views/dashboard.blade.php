<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <flux:heading size="xl" class="mb-6">Dashboard</flux:heading>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <flux:card>
                    <flux:heading>Welcome to LocaleLogic</flux:heading>
                    <flux:subheading>UK Geography Microservice</flux:subheading>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        Access powerful UK geography data including postcode lookups, property coordinates, and boundary visualization.
                    </p>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ number_format(\App\Models\Property::count()) }}</flux:heading>
                            <flux:subheading>Properties</flux:subheading>
                        </div>
                        <flux:icon.home class="size-12 text-blue-500" />
                    </div>
                </flux:card>

                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            @php
                                $currentVersion = \App\Models\DataVersion::where('dataset', 'ONSUD')
                                    ->where('status', 'current')
                                    ->first();
                            @endphp
                            <flux:heading size="lg">{{ $currentVersion ? 'Epoch ' . $currentVersion->epoch : 'N/A' }}</flux:heading>
                            <flux:subheading>Current ONSUD</flux:subheading>
                        </div>
                        <flux:icon.circle-stack class="size-12 text-green-500" />
                    </div>
                </flux:card>
            </div>

            <div class="mt-8">
                <flux:card>
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 flex flex-wrap gap-4">
                        <flux:button href="{{ route('admin.import') }}" icon="arrow-up-tray">Import ONSUD Data</flux:button>
                        <flux:button href="{{ route('tools.lookup') }}" icon="magnifying-glass" variant="ghost">Lookup Postcode</flux:button>
                        <flux:button href="{{ route('admin.versions') }}" icon="clock" variant="ghost">View History</flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>
</x-app-layout>
