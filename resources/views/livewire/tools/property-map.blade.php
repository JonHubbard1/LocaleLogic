<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Property Map Viewer</flux:heading>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <flux:card>
                    <div class="mb-6">
                        <flux:heading>Interactive Property Map</flux:heading>
                        <flux:subheading>Visualize all properties in a postcode on an interactive map</flux:subheading>
                    </div>

                    <div class="aspect-video rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <flux:icon.map class="h-20 w-20 text-gray-400" />
                            <flux:heading size="lg" class="mt-4">Map Integration Coming Soon</flux:heading>
                            <flux:subheading class="mt-2">
                                Leaflet.js map will be integrated once API endpoints are available
                            </flux:subheading>

                            <div class="mt-6 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <strong>Roadmap Reference:</strong> Requires API endpoints (items 5-8) and Leaflet.js integration
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <form wire:submit="loadMap" class="flex gap-3">
                            <div class="flex-1">
                                <flux:input
                                    wire:model="postcode"
                                    type="text"
                                    placeholder="Enter postcode to view properties"
                                    disabled
                                />
                            </div>
                            <flux:button type="submit" icon="map-pin" disabled>
                                Load Map
                            </flux:button>
                        </form>
                        <p class="mt-2 text-xs text-gray-500">Map functionality will be enabled once API endpoints are built</p>
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
                                <p class="font-medium text-gray-900 dark:text-white">Interactive Leaflet.js Map</p>
                                <p class="text-gray-600 dark:text-gray-400">Pan, zoom, and explore with smooth controls</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Property Markers</p>
                                <p class="text-gray-600 dark:text-gray-400">Plot all properties in the postcode area</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Marker Clustering</p>
                                <p class="text-gray-600 dark:text-gray-400">Automatic clustering for dense areas</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Property Details</p>
                                <p class="text-gray-600 dark:text-gray-400">Click markers to view UPRN and coordinates</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Multiple Base Layers</p>
                                <p class="text-gray-600 dark:text-gray-400">Street, satellite, and terrain views</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-5 w-5 flex-shrink-0 text-blue-500" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Route Export</p>
                                <p class="text-gray-600 dark:text-gray-400">Download routes as GPX or KML files</p>
                            </div>
                        </li>
                    </ul>
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Map Controls Preview</flux:heading>
                    <flux:subheading>Future map controls</flux:subheading>

                    <div class="mt-4 space-y-3">
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Base Layer</p>
                            <flux:select disabled>
                                <option>OpenStreetMap</option>
                                <option>Satellite</option>
                                <option>Terrain</option>
                            </flux:select>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cluster Threshold</p>
                            <flux:input type="number" value="10" disabled />
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Show Boundaries</p>
                            <flux:checkbox disabled>
                                <flux:label>Ward boundaries</flux:label>
                            </flux:checkbox>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 space-y-2">
                        <flux:button href="{{ route('tools.lookup') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.magnifying-glass /> Postcode Lookup
                        </flux:button>
                        <flux:button href="{{ route('tools.boundaries') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.map /> Boundary Viewer
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
            alert('API endpoints not yet implemented. See roadmap items 5-8.');
        });
    });
</script>
