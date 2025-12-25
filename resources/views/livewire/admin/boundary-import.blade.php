<div class="py-12" wire:poll.5s>
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Boundary & Geography Import</flux:heading>

        <div class="grid gap-6">
            {{-- Boundary Status Table --}}
            <flux:card>
                <flux:heading>Boundary Data Status</flux:heading>
                <flux:subheading>Click any row to expand details • Auto-refreshes every 5 seconds</flux:subheading>

                <div class="mt-6 overflow-x-auto" x-data="{ expanded: {} }">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="w-8 px-2 py-2"></th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Names</th>
                                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Polygons</th>
                                <th scope="col" class="w-12 px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($boundaryTypes as $key => $label)
                                @php
                                    $nameImport = $this->getNameImportStatus($key);
                                    $polygonImport = $this->getImportStatus($key, 'polygons');
                                @endphp

                                {{-- Main Compact Row --}}
                                <tr
                                    class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ $boundaryType === $key ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                    @click="expanded['{{ $key }}'] = !expanded['{{ $key }}']"
                                >
                                    <td class="px-2 py-3" @click.stop>
                                        <input
                                            type="radio"
                                            wire:model.live="boundaryType"
                                            value="{{ $key }}"
                                            class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                        >
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $label }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ $boundaryDescriptions[$key] }}</div>
                                    </td>

                                    {{-- Names Status Badge --}}
                                    <td class="px-3 py-3">
                                        @if($nameImport && $nameImport->status === 'completed')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30">
                                                <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                                <span class="text-xs text-green-900 dark:text-green-200 font-medium">{{ number_format($nameImport->records_processed) }}</span>
                                            </div>
                                        @elseif($nameImport && $nameImport->status === 'processing')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                                                <span class="text-xs text-blue-900 dark:text-blue-200 font-medium">{{ $nameImport->getProgressPercentage() }}%</span>
                                            </div>
                                        @elseif($nameImport && $nameImport->status === 'pending')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                                <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                                                <span class="text-xs text-amber-900 dark:text-amber-200 font-medium">Queued</span>
                                            </div>
                                        @elseif($nameImport && $nameImport->status === 'failed')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30">
                                                <div class="h-2 w-2 rounded-full bg-red-500"></div>
                                                <span class="text-xs text-red-900 dark:text-red-200 font-medium">Failed</span>
                                            </div>
                                        @elseif(isset($existingFiles[$key]))
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                                <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                                                <span class="text-xs text-amber-900 dark:text-amber-200 font-medium">Available</span>
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                                <div class="h-2 w-2 rounded-full bg-gray-400"></div>
                                                <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">—</span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Polygons Status Badge --}}
                                    <td class="px-3 py-3">
                                        @if($polygonImport && $polygonImport->status === 'completed')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30">
                                                <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                                <span class="text-xs text-green-900 dark:text-green-200 font-medium">{{ number_format($polygonImport->records_processed) }}</span>
                                            </div>
                                        @elseif($polygonImport && $polygonImport->status === 'processing')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                                <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                                                <span class="text-xs text-blue-900 dark:text-blue-200 font-medium">{{ $polygonImport->getProgressPercentage() }}%</span>
                                            </div>
                                        @elseif($polygonImport && $polygonImport->status === 'pending')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                                <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                                                <span class="text-xs text-amber-900 dark:text-amber-200 font-medium">Queued</span>
                                            </div>
                                        @elseif($polygonImport && $polygonImport->status === 'failed')
                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30">
                                                <div class="h-2 w-2 rounded-full bg-red-500"></div>
                                                <span class="text-xs text-red-900 dark:text-red-200 font-medium">Failed</span>
                                            </div>
                                        @else
                                            @if(str_ends_with($key, '_lookup'))
                                                {{-- Lookup types don't have polygon data --}}
                                                <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                                    <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">N/A</span>
                                                </div>
                                            @else
                                                @php
                                                    $fileInfo = $this->getBoundaryFileInfo($key);
                                                @endphp
                                                @if($fileInfo)
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-amber-100 dark:bg-amber-900/30">
                                                        <div class="h-2 w-2 rounded-full bg-amber-500"></div>
                                                        <span class="text-xs text-amber-900 dark:text-amber-200 font-medium">Ready</span>
                                                    </div>
                                                @else
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                                        <div class="h-2 w-2 rounded-full bg-gray-400"></div>
                                                        <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">—</span>
                                                    </div>
                                                @endif
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Expand Arrow --}}
                                    <td class="px-2 py-3 text-center">
                                        <svg
                                            class="h-5 w-5 text-gray-400 transition-transform duration-200"
                                            :class="{ 'rotate-180': expanded['{{ $key }}'] }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </td>
                                </tr>

                                {{-- Expandable Details Row --}}
                                <tr
                                    x-show="expanded['{{ $key }}']"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:enter-end="opacity-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    style="display: none;"
                                    class="bg-gray-50 dark:bg-gray-800/50"
                                >
                                    <td colspan="5" class="px-3 py-4">
                                        <div class="grid gap-6 md:grid-cols-2">
                                            {{-- Names Details --}}
                                            <div class="space-y-3">
                                                <div class="font-semibold text-sm text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                                                    Name Lookups
                                                </div>
                                                @if($nameImport)
                                                    <div class="space-y-2 text-xs">
                                                        @if($nameImport->status === 'completed')
                                                            <div>
                                                                <span class="text-gray-600 dark:text-gray-400">Completed:</span>
                                                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $nameImport->completed_at?->format('d M Y H:i') }}</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-gray-600 dark:text-gray-400">Records:</span>
                                                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ number_format($nameImport->records_processed) }}</span>
                                                            </div>
                                                            @if($nameImport->data_type === 'polygons')
                                                                <div class="text-gray-500 dark:text-gray-400 italic">Extracted from polygon data</div>
                                                            @endif
                                                        @elseif($nameImport->status === 'processing')
                                                            <div>
                                                                <span class="text-gray-600 dark:text-gray-400">Progress:</span>
                                                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $nameImport->records_processed }}/{{ $nameImport->records_total }} ({{ $nameImport->getProgressPercentage() }}%)</span>
                                                            </div>
                                                        @elseif($nameImport->status === 'failed')
                                                            <div class="text-red-600 dark:text-red-400">
                                                                <div class="font-medium">Error:</div>
                                                                <div class="mt-1">{{ $nameImport->error_message }}</div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif(isset($existingFiles[$key]))
                                                    <div class="space-y-2">
                                                        <div class="text-xs text-gray-600 dark:text-gray-400">Available from ONSUD import</div>
                                                        <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $existingFiles[$key] }}</div>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">Not imported</div>
                                                @endif

                                                @if(isset($onsPageUrls[$key]))
                                                    <div class="pt-2">
                                                        <a href="{{ $onsPageUrls[$key] }}" target="_blank"
                                                           class="text-xs text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-1"
                                                           @click.stop>
                                                            <flux:icon.arrow-top-right-on-square class="h-3 w-3" />
                                                            ONS Download Page
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Polygons Details --}}
                                            <div class="space-y-3">
                                                <div class="font-semibold text-sm text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
                                                    Boundary Polygons
                                                </div>
                                                @if($polygonImport)
                                                    <div class="space-y-2 text-xs">
                                                        @if($polygonImport->status === 'completed')
                                                            <div>
                                                                <span class="text-gray-600 dark:text-gray-400">Completed:</span>
                                                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $polygonImport->completed_at?->format('d M Y H:i') }}</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-gray-600 dark:text-gray-400">Features:</span>
                                                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ number_format($polygonImport->records_processed) }}</span>
                                                            </div>
                                                        @elseif($polygonImport->status === 'processing')
                                                            <div>
                                                                <span class="text-gray-600 dark:text-gray-400">Progress:</span>
                                                                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $polygonImport->records_processed }}/{{ $polygonImport->records_total }} ({{ $polygonImport->getProgressPercentage() }}%)</span>
                                                            </div>
                                                        @elseif($polygonImport->status === 'failed')
                                                            <div class="text-red-600 dark:text-red-400">
                                                                <div class="font-medium">Error:</div>
                                                                <div class="mt-1">{{ $polygonImport->error_message }}</div>
                                                            </div>
                                                            <flux:button wire:click.stop="processExistingFile('{{ $key }}')" size="xs" variant="danger" class="mt-2">
                                                                Retry Import
                                                            </flux:button>
                                                        @elseif($polygonImport->status === 'pending')
                                                            <div class="text-xs text-gray-600 dark:text-gray-400">Waiting to process...</div>
                                                            <flux:button wire:click.stop="processExistingFile('{{ $key }}')" size="xs" variant="ghost" class="mt-2">
                                                                Retry Import
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                @else
                                                    @if(str_ends_with($key, '_lookup'))
                                                        {{-- Lookup types don't have polygon data --}}
                                                        <div class="space-y-2 text-xs">
                                                            <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                                                <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">N/A</span>
                                                            </div>
                                                            <div class="text-gray-500 dark:text-gray-400 italic">Lookup datasets contain relational data only, no boundary polygons</div>
                                                        </div>
                                                    @else
                                                        @php
                                                            $fileInfo = $this->getBoundaryFileInfo($key);
                                                        @endphp
                                                        @if($fileInfo)
                                                            <div class="space-y-2 text-xs">
                                                                <div class="text-gray-600 dark:text-gray-400">File ready to process</div>
                                                                <div>
                                                                    <span class="text-gray-600 dark:text-gray-400">Date:</span>
                                                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $fileInfo['date'] }}</span>
                                                                </div>
                                                                <div>
                                                                    <span class="text-gray-600 dark:text-gray-400">Size:</span>
                                                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $fileInfo['size'] }}</span>
                                                                </div>
                                                                <flux:button wire:click.stop="processExistingFile('{{ $key }}')" size="xs" variant="primary" class="mt-2">
                                                                    Process Now
                                                                </flux:button>
                                                            </div>
                                                        @else
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">Not imported</div>
                                                        @endif
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>

            {{-- Import Form (unchanged) --}}
            @if($boundaryType)
                <flux:card>
                    <flux:heading>Import Data for {{ $boundaryTypes[$boundaryType] }}</flux:heading>
                    <flux:subheading>Upload file or download from ONS Open Geography Portal</flux:subheading>

                    <form wire:submit="startImport" class="mt-6 space-y-6">

                        {{-- Toggle between file upload and URL download --}}
                        <div class="flex gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="useUrl" value="0" class="text-blue-600 rounded">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload File</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="useUrl" value="1" class="text-blue-600 rounded">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Download from URL</span>
                            </label>
                        </div>

                        {{-- Show option to use existing ONSUD file --}}
                        @if(isset($existingFiles[$boundaryType]))
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                <div class="flex items-start gap-3">
                                    <flux:icon.information-circle class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-blue-900 dark:text-blue-100">ONSUD Name Lookup Available</p>
                                        <p class="text-xs text-blue-700 dark:text-blue-300 mt-1 font-mono">
                                            {{ $existingFiles[$boundaryType] }}
                                        </p>
                                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                                            This CSV file contains name lookups (codes → names) only. Import it to populate geography names in postcode lookups.
                                        </p>
                                    </div>
                                </div>
                                <flux:button
                                    type="button"
                                    wire:click="useExistingFile"
                                    variant="primary"
                                    size="sm"
                                    class="mt-3"
                                    icon="arrow-down-tray"
                                    :disabled="$importing"
                                >
                                    Import Name Lookups
                                </flux:button>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-700"></div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">OR import boundary polygons</span>
                                <div class="flex-1 border-t border-gray-300 dark:border-gray-700"></div>
                            </div>
                        @endif

                        @if($useUrl == 1)
                            {{-- URL Download Option --}}
                            <flux:field>
                                <flux:label>ONS Download URL</flux:label>
                                <input
                                    type="url"
                                    wire:model.live="downloadUrl"
                                    placeholder="https://..."
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                                />
                                <flux:error name="downloadUrl" />
                                <flux:description>Paste the direct download URL from the ONS Open Geography Portal. The server will download and process it automatically.</flux:description>
                            </flux:field>

                            {{-- Download in progress indicator --}}
                            <div wire:loading wire:target="startImport" class="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                                <div class="flex items-center gap-3">
                                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <div>
                                        <div class="text-sm font-medium text-blue-900 dark:text-blue-100">Downloading from ONS...</div>
                                        <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">This may take several minutes for large boundary files. Please wait...</div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- File Upload --}}
                            <flux:field>
                                <flux:label>Upload File</flux:label>
                                <input
                                    type="file"
                                    wire:model="file"
                                    accept=".csv,.json,.geojson,.zip"
                                    class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600"
                                />
                                <flux:error name="file" />
                                <flux:description>
                                    Accepts CSV (name lookups), GeoJSON/JSON (boundaries), or ZIP files. Maximum size: 5GB.
                                </flux:description>

                                {{-- Upload progress --}}
                                <div wire:loading wire:target="file"
                                     x-data="{
                                        progress: 0,
                                        elapsed: 0,
                                        interval: null,
                                        mounted: false
                                     }"
                                     x-init="
                                        if (!mounted) {
                                            mounted = true;
                                            interval = setInterval(() => { elapsed++ }, 1000);
                                        }
                                     "
                                     x-on:livewire-upload-progress.window="progress = $event.detail.progress"
                                     x-on:livewire-upload-finish.window="clearInterval(interval)"
                                     x-on:livewire-upload-error.window="clearInterval(interval)"
                                     class="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-800">
                                    <div class="flex items-start gap-3">
                                        <svg class="animate-spin h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                <span x-show="progress > 0">Uploading file... (<span x-text="progress"></span>%)</span>
                                                <span x-show="progress === 0">Uploading large file...</span>
                                            </div>
                                            <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                                <span x-show="progress === 0 && elapsed < 10">Preparing upload... This may take a few moments for large files.</span>
                                                <span x-show="progress === 0 && elapsed >= 10">
                                                    Upload in progress (<span x-text="elapsed"></span>s elapsed). Large files can take several minutes. Please wait...
                                                </span>
                                                <span x-show="progress > 0">
                                                    Time elapsed: <span x-text="elapsed"></span>s
                                                </span>
                                            </div>
                                            {{-- Progress bar --}}
                                            <div x-show="progress > 0" class="mt-2 w-full bg-blue-200 dark:bg-blue-700 rounded-full h-2">
                                                <div class="bg-blue-600 dark:bg-blue-400 h-2 rounded-full transition-all duration-300"
                                                     :style="'width: ' + progress + '%'"></div>
                                            </div>
                                            {{-- Indeterminate progress for when percentage isn't available --}}
                                            <div x-show="progress === 0 && elapsed >= 10" class="mt-2 w-full bg-blue-200 dark:bg-blue-700 rounded-full h-2 overflow-hidden">
                                                <div class="bg-blue-600 dark:bg-blue-400 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- File uploaded confirmation --}}
                                @if($file)
                                    <div class="mt-2 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg p-3 dark:bg-green-900/20 dark:border-green-800">
                                        <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="font-medium text-green-900 dark:text-green-100">File ready:</span>
                                        <span class="text-green-700 dark:text-green-300">{{ $file->getClientOriginalName() }}</span>
                                    </div>
                                @endif
                            </flux:field>
                        @endif

                        {{-- Submit Button --}}
                        <div>
                            <flux:button
                                type="submit"
                                variant="primary"
                                icon="arrow-up-tray"
                                :disabled="$importing || (!$file && !$downloadUrl) || !$boundaryType"
                                wire:loading.attr="disabled"
                                wire:target="startImport"
                            >
                                <span wire:loading.remove wire:target="startImport">
                                    @if($importing)
                                        <flux:icon.arrow-path class="animate-spin" /> Processing...
                                    @else
                                        {{ $useUrl == 1 ? 'Download & Process' : 'Upload & Process' }}
                                    @endif
                                </span>
                                <span wire:loading wire:target="startImport" class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ $useUrl == 1 ? 'Downloading...' : 'Uploading...' }}
                                </span>
                            </flux:button>
                        </div>
                    </form>
                </flux:card>
            @else
                <flux:card>
                    <div class="text-center py-12">
                        <flux:icon.arrow-up class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-600 dark:text-gray-400">Select a boundary type from the table above to begin importing</p>
                    </div>
                </flux:card>
            @endif

            {{-- Help Card --}}
            <flux:card>
                <flux:heading>Import Guide</flux:heading>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Name Lookups (CSV)</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            Import CSV files to populate geography names in postcode lookups (e.g., E05001234 → "Melksham South"). Available from ONSUD for most boundary types.
                        </p>
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Boundary Polygons (GeoJSON)</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            Import GeoJSON files for map visualization. Download BFC (Full Resolution) files from <a href="https://geoportal.statistics.gov.uk/" target="_blank" class="text-blue-600 dark:text-blue-400 underline">ONS Open Geography Portal</a>.
                        </p>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('import-success', (event) => {
            alert('✓ ' + event.message);
        });

        Livewire.on('import-error', (event) => {
            alert('✗ Import failed: ' + event.message);
        });
    });
</script>
