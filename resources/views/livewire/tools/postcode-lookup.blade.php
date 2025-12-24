<div class="py-12">
    <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Postcode Lookup</flux:heading>

        <flux:card>
            <div class="mb-6">
                <flux:heading>Search UK Postcodes</flux:heading>
                <flux:subheading>Look up geography codes, coordinates, and administrative boundaries</flux:subheading>
            </div>

            <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 text-center dark:border-gray-700 dark:bg-gray-800">
                <flux:icon.wrench-screwdriver class="mx-auto h-16 w-16 text-gray-400" />
                <flux:heading size="lg" class="mt-4">Coming Soon</flux:heading>
                <flux:subheading class="mt-2">This feature requires API endpoints that haven't been built yet.</flux:subheading>

                <div class="mt-6 text-left">
                    <p class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Planned Features:</p>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Real-time postcode validation and formatting</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>WGS84 coordinates (latitude/longitude)</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>OS Grid reference (Easting/Northing)</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Administrative geography codes (Ward, LAD, Constituency, Parish, etc.)</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Search history and favorites</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Copy to clipboard functionality</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-6 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>Roadmap Reference:</strong> This feature will be enabled after implementing API endpoints (Roadmap items 4-6)
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <form wire:submit="lookup" class="flex gap-3">
                    <div class="flex-1">
                        <flux:input
                            wire:model="postcode"
                            type="text"
                            placeholder="e.g. SW1A 1AA"
                            disabled
                        />
                    </div>
                    <flux:button type="submit" icon="magnifying-glass" disabled>
                        Search
                    </flux:button>
                </form>
                <p class="mt-2 text-xs text-gray-500">Search functionality will be enabled once API endpoints are built</p>
            </div>
        </flux:card>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <flux:card>
                <flux:heading>Example Output</flux:heading>
                <flux:subheading>What you'll see when searching a postcode</flux:subheading>

                <div class="mt-4 space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Postcode</p>
                        <p class="font-mono text-sm">SW1A 1AA</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Latitude</p>
                            <p class="font-mono text-sm">51.5014</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Longitude</p>
                            <p class="font-mono text-sm">-0.1419</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Ward</p>
                        <p class="font-mono text-sm">E05013806</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Local Authority</p>
                        <p class="font-mono text-sm">E09000033</p>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading>Quick Actions</flux:heading>
                <div class="mt-4 space-y-2">
                    <flux:button href="{{ route('admin.imports') }}" variant="ghost" class="w-full justify-start">
                        <flux:icon.arrow-up-tray /> Import ONSUD Data
                    </flux:button>
                    <flux:button href="{{ route('admin.versions') }}" variant="ghost" class="w-full justify-start">
                        <flux:icon.clock /> View Data Versions
                    </flux:button>
                    <flux:button href="{{ route('tools.map') }}" variant="ghost" class="w-full justify-start">
                        <flux:icon.map /> Property Map
                    </flux:button>
                </div>
            </flux:card>
        </div>
    </div>

</div>

<!-- VERSION 2.0 - LIVEWIRE FIXED -->
<script>
    document.addEventListener('livewire:initialized', () => {
        console.log('✓ Livewire initialized event fired');
        Livewire.on('api-not-ready', () => {
            alert('API endpoints not yet implemented. See roadmap items 4-6.');
        });
    });
</script>
