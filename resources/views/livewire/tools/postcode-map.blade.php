<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Postcode Map Visualization Demo</flux:heading>

        <div class="grid gap-6">
            {{-- Search Form --}}
            <flux:card>
                <flux:heading>Search Postcode</flux:heading>
                <flux:subheading>Enter a UK postcode to visualize properties on OpenStreetMap</flux:subheading>

                <form wire:submit="lookup" class="mt-6 space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        {{-- Postcode Input --}}
                        <flux:input
                            wire:model="postcode"
                            label="Postcode"
                            placeholder="e.g. SN12 6AE"
                            type="text"
                        />

                        {{-- Map View Toggle --}}
                        <flux:field>
                            <flux:label>Map View</flux:label>
                            <flux:radio.group wire:model.live="mapView">
                                <flux:radio value="markers" label="Individual Markers" />
                                <flux:radio value="route" label="Walking Route (Polyline)" />
                            </flux:radio.group>
                            <flux:description>
                                Markers show each property individually. Route shows a walking path connecting all properties.
                            </flux:description>
                        </flux:field>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" icon="magnifying-glass">
                            Search & Map
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
                            <flux:subheading>Enter a postcode to display properties on the map</flux:subheading>
                        @endif
                    </div>

                    @if($result)
                        <div class="flex items-center gap-2 text-sm">
                            @if($mapView === 'markers')
                                <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                    <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                                    <span class="text-blue-900 dark:text-blue-200 font-medium">Markers Mode</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-purple-100 dark:bg-purple-900/30">
                                    <div class="h-2 w-2 rounded-full bg-purple-500"></div>
                                    <span class="text-purple-900 dark:text-purple-200 font-medium">Route Mode</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Leaflet Map Container --}}
                <div id="map" class="w-full h-[600px] rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800"></div>

                {{-- Map Legend/Help --}}
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                        <div class="flex items-start gap-3">
                            <flux:icon.map-pin class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <div class="text-sm font-medium text-blue-900 dark:text-blue-100">Markers Mode</div>
                                <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                    Each property is shown as an individual marker. Click any marker to see its UPRN.
                                    Perfect for identifying specific properties.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                        <div class="flex items-start gap-3">
                            <flux:icon.arrow-trending-up class="h-5 w-5 text-purple-600 dark:text-purple-400 flex-shrink-0 mt-0.5" />
                            <div>
                                <div class="text-sm font-medium text-purple-900 dark:text-purple-100">Route Mode</div>
                                <div class="text-xs text-purple-700 dark:text-purple-300 mt-1">
                                    Properties connected by a walking route path. Useful for planning leaflet delivery routes
                                    or canvassing walks.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>

            {{-- Example Code --}}
            @if($result)
                <flux:card>
                    <flux:heading>Implementation Code</flux:heading>
                    <flux:subheading>JavaScript code demonstrating how this map was created</flux:subheading>

                    <div class="mt-4 space-y-4">
                        @if($mapView === 'markers')
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Markers Example:</div>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded-lg overflow-x-auto text-xs"><code>// Plot all properties as individual markers
response.uprns.forEach(property => {
  L.marker([property.latitude, property.longitude])
    .bindPopup(`UPRN: ${property.uprn}`)
    .addTo(map);
});</code></pre>
                            </div>
                        @else
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Route Example:</div>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded-lg overflow-x-auto text-xs"><code>// Create a walking route connecting all properties
const coordinates = response.uprns.map(p => [p.latitude, p.longitude]);
L.polyline(coordinates, {
  color: 'purple',
  weight: 3,
  opacity: 0.7
}).addTo(map);

// Add start/end markers
if (coordinates.length > 0) {
  L.marker(coordinates[0]).bindPopup('Start').addTo(map);
  L.marker(coordinates[coordinates.length - 1]).bindPopup('End').addTo(map);
}</code></pre>
                            </div>
                        @endif

                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">API Response Structure:</div>
                            <pre class="p-4 bg-gray-900 text-gray-100 rounded-lg overflow-x-auto text-xs"><code>{
  "postcode": "{{ $result['postcode'] }}",
  "property_count": {{ $result['property_count'] }},
  "uprns": [
    {
      "uprn": "{{ $result['uprns'][0]['uprn'] ?? 'N/A' }}",
      "latitude": {{ $result['uprns'][0]['latitude'] ?? 0 }},
      "longitude": {{ $result['uprns'][0]['longitude'] ?? 0 }}
    }
    @if(count($result['uprns']) > 1), ...@endif
  ]
}</code></pre>
                        </div>
                    </div>
                </flux:card>
            @endif
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
    let routeLayer = null;

    document.addEventListener('livewire:initialized', () => {
        // Initialize the map centered on UK
        map = L.map('map').setView([54.5, -3.5], 6);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Create layer groups for markers and routes
        markersLayer = L.layerGroup().addTo(map);
        routeLayer = L.layerGroup().addTo(map);

        // Listen for map update events from Livewire
        Livewire.on('updateMap', (event) => {
            const { uprns, mapView, postcode } = event;
            updateMap(uprns, mapView, postcode);
        });

        // Listen for clear map event
        Livewire.on('clearMap', () => {
            clearMap();
        });
    });

    function updateMap(uprns, mapView, postcode) {
        // Clear existing layers
        markersLayer.clearLayers();
        routeLayer.clearLayers();

        if (!uprns || uprns.length === 0) {
            return;
        }

        if (mapView === 'markers') {
            // Example 1: Plot all properties as individual markers
            uprns.forEach(property => {
                L.marker([property.latitude, property.longitude])
                    .bindPopup(`<strong>UPRN:</strong> ${property.uprn}<br><strong>Postcode:</strong> ${postcode}`)
                    .addTo(markersLayer);
            });
        } else {
            // Example 2: Create a walking route connecting all properties
            const coordinates = uprns.map(p => [p.latitude, p.longitude]);

            // Draw the polyline route
            L.polyline(coordinates, {
                color: 'purple',
                weight: 3,
                opacity: 0.7
            }).addTo(routeLayer);

            // Add start marker (green)
            L.marker(coordinates[0], {
                icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                })
            }).bindPopup(`<strong>Start</strong><br>UPRN: ${uprns[0].uprn}`).addTo(routeLayer);

            // Add end marker (red)
            if (coordinates.length > 1) {
                const lastIndex = coordinates.length - 1;
                L.marker(coordinates[lastIndex], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).bindPopup(`<strong>End</strong><br>UPRN: ${uprns[lastIndex].uprn}`).addTo(routeLayer);
            }

            // Add numbered markers along the route
            coordinates.forEach((coord, index) => {
                if (index > 0 && index < coordinates.length - 1) {
                    L.circleMarker(coord, {
                        radius: 6,
                        fillColor: 'white',
                        color: 'purple',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 1
                    }).bindPopup(`<strong>Stop ${index + 1}</strong><br>UPRN: ${uprns[index].uprn}`).addTo(routeLayer);
                }
            });
        }

        // Fit map bounds to show all markers
        const bounds = uprns.map(p => [p.latitude, p.longitude]);
        map.fitBounds(bounds, { padding: [50, 50] });
    }

    function clearMap() {
        if (markersLayer) markersLayer.clearLayers();
        if (routeLayer) routeLayer.clearLayers();
        if (map) map.setView([54.5, -3.5], 6);
    }
</script>
@endpush
