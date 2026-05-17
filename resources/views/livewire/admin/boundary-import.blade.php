<div class="py-12" wire:poll.30s>
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

        <flux:heading size="xl">Boundary & Geography Import</flux:heading>

        {{-- Section 1: Auto-Importable Boundary Polygons --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading>Boundary Polygons</flux:heading>
                    <flux:subheading>Automatically discover and import from ONS ArcGIS. Auto-refreshes every 5 seconds.</flux:subheading>
                </div>
                <flux:button
                    wire:click="autoImportAll"
                    variant="primary"
                    size="sm"
                    icon="arrow-down-tray"
                >
                    Update All Now
                </flux:button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Boundary Type</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Latest Version</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Records</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($autoBoundaryTypes as $key => $label)
                            @php
                                $polygonImport = $this->getImportStatus($key, 'polygons');
                                $nameImport = $this->getNameImportStatus($key);
                                $isOutdated = $this->isOutdated($key);
                                $hasRunning = $polygonImport && in_array($polygonImport->status, ['pending', 'processing']);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 {{ $isOutdated ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                <td class="px-3 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $label }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $boundaryDescriptions[$key] ?? '' }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    @if($polygonImport && $polygonImport->status === 'completed')
                                        @php $onsVersion = $this->getOnsVersionDate($key); @endphp
                                        @if($onsVersion)
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($onsVersion)->format('F Y') }}</span>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">—</span>
                                        @endif
                                    @elseif($isOutdated)
                                        <span class="inline-flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
                                            <flux:icon.exclamation-triangle class="h-3 w-3" />
                                            Update expected
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($hasRunning)
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30">
                                            <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                                            <span class="text-xs text-blue-900 dark:text-blue-200 font-medium">
                                                @if($polygonImport->status === 'pending')
                                                    Queued
                                                @else
                                                    {{ $polygonImport->getProgressPercentage() }}%
                                                @endif
                                            </span>
                                        </div>
                                    @elseif($polygonImport && $polygonImport->status === 'completed')
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30">
                                            <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                            <span class="text-xs text-green-900 dark:text-green-200 font-medium">Current</span>
                                        </div>
                                    @elseif($polygonImport && $polygonImport->status === 'failed')
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-red-100 dark:bg-red-900/30">
                                            <div class="h-2 w-2 rounded-full bg-red-500"></div>
                                            <span class="text-xs text-red-900 dark:text-red-200 font-medium">Failed</span>
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-2 w-2 rounded-full bg-gray-400"></div>
                                            <span class="text-xs text-gray-600 dark:text-gray-400 font-medium">Not imported</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($polygonImport && $polygonImport->status === 'completed')
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ number_format($polygonImport->records_processed) }}</span>
                                    @elseif($hasRunning && $polygonImport->records_total > 0)
                                        <span class="text-xs text-gray-700 dark:text-gray-300">{{ number_format($polygonImport->records_processed) }} / {{ number_format($polygonImport->records_total) }}</span>
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if($hasRunning)
                                        <flux:button size="xs" variant="ghost" disabled>
                                            <flux:icon.arrow-path class="animate-spin h-3 w-3" />
                                            Processing...
                                        </flux:button>
                                    @else
                                        <flux:button
                                            wire:click="autoImport('{{ $key }}')"
                                            size="xs"
                                            variant="primary"
                                            icon="arrow-down-tray"
                                        >
                                            Update Now
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>

        {{-- Flash Messages --}}
        @if(session('success'))
            <flux:card class="bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
                <div class="flex items-center gap-2 text-green-800 dark:text-green-200">
                    <flux:icon.check-circle class="h-5 w-5 shrink-0" />
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            </flux:card>
        @endif
        @if(session('error'))
            <flux:card class="bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
                <div class="flex items-center gap-2 text-red-800 dark:text-red-200">
                    <flux:icon.exclamation-triangle class="h-5 w-5 shrink-0" />
                    <span class="text-sm font-medium">{{ session('error') }}</span>
                </div>
            </flux:card>
        @endif

        {{-- Section 3: Manual Import Form --}}
        @if($boundaryType)
            <flux:card>
                <flux:heading>
                    Import Data for {{ $boundaryTypes[$boundaryType] ?? 'Unknown' }}
                </flux:heading>
                <flux:subheading>
                    Upload file or download from URL for out-of-cycle updates
                </flux:subheading>

                {{-- File Upload Form: Standard Laravel POST (bypasses Livewire file upload issues) --}}
                <form action="{{ route('admin.boundaries.upload') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-6">
                    @csrf
                    <input type="hidden" name="boundaryType" value="{{ $boundaryType }}">

                    <flux:field>
                        <flux:label>Upload File</flux:label>
                        <input
                            type="file"
                            name="file"
                            accept=".csv,.json,.geojson,.zip"
                            required
                            class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600"
                        />
                        @error('file')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <flux:description>Accepts CSV (lookups), GeoJSON/JSON (boundaries), or ZIP files. Maximum size: 5GB.</flux:description>
                    </flux:field>

                    <div class="flex items-center gap-3">
                        <flux:button
                            type="submit"
                            variant="primary"
                            icon="arrow-up-tray"
                        >
                            Upload & Process
                        </flux:button>
                    </div>
                </form>

                {{-- URL Download Form: Livewire for streaming remote downloads --}}
                @if($this->supportsAutoImport($boundaryType))
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <flux:subheading>Or download from a direct URL</flux:subheading>
                        <form wire:submit="startImport" class="mt-4 space-y-4">
                            <input type="hidden" wire:model="useUrl" value="1">
                            <flux:field>
                                <flux:label>Download URL</flux:label>
                                <input
                                    type="url"
                                    wire:model.live="downloadUrl"
                                    placeholder="https://..."
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400"
                                />
                                <flux:error name="downloadUrl" />
                                <flux:description>Paste the direct download URL. The server will stream-download and process it automatically.</flux:description>
                            </flux:field>

                            <div class="flex items-center gap-3">
                                <flux:button
                                    type="submit"
                                    variant="ghost"
                                    icon="arrow-down-tray"
                                    :disabled="$importing || empty($downloadUrl)"
                                    wire:loading.attr="disabled"
                                    wire:target="startImport"
                                >
                                    <span wire:loading.remove wire:target="startImport">Download & Process</span>
                                    <span wire:loading wire:target="startImport" class="flex items-center gap-2">
                                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </flux:button>

                                <flux:button
                                    type="button"
                                    wire:click="autoImport('{{ $boundaryType }}')"
                                    variant="ghost"
                                    size="sm"
                                    icon="arrow-down-tray"
                                >
                                    Or Auto-Import from ArcGIS
                                </flux:button>
                            </div>
                        </form>
                    </div>
                @endif
            </flux:card>
        @endif

        {{-- Help Card --}}
        <flux:card>
            <flux:heading>Import Guide</flux:heading>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Auto-Import (ArcGIS)</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        Boundary polygons are downloaded automatically from ONS Open Geography Portal ArcGIS services. Each "Update Now" call discovers the latest version, downloads the GeoJSON, and imports it.
                    </p>
                </div>
                <div>
                    <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Manual Upload</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        Use for hierarchy lookups (relationship tables) or out-of-cycle boundary updates not yet available on ArcGIS.
                    </p>
                </div>
            </div>
        </flux:card>
    </div>
</div>
