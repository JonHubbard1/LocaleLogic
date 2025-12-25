<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Postcode Lookup</flux:heading>

        {{-- Search Form --}}
        <flux:card class="mb-6">
            <form wire:submit="lookup" class="space-y-4">
                <div class="flex gap-3">
                    <div class="flex-1">
                        <flux:input
                            wire:model="postcode"
                            type="text"
                            placeholder="e.g. SW1A 1AA or SN38HE"
                            autocomplete="off"
                        />
                    </div>
                    <flux:button type="submit" icon="magnifying-glass">
                        Lookup
                    </flux:button>
                    @if($result || $error)
                        <flux:button type="button" wire:click="clear" variant="ghost" icon="x-mark">
                            Clear
                        </flux:button>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" wire:model="includeUprns" class="rounded">
                        <span class="text-gray-700 dark:text-gray-300">Include UPRNs (property unique identifiers)</span>
                    </label>
                </div>
            </form>
        </flux:card>

        {{-- Error Display --}}
        @if($error)
            <flux:card class="mb-6 border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-start gap-3">
                    <flux:icon.exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="font-medium text-red-900 dark:text-red-100">Lookup Failed</p>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-1">{{ $error }}</p>
                    </div>
                </div>
            </flux:card>
        @endif

        {{-- Results Display --}}
        @if($result)
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Postcode & Coordinates --}}
                <flux:card>
                    <flux:heading>Postcode & Coordinates</flux:heading>
                    <flux:subheading>{{ $result['postcode'] }}</flux:subheading>

                    <div class="mt-4 space-y-4">
                        {{-- WGS84 Coordinates --}}
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                            <p class="text-xs font-semibold text-blue-900 dark:text-blue-200 mb-3">WGS84 (GPS Coordinates)</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs font-medium text-blue-700 dark:text-blue-300">Latitude</p>
                                    <p class="font-mono text-sm text-blue-900 dark:text-blue-100">{{ $result['coordinates']['wgs84']['latitude'] }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-blue-700 dark:text-blue-300">Longitude</p>
                                    <p class="font-mono text-sm text-blue-900 dark:text-blue-100">{{ $result['coordinates']['wgs84']['longitude'] }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- OS Grid Coordinates --}}
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                            <p class="text-xs font-semibold text-green-900 dark:text-green-200 mb-3">OS Grid Reference</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-xs font-medium text-green-700 dark:text-green-300">Easting</p>
                                    <p class="font-mono text-sm text-green-900 dark:text-green-100">{{ number_format($result['coordinates']['os_grid']['easting']) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-green-700 dark:text-green-300">Northing</p>
                                    <p class="font-mono text-sm text-green-900 dark:text-green-100">{{ number_format($result['coordinates']['os_grid']['northing']) }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Property Count --}}
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                            <p class="text-xs font-medium text-purple-700 dark:text-purple-300">Properties in Postcode</p>
                            <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-1">{{ number_format($result['property_count']) }}</p>
                        </div>
                    </div>
                </flux:card>

                {{-- Geography Data --}}
                <flux:card>
                    <flux:heading>Administrative Geography</flux:heading>
                    <flux:subheading>Electoral and administrative boundaries</flux:subheading>

                    <div class="mt-4 space-y-3">
                        @foreach($result['geography'] as $key => $geo)
                            @if($geo)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
                                    @if($geo['name'])
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $geo['name'] }}</p>
                                        <p class="font-mono text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $geo['code'] }}</p>
                                    @else
                                        <p class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $geo['code'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 italic mt-1">(Name lookup not yet available)</p>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </flux:card>
            </div>

            {{-- UPRNs Display --}}
            @if($result['uprns'])
                <flux:card class="mt-6">
                    <flux:heading>Property UPRNs ({{ count($result['uprns']) }})</flux:heading>
                    <flux:subheading>Unique Property Reference Numbers</flux:subheading>

                    <div class="mt-4 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                            @foreach($result['uprns'] as $uprn)
                                <div class="font-mono text-xs text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 rounded px-2 py-1 border border-gray-200 dark:border-gray-700">
                                    {{ $uprn }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </flux:card>
            @endif

            {{-- JSON View --}}
            <flux:card class="mt-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading>JSON Response</flux:heading>
                        <flux:subheading>Same data structure as the API</flux:subheading>
                    </div>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="clipboard"
                        onclick="navigator.clipboard.writeText(document.getElementById('json-output').textContent)"
                    >
                        Copy
                    </flux:button>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-900 p-4 dark:border-gray-700">
                    <pre id="json-output" class="text-xs text-green-400 overflow-x-auto">{{ json_encode(['data' => $result, 'meta' => ['api_version' => '1.0', 'timestamp' => now()->toIso8601String()]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </flux:card>
        @endif

        {{-- Help Card (shown when no results) --}}
        @if(!$result && !$error)
            <div class="grid gap-6 md:grid-cols-2">
                <flux:card>
                    <flux:heading>How to Use</flux:heading>
                    <ul class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Enter a UK postcode (with or without space)</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>View coordinates in WGS84 (GPS) and OS Grid formats</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>See all administrative boundaries (Ward, LAD, Parish, etc.)</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Optionally include UPRNs for property-level data</span>
                        </li>
                        <li class="flex items-start">
                            <flux:icon.check-circle class="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>View the JSON output matching the REST API format</span>
                        </li>
                    </ul>
                </flux:card>

                <flux:card>
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 space-y-2">
                        <flux:button href="{{ route('admin.import') }}" variant="ghost" class="w-full justify-start">
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
        @endif
    </div>
</div>
