<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Boundary Viewer</flux:heading>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <flux:card>
                    <div class="mb-6">
                        <flux:heading>Administrative Boundary Visualization</flux:heading>
                        <flux:subheading>View GeoJSON boundary polygons for UK administrative geographies</flux:subheading>
                    </div>

                    <div class="aspect-video rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <flux:icon.globe-alt class="h-20 w-20 text-gray-400" />
                            <flux:heading size="lg" class="mt-4">GeoJSON Viewer Coming Soon</flux:heading>
                            <flux:subheading class="mt-2">
                                Interactive boundary visualization will be enabled once boundary API endpoints are built
                            </flux:subheading>

                            <div class="mt-6 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <strong>Roadmap Reference:</strong> Requires boundary API endpoint (Roadmap item 8)
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <form wire:submit="loadBoundary" class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:field>
                                    <flux:label>Geography Type</flux:label>
                                    <flux:select wire:model="geographyType" disabled>
                                        <option value="">Select type...</option>
                                        @foreach($geographyTypes as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </flux:select>
                                </flux:field>

                                <flux:field>
                                    <flux:label>Geography Code</flux:label>
                                    <flux:input
                                        wire:model="geographyCode"
                                        type="text"
                                        placeholder="e.g. E05013806"
                                        disabled
                                    />
                                    <flux:description>9-character ONS code</flux:description>
                                </flux:field>
                            </div>

                            <flux:button type="submit" icon="map" disabled>
                                Load Boundary
                            </flux:button>
                        </form>
                        <p class="mt-2 text-xs text-gray-500">Boundary visualization will be enabled once API endpoints are built</p>
                    </div>
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Example Boundaries</flux:heading>
                    <flux:subheading>Common geography codes for testing</flux:subheading>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Westminster - St James's Ward</p>
                            <p class="font-mono text-sm text-gray-900 dark:text-white">E05013806</p>
                            <flux:badge variant="info" class="mt-2">Ward</flux:badge>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">City of Westminster</p>
                            <p class="font-mono text-sm text-gray-900 dark:text-white">E09000033</p>
                            <flux:badge variant="success" class="mt-2">Local Authority</flux:badge>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cities of London & Westminster</p>
                            <p class="font-mono text-sm text-gray-900 dark:text-white">E14000639</p>
                            <flux:badge variant="warning" class="mt-2">Constituency</flux:badge>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">England</p>
                            <p class="font-mono text-sm text-gray-900 dark:text-white">E92000001</p>
                            <flux:badge variant="danger" class="mt-2">Country</flux:badge>
                        </div>
                    </div>
                </flux:card>
            </div>

            <div>
                <flux:card>
                    <flux:heading>Planned Features</flux:heading>
                    <flux:subheading>What this tool will offer</flux:subheading>

                    <ul class="mt-4 space-y-3 text-sm">
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">GeoJSON Boundaries</p>
                                <p class="text-gray-600 dark:text-gray-400">Accurate polygon boundaries from ONS</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Interactive Map</p>
                                <p class="text-gray-600 dark:text-gray-400">Pan and zoom to explore boundaries</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Multiple Layers</p>
                                <p class="text-gray-600 dark:text-gray-400">Overlay multiple boundaries at once</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Boundary Metadata</p>
                                <p class="text-gray-600 dark:text-gray-400">View area, population, and other stats</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Export GeoJSON</p>
                                <p class="text-gray-600 dark:text-gray-400">Download boundaries for use in GIS tools</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Search by Name</p>
                                <p class="text-gray-600 dark:text-gray-400">Find boundaries by place name</p>
                            </div>
                        </li>
                    </ul>
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Geography Types</flux:heading>
                    <flux:subheading>Available boundary types</flux:subheading>

                    <div class="mt-4 space-y-2">
                        @foreach($geographyTypes as $code => $label)
                            <div class="rounded-lg border border-gray-200 p-2 dark:border-gray-700">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $label }}</p>
                                <p class="font-mono text-xs text-gray-500">{{ $code }}</p>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 space-y-2">
                        <flux:button href="{{ route('tools.lookup') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.magnifying-glass /> Postcode Lookup
                        </flux:button>
                        <flux:button href="{{ route('tools.map') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.map-pin /> Property Map
                        </flux:button>
                        <flux:button href="{{ route('admin.versions') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.clock /> Data Versions
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('api-not-ready', () => {
            alert('Boundary API endpoint not yet implemented. See roadmap item 8.');
        });
    });
</script>
