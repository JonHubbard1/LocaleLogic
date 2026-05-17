<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Property Map</flux:heading>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left column: Map --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Search Form --}}
                <flux:card>
                    <form wire:submit="lookup" class="flex flex-col md:flex-row gap-3">
                        <div class="flex-1">
                            <flux:input
                                wire:model="postcode"
                                placeholder="Enter postcode, e.g. SN12 6AE"
                                type="text"
                            />
                        </div>
                        <flux:field class="min-w-[200px]">
                            <flux:radio.group wire:model.live="mapView">
                                <flux:radio value="markers" label="Markers" />
                                <flux:radio value="route" label="Route" />
                            </flux:radio.group>
                        </flux:field>
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary" icon="magnifying-glass">Search</flux:button>
                            @if($result)
                                <flux:button type="button" wire:click="clear" variant="ghost" icon="x-mark">Clear</flux:button>
                            @endif
                        </div>
                    </form>

                    @if($error)
                        <div class="mt-4 rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                            <div class="flex items-start gap-3">
                                <flux:icon.exclamation-circle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                                <div class="text-sm text-red-800 dark:text-red-200">{{ $error }}</div>
                            </div>
                        </div>
                    @endif
                </flux:card>

                {{-- Map Display --}}
                <flux:card>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <flux:heading>Map View</flux:heading>
                            @if($result)
                                <flux:subheading>
                                    {{ number_format($result['property_count']) }} {{ $result['property_count'] === 1 ? 'property' : 'properties' }} in {{ $result['postcode'] }}
                                </flux:subheading>
                            @else
                                <flux:subheading>Enter a postcode to display properties on the map</flux:subheading>
                            @endif
                        </div>
                        @if($result && ($offsetLat != 0 || $offsetLng != 0))
                            <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-sm">
                                <flux:icon.check-circle class="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                                <span class="text-green-900 dark:text-green-200 font-medium">Calibration Active</span>
                            </div>
                        @endif
                    </div>

                    <div wire:ignore id="map" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800" style="height: 500px;"></div>
                </flux:card>
            </div>

            {{-- Right column: Geography + Details --}}
            <div class="space-y-6">
                @if($result)
                    {{-- Geography Panel --}}
                    <flux:card>
                        <flux:heading class="mb-4">Geography</flux:heading>

                        <div class="space-y-3">
                            @foreach([
                                'local_authority_district' => 'Local Authority',
                                'ward' => 'Ward',
                                'parish' => 'Parish',
                                'county_electoral_division' => 'County Electoral Division',
                                'constituency' => 'Constituency',
                                'region' => 'Region',
                                'police_force_area' => 'Police Force Area',
                            ] as $key => $label)
                                @if(isset($result['geography'][$key]))
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">
                                            {{ $result['geography'][$key]['name'] ?? 'Unknown' }}
                                        </div>
                                        @if(isset($result['geography'][$key]['name_welsh']))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $result['geography'][$key]['name_welsh'] }}</div>
                                        @endif
                                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 font-mono">{{ $result['geography'][$key]['code'] }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </flux:card>

                    {{-- Coordinates Panel --}}
                    <flux:card>
                        <flux:heading class="mb-4">Coordinates</flux:heading>
                        @if(isset($result['coordinates']))
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">WGS84 Lat</span>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ $result['coordinates']['wgs84']['latitude'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">WGS84 Lng</span>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ $result['coordinates']['wgs84']['longitude'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">OS Easting</span>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ number_format($result['coordinates']['os_grid']['easting']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">OS Northing</span>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ number_format($result['coordinates']['os_grid']['northing']) }}</span>
                                </div>
                            </div>
                        @endif
                    </flux:card>

                    {{-- Properties List --}}
                    <flux:card>
                        <flux:heading class="mb-4">Properties</flux:heading>
                        <flux:subheading class="mb-3">{{ number_format($result['property_count']) }} properties found</flux:subheading>
                        <div class="max-h-80 overflow-y-auto space-y-2 pr-1">
                            @foreach($result['uprns'] as $property)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-2 text-xs">
                                    <div class="font-mono font-semibold text-gray-900 dark:text-gray-100">UPRN {{ $property['uprn'] }}</div>
                                    <div class="text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ number_format($property['latitude'], 6) }}, {{ number_format($property['longitude'], 6) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @else
                    {{-- Empty State --}}
                    <flux:card>
                        <div class="text-center py-8">
                            <flux:icon.map class="mx-auto h-12 w-12 text-gray-400" />
                            <flux:heading size="lg" class="mt-4">Search for a postcode</flux:heading>
                            <flux:subheading class="mt-2">Enter a UK postcode to see properties plotted on the map with geography data.</flux:subheading>
                        </div>
                    </flux:card>
                @endif
            </div>
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
        setTimeout(() => {
            map = L.map('map').setView([54.5, -3.5], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            markersLayer = L.layerGroup().addTo(map);
            routeLayer = L.layerGroup().addTo(map);
        }, 100);

        Livewire.on('updateMap', (event) => {
            const { uprns, mapView, postcode, offsetLat, offsetLng, overrides } = event;
            const geography = event.geography || null;
            const coordinates = event.coordinates || null;
            updateMap(uprns, mapView, postcode, offsetLat, offsetLng, overrides || []);
        });

        Livewire.on('clearMap', () => {
            clearMap();
        });
    });

    function updateMap(uprns, mapView, postcode, offsetLat = 0, offsetLng = 0, overrides = []) {
        markersLayer.clearLayers();
        routeLayer.clearLayers();

        if (!uprns || uprns.length === 0) {
            return;
        }

        const overrideMap = {};
        overrides.forEach(o => { overrideMap[o.uprn] = { lat: o.lat, lng: o.lng }; });

        const getCoordinates = (property) => {
            if (overrideMap[property.uprn]) {
                return { lat: overrideMap[property.uprn].lat, lng: overrideMap[property.uprn].lng };
            }
            return {
                lat: property.latitude + parseFloat(offsetLat),
                lng: property.longitude + parseFloat(offsetLng)
            };
        };

        if (mapView === 'markers') {
            uprns.forEach(property => {
                const coords = getCoordinates(property);
                L.marker([coords.lat, coords.lng])
                    .bindPopup(`<strong>UPRN:</strong> ${property.uprn}<br><strong>Postcode:</strong> ${postcode}`)
                    .addTo(markersLayer);
            });
        } else {
            const coordinates = uprns.map(p => {
                const coords = getCoordinates(p);
                return [coords.lat, coords.lng];
            });

            L.polyline(coordinates, {
                color: 'purple',
                weight: 3,
                opacity: 0.7
            }).addTo(routeLayer);

            if (coordinates.length > 0) {
                L.marker(coordinates[0], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).bindPopup('<strong>Start</strong>').addTo(routeLayer);
            }

            if (coordinates.length > 1) {
                const last = coordinates.length - 1;
                L.marker(coordinates[last], {
                    icon: L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    })
                }).bindPopup('<strong>End</strong>').addTo(routeLayer);
            }
        }

        const bounds = uprns.map(p => {
            const coords = getCoordinates(p);
            return [coords.lat, coords.lng];
        });
        map.fitBounds(bounds, { padding: [50, 50] });
    }

    function clearMap() {
        if (markersLayer) markersLayer.clearLayers();
        if (routeLayer) routeLayer.clearLayers();
        if (map) map.setView([54.5, -3.5], 6);
    }
</script>
@endpush
