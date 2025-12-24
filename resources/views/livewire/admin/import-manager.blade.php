<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">ONSUD Import Manager</flux:heading>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <flux:card>
                    <flux:heading>Import New ONSUD Data</flux:heading>
                    <flux:subheading>Upload file or download directly from ONS</flux:subheading>

                    <form wire:submit="startImport" class="mt-6 space-y-6">
                        {{-- Toggle between file upload and URL download --}}
                        <div class="flex gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="useUrl" value="0" class="text-blue-600">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Upload File</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model.live="useUrl" value="1" class="text-blue-600">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Download from URL</span>
                            </label>
                        </div>

                        @if($useUrl)
                            {{-- URL Download Option --}}
                            <flux:field>
                                <flux:label>ONS Download URL</flux:label>
                                <flux:input wire:model="downloadUrl" type="url" placeholder="https://..." />
                                <flux:error name="downloadUrl" />
                                <flux:description>Paste the direct download URL from the ONS Open Geography Portal. The server will download and process it automatically.</flux:description>
                            </flux:field>
                        @else
                            {{-- File Upload Option --}}
                            <flux:field>
                                <flux:label>ONSUD File (CSV or ZIP)</flux:label>
                                <input type="file" wire:model="file" accept=".csv,.zip"
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none focus:border-blue-500 dark:text-gray-300 dark:bg-gray-700 dark:border-gray-600" />
                                <flux:error name="file" />
                                <flux:description>Maximum file size: 5GB. Supports ZIP files with multiple regional CSV files.</flux:description>

                            {{-- Upload progress indicator --}}
                            <div wire:loading wire:target="file"
                                 x-data="{ progress: 0, uploadSize: 0 }"
                                 x-on:livewire-upload-progress="progress = $event.detail.progress"
                                 class="mt-3 flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-blue-900">Uploading file...</div>
                                    <div class="text-xs text-blue-700 mt-1">
                                        <span x-show="progress > 0" x-text="'Progress: ' + progress + '%'"></span>
                                        <span x-show="progress === 0">This may take several minutes for large files. Please wait.</span>
                                    </div>
                                    {{-- Progress bar --}}
                                    <div x-show="progress > 0" class="mt-2 w-full bg-blue-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                             :style="'width: ' + progress + '%'"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- File uploaded confirmation --}}
                            @if($file)
                                <div class="mt-2 flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg p-3">
                                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span class="font-medium">File uploaded:</span>
                                    <span>{{ $file->getClientOriginalName() }} ({{ number_format($file->getSize() / 1024 / 1024, 2) }} MB)</span>
                                </div>
                            @endif
                        </flux:field>
                        @endif

                        <div class="grid gap-6 md:grid-cols-2">
                            <flux:field>
                                <flux:label>Epoch Number</flux:label>
                                <flux:input wire:model="epoch" type="number" placeholder="114" />
                                <flux:error name="epoch" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Release Date</flux:label>
                                <flux:input wire:model="releaseDate" type="date" />
                                <flux:error name="releaseDate" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Batch Size</flux:label>
                            <flux:input wire:model="batchSize" type="number" min="100" max="4000" step="100" />
                            <flux:description>Number of records to process per batch (recommended: 1,000). PostgreSQL limit: 4,000 max.</flux:description>
                        </flux:field>

                        <flux:button type="submit" variant="primary"
                                     :disabled="$importing || (!$file && !$downloadUrl)"
                                     wire:loading.attr="disabled"
                                     wire:target="file,startImport">
                            <span wire:loading.remove wire:target="startImport">
                                @if($importing)
                                    <flux:icon.arrow-path class="animate-spin" /> Processing...
                                @else
                                    <flux:icon.cog-6-tooth /> {{ $useUrl ? 'Download & Process' : 'Process Upload' }}
                                @endif
                            </span>
                            <span wire:loading wire:target="startImport" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                        </flux:button>
                        @if(!$file && !$downloadUrl && !$importing)
                            <p class="text-sm text-gray-500 mt-2">Please {{ $useUrl ? 'provide a URL' : 'upload a file' }} first</p>
                        @endif
                    </form>
                </flux:card>
            </div>

            <div>
                <flux:card wire:poll.2s="refreshLastImport">
                    <div class="flex items-center justify-between">
                        <flux:heading>Import Progress</flux:heading>
                        @if($lastImport && $lastImport->status === 'importing')
                            <div class="flex items-center gap-2 text-xs text-blue-600">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ number_format($lastImport->progress_percentage, 1) }}%</span>
                            </div>
                        @endif
                    </div>
                    @if($lastImport)
                        {{-- Progress Bar (show during import or if failed) --}}
                        @if($lastImport->status === 'importing' || $lastImport->status === 'failed')
                            <div class="mt-4 space-y-2">
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                    <div class="{{ $lastImport->status === 'failed' ? 'bg-red-600' : 'bg-blue-600' }} h-3 rounded-full transition-all duration-500 ease-out"
                                         style="width: {{ $lastImport->progress_percentage }}%"></div>
                                </div>
                                @if($lastImport->status === 'failed')
                                    <div class="text-xs text-red-600 dark:text-red-400 font-medium">
                                        Import stopped at {{ number_format($lastImport->progress_percentage, 1) }}%
                                    </div>
                                @endif
                                @if($lastImport->status_message && $lastImport->status === 'importing')
                                    <div class="text-xs text-gray-600 dark:text-gray-400 break-words">
                                        {{ $lastImport->status_message }}
                                    </div>
                                @endif
                                @if($lastImport->total_files > 1 && $lastImport->status === 'importing')
                                    <div class="text-xs text-gray-500 dark:text-gray-500">
                                        File {{ $lastImport->current_file }} of {{ $lastImport->total_files }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="mt-4 space-y-3">
                            <div>
                                <flux:subheading>Epoch</flux:subheading>
                                <p class="text-sm">{{ $lastImport->epoch }}</p>
                            </div>
                            <div>
                                <flux:subheading>Release Date</flux:subheading>
                                <p class="text-sm">{{ $lastImport->release_date->format('d M Y') }}</p>
                            </div>
                            <div>
                                <flux:subheading>Records Imported</flux:subheading>
                                <p class="text-sm font-mono">{{ number_format($lastImport->record_count ?? 0) }}</p>
                            </div>
                            <div>
                                <flux:subheading>Status</flux:subheading>
                                <flux:badge :variant="$lastImport->status === 'current' ? 'success' : ($lastImport->status === 'importing' ? 'info' : ($lastImport->status === 'failed' ? 'danger' : 'ghost'))">
                                    {{ ucfirst($lastImport->status) }}
                                </flux:badge>
                                @if($lastImport->status === 'failed' && $lastImport->status_message)
                                    <div class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                        <p class="text-xs text-red-800 dark:text-red-200 font-medium">Error:</p>
                                        <p class="text-xs text-red-700 dark:text-red-300 mt-1 break-words">{{ \Illuminate\Support\Str::limit($lastImport->status_message, 200) }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-gray-500">No imports yet</p>
                    @endif
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 space-y-2">
                        <flux:button href="{{ route('admin.versions') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.clock /> View History
                        </flux:button>
                        <flux:button href="{{ route('admin.cleanup') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.trash /> System Cleanup
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('import-started', (event) => {
            alert('Import started successfully!\n\nThe import is now running in the background. This may take several minutes for large files.\n\nLog file: storage/logs/' + event.logFile + '\n\nCheck the "Last Import" panel on the right to monitor progress.');
        });

        Livewire.on('import-error', (event) => {
            alert('Failed to start import: ' + event.message);
        });
    });
</script>
