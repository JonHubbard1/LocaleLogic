<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Boundary Viewer</flux:heading>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left: Map --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Search Form --}}
                <flux:card>
                    <div class="flex flex-col md:flex-row gap-3">
                        <flux:field class="min-w-[220px]">
                            <flux:label>Boundary Type</flux:label>
                            <flux:select wire:model.live="boundaryType">
                                <option value="">Select type...</option>
                                @foreach($typeOptions as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        <div class="flex-1 relative">
                            <flux:field>
                                <flux:label>Search by Name</flux:label>
                                <flux:input
                                    wire:model.live.debounce.300ms="searchQuery"
                                    placeholder="Start typing a name..."
                                    type="text"
                                    autocomplete="off"
                                />
                            </flux:field>

                            @if(count($searchResults) > 0)
                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg max-h-60 overflow-y-auto">
                                    @foreach($searchResults as $result)
                                        <button
                                            type="button"
                                            wire:click="selectBoundary('{{ $result['gss_code'] }}', '{{ $result['name'] }}')"
                                            class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                        >
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $result['name'] }}</span>
                                            @if(!empty($result['name_welsh']))
                                                <span class="text-gray-500 dark:text-gray-400"> / {{ $result['name_welsh'] }}</span>
                                            @endif
                                            <span class="ml-2 font-mono text-xs text-gray-400 dark:text-gray-500">{{ $result['gss_code'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex gap-2 items-end">
                            <flux:button wire:click="loadBoundary" variant="primary" icon="magnifying-glass" :disabled="empty($gssCode)">Load</flux:button>
                            <flux:button wire:click="clear" variant="ghost" icon="x-mark">Clear</flux:button>
                        </div>
                    </div>

                    @if($gssCode && $this->supportsPropertyData())
                        <div class="mt-4 flex flex-wrap gap-4">
                            <flux:checkbox wire:model.live="showPostcodeCentres" label="Show Postcode Centres" />
                            <flux:checkbox wire:model.live="showPropertyEndpoints" label="Show Property Endpoints" />
                        </div>
                    @endif

                    @if($showPropertyEndpoints && count($propertyEndpoints) >= 50000)
                        <div class="mt-3 rounded-lg bg-amber-50 p-3 dark:bg-amber-900/20">
                            <div class="flex items-start gap-2">
                                <flux:icon.exclamation-triangle class="h-4 w-4 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                                <div class="text-xs text-amber-800 dark:text-amber-200">Display capped at 50,000 properties. Large boundaries may not show every endpoint.</div>
                            </div>
                        </div>
                    @endif

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
                            @if($boundary)
                                <flux:subheading>{{ $boundary->name }} ({{ $boundary->gss_code }})</flux:subheading>
                            @else
                                <flux:subheading>Search and select a boundary to display it on the map</flux:subheading>
                            @endif
                        </div>
                    </div>

                    <div wire:ignore id="boundary-map" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800" style="height: 500px;"></div>
                </flux:card>
            </div>

            {{-- Right: Metadata + Info --}}
            <div class="space-y-6">
                @if($boundary)
                    <flux:card>
                        <flux:heading class="mb-4">Boundary Details</flux:heading>

                        <div class="space-y-3">
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Name</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ $boundary->name }}</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">GSS Code</div>
                                <div class="text-sm font-mono text-gray-900 dark:text-gray-100 mt-0.5">{{ $boundary->gss_code }}</div>
                            </div>

                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Type</div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ $typeOptions[$boundary->boundary_type] ?? $boundary->boundary_type }}</div>
                            </div>

                            @if($boundary->area_hectares)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Area</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ number_format($boundary->area_hectares, 2) }} hectares</div>
                                </div>
                            @endif

                            @if($boundary->version_date)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Version Date</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-0.5">{{ $boundary->version_date->format('F Y') }}</div>
                                </div>
                            @endif

                            @if($boundary->bounding_box)
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Bounding Box</div>
                                    <div class="text-xs font-mono text-gray-500 dark:text-gray-400 mt-0.5">{{ $boundary->bounding_box }}</div>
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @else
                    <flux:card>
                        <div class="text-center py-8">
                            <flux:icon.globe-alt class="mx-auto h-12 w-12 text-gray-400" />
                            <flux:heading size="lg" class="mt-4">Select a Boundary</flux:heading>
                            <flux:subheading class="mt-2">Choose a boundary type and search by name to view its polygon on the map.</flux:subheading>
                        </div>
                    </flux:card>
                @endif

                {{-- Common Examples --}}
                <flux:card>
                    <flux:heading class="mb-4">Quick Examples</flux:heading>
                    <div class="space-y-2">
                        @php
                            $examples = [
                                ['type' => 'wards', 'name' => 'Ainsdale', 'code' => 'E05000932'],
                                ['type' => 'lad', 'name' => 'Wiltshire', 'code' => 'E06000054'],
                                ['type' => 'constituencies', 'name' => 'Chippenham', 'code' => 'E14000635'],
                                ['type' => 'region', 'name' => 'South West', 'code' => 'E12000009'],
                            ];
                        @endphp
                        @foreach($examples as $ex)
                            <button
                                type="button"
                                wire:click="$set('boundaryType', '{{ $ex['type'] }}'); $set('searchQuery', '{{ $ex['name'] }}'); selectBoundary('{{ $ex['code'] }}', '{{ $ex['name'] }}')"
                                class="w-full text-left rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $ex['name'] }}</span>
                                    <flux:badge size="sm" variant="info">{{ $typeOptions[$ex['type']] ?? $ex['type'] }}</flux:badge>
                                </div>
                                <div class="text-xs font-mono text-gray-400 dark:text-gray-500 mt-0.5">{{ $ex['code'] }}</div>
                            </button>
                        @endforeach
                    </div>
                </flux:card>
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
    let geoJsonLayer = null;
    let centroidsLayer = null;
    let endpointsLayer = null;

    document.addEventListener('livewire:initialized', () => {
        setTimeout(() => {
            map = L.map('boundary-map').setView([54.5, -3.5], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            centroidsLayer = L.layerGroup().addTo(map);
            endpointsLayer = L.layerGroup().addTo(map);
        }, 100);

        Livewire.on('loadBoundaryGeoJson', (event) => {
            loadGeoJson(event.geoJson, event.boundingBox);
        });

        Livewire.on('clearBoundaryMap', () => {
            clearMap();
        });

        Livewire.on('loadBoundaryCentroids', (event) => {
            loadCentroids(event.centroids);
        });

        Livewire.on('clearBoundaryCentroids', () => {
            clearCentroids();
        });

        Livewire.on('loadBoundaryEndpoints', (event) => {
            loadEndpoints(event.endpoints);
        });

        Livewire.on('clearBoundaryEndpoints', () => {
            clearEndpoints();
        });
    });

    function loadGeoJson(geoJson, boundingBox) {
        if (geoJsonLayer) {
            map.removeLayer(geoJsonLayer);
            geoJsonLayer = null;
        }

        if (!geoJson || !geoJson.geometry) {
            return;
        }

        geoJsonLayer = L.geoJSON(geoJson, {
            style: {
                color: '#3b82f6',
                weight: 2,
                opacity: 0.8,
                fillColor: '#3b82f6',
                fillOpacity: 0.15
            },
            onEachFeature: function (feature, layer) {
                const props = feature.properties || {};
                let popup = `<strong>${props.name || 'Boundary'}</strong><br><span class="font-mono text-xs">${props.gss_code || ''}</span>`;
                if (props.area_hectares) {
                    popup += `<br>Area: ${props.area_hectares.toLocaleString()} ha`;
                }
                layer.bindPopup(popup);
            }
        }).addTo(map);

        if (boundingBox) {
            const bounds = [
                [boundingBox.min_lat, boundingBox.min_lng],
                [boundingBox.max_lat, boundingBox.max_lng]
            ];
            map.fitBounds(bounds, { padding: [40, 40] });
        } else if (geoJsonLayer.getBounds) {
            map.fitBounds(geoJsonLayer.getBounds(), { padding: [40, 40] });
        }
    }

    function loadCentroids(centroids) {
        centroidsLayer.clearLayers();
        if (!centroids || centroids.length === 0) return;

        centroids.forEach(c => {
            const marker = L.circleMarker([c.lat, c.lng], {
                radius: 6,
                color: '#10b981',
                weight: 2,
                fillColor: '#10b981',
                fillOpacity: 0.6
            }).bindPopup(
                `<strong>${c.pcds}</strong><br>${c.count.toLocaleString()} propert${c.count === 1 ? 'y' : 'ies'}`
            );
            centroidsLayer.addLayer(marker);
        });
    }

    function clearCentroids() {
        centroidsLayer.clearLayers();
    }

    function loadEndpoints(endpoints) {
        endpointsLayer.clearLayers();
        if (!endpoints || endpoints.length === 0) return;

        endpoints.forEach(e => {
            const marker = L.circleMarker([e.lat, e.lng], {
                radius: 3,
                color: '#f59e0b',
                weight: 1,
                fillColor: '#f59e0b',
                fillOpacity: 0.5
            }).bindPopup(
                `<strong>UPRN ${e.uprn}</strong><br>${e.pcds}`
            );
            endpointsLayer.addLayer(marker);
        });
    }

    function clearEndpoints() {
        endpointsLayer.clearLayers();
    }

    function clearMap() {
        if (geoJsonLayer) {
            map.removeLayer(geoJsonLayer);
            geoJsonLayer = null;
        }
        clearCentroids();
        clearEndpoints();
        if (map) map.setView([54.5, -3.5], 6);
    }
</script>
@endpush
