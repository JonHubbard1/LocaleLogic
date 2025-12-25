<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Coordinate Calibration Tool</flux:heading>

        <div class="grid gap-6">
            {{-- Search Form --}}
            <flux:card>
                <flux:heading>Calibrate Pin Positions</flux:heading>
                <flux:subheading>Enter a postcode you know well, then use arrow keys to fine-tune pin positions</flux:subheading>

                <form wire:submit="lookup" class="mt-6 space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        {{-- Postcode Input --}}
                        <flux:input
                            wire:model="postcode"
                            label="Postcode"
                            placeholder="e.g. SN12 6AE"
                            type="text"
                        />

                        {{-- Step Size --}}
                        <flux:input
                            wire:model="stepSize"
                            label="Step Size (meters)"
                            type="number"
                            step="0.5"
                            min="0.5"
                            max="10"
                        >
                            <x-slot:description>
                                Distance to move pins per arrow key press
                            </x-slot:description>
                        </flux:input>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" icon="magnifying-glass">
                            Load Postcode
                        </flux:button>

                        @if($result)
                            <flux:button type="button" wire:click="clear" variant="ghost" icon="x-mark">
                                Clear
                            </flux:button>
                        @endif
                    </div>

                    {{-- Error Message --}}
                    @if($error)
                        <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                            <div class="flex items-start gap-3">
                                <flux:icon.exclamation-circle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                                <div class="text-sm text-red-800 dark:text-red-200">{{ $error }}</div>
                            </div>
                        </div>
                    @endif
                </form>
            </flux:card>

            {{-- Calibration Controls --}}
            @if($result)
                <flux:card>
                    <flux:heading>Adjustment Controls</flux:heading>
                    <flux:subheading>Use arrow keys or click buttons to move all pins. Click Save when pins are correctly aligned.</flux:subheading>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        {{-- Arrow Key Controls --}}
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Directional Controls</div>
                            <div class="inline-grid grid-cols-3 gap-2">
                                <div></div>
                                <flux:button wire:click="adjustOffset('up')" variant="outline" icon="arrow-up" size="sm">
                                    Up
                                </flux:button>
                                <div></div>

                                <flux:button wire:click="adjustOffset('left')" variant="outline" icon="arrow-left" size="sm">
                                    Left
                                </flux:button>
                                <div class="flex items-center justify-center text-xs text-gray-500 dark:text-gray-400">
                                    {{ $stepSize }}m
                                </div>
                                <flux:button wire:click="adjustOffset('right')" variant="outline" icon="arrow-right" size="sm">
                                    Right
                                </flux:button>

                                <div></div>
                                <flux:button wire:click="adjustOffset('down')" variant="outline" icon="arrow-down" size="sm">
                                    Down
                                </flux:button>
                                <div></div>
                            </div>

                            <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                                Tip: You can also use keyboard arrow keys when the map is focused
                            </div>
                        </div>

                        {{-- Current Offset Display --}}
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Current Offset</div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Latitude:</span>
                                    <span class="font-mono text-sm font-medium">{{ number_format($offsetLat, 8) }}°</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Longitude:</span>
                                    <span class="font-mono text-sm font-medium">{{ number_format($offsetLng, 8) }}°</span>
                                </div>
                                <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                    <span class="text-sm text-blue-700 dark:text-blue-300">Approx. Distance:</span>
                                    <span class="font-mono text-sm font-medium text-blue-900 dark:text-blue-100">
                                        ~{{ number_format(sqrt(pow($offsetLat * 111000, 2) + pow($offsetLng * 70000, 2)), 1) }}m
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <flux:button wire:click="saveOffset" variant="primary" icon="check" class="flex-1">
                                    Save Offset
                                </flux:button>
                                <flux:button wire:click="resetOffset" variant="outline" icon="arrow-path">
                                    Reset
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- Map Display --}}
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading>Map View</flux:heading>
                        @if($result)
                            <flux:subheading>
                                Showing {{ $result['property_count'] }} {{ $result['property_count'] === 1 ? 'property' : 'properties' }} in {{ $result['postcode'] }}
                            </flux:subheading>
                        @else
                            <flux:subheading>Enter a postcode to begin calibration</flux:subheading>
                        @endif
                    </div>
                </div>

                {{-- Leaflet Map Container --}}
                <div wire:ignore id="map" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800" style="height: 600px;" tabindex="0"></div>

                @if($result)
                    <div class="mt-4 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                        <div class="flex items-start gap-3">
                            <flux:icon.information-circle class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-blue-800 dark:text-blue-200">
                                <strong>How to calibrate:</strong> Find a property you recognize on the map. Use the arrow controls to shift all pins until they align with the actual building outlines. Once satisfied, click "Save Offset" to apply this adjustment to all future maps.
                            </div>
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>

{{-- Leaflet CSS --}}
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
      crossorigin=""/>
@endpush

{{-- Leaflet JS and Map Logic --}}
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script>
    let map = null;
    let markersLayer = null;

    document.addEventListener('livewire:initialized', () => {
        console.log('Livewire initialized, setting up calibration map...');

        setTimeout(() => {
            // Initialize the map centered on UK
            map = L.map('map').setView([54.5, -3.5], 6);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Create layer group for markers
            markersLayer = L.layerGroup().addTo(map);

            console.log('Calibration map setup complete');

            // Add keyboard controls for arrow keys
            const mapElement = document.getElementById('map');
            mapElement.addEventListener('keydown', (e) => {
                if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                    e.preventDefault();
                    const direction = e.key.replace('Arrow', '').toLowerCase();
                    @this.call('adjustOffset', direction);
                }
            });
        }, 100);

        // Listen for map update events from Livewire
        Livewire.on('updateMap', (event) => {
            const { uprns, offsetLat, offsetLng, postcode } = event;
            updateMap(uprns, offsetLat, offsetLng, postcode);
        });

        // Listen for clear map event
        Livewire.on('clearMap', () => {
            clearMap();
        });

        // Listen for offset saved event
        Livewire.on('offsetSaved', () => {
            alert('Offset saved successfully! This adjustment will be applied to all future maps.');
        });
    });

    function updateMap(uprns, offsetLat, offsetLng, postcode) {
        // Clear existing markers
        markersLayer.clearLayers();

        if (!uprns || uprns.length === 0) {
            return;
        }

        // Plot all properties with the current offset applied
        uprns.forEach(property => {
            const adjustedLat = property.latitude + parseFloat(offsetLat);
            const adjustedLng = property.longitude + parseFloat(offsetLng);

            L.marker([adjustedLat, adjustedLng])
                .bindPopup(`<strong>UPRN:</strong> ${property.uprn}<br><strong>Postcode:</strong> ${postcode}`)
                .addTo(markersLayer);
        });

        // Fit map bounds to show all markers (using adjusted coordinates)
        const bounds = uprns.map(p => [
            p.latitude + parseFloat(offsetLat),
            p.longitude + parseFloat(offsetLng)
        ]);
        map.fitBounds(bounds, { padding: [50, 50] });

        // Focus the map so keyboard controls work
        document.getElementById('map').focus();
    }

    function clearMap() {
        if (markersLayer) markersLayer.clearLayers();
        if (map) map.setView([54.5, -3.5], 6);
    }
</script>
@endpush
