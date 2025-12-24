<div class="py-12" wire:poll.1s="refreshProgress">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">ONSUD Import Progress</flux:heading>
                <flux:subheading class="mt-1">
                    Epoch {{ $import->epoch }} &bull; {{ $import->release_date->format('d M Y') }}
                </flux:subheading>
            </div>
            <div class="flex gap-3">
                @if($import->status === 'importing')
                    <flux:button variant="danger" wire:click="cancelImport" wire:confirm="Are you sure you want to cancel this import?">
                        <flux:icon.x-mark /> Cancel Import
                    </flux:button>
                @endif
                <flux:button href="{{ route('admin.import') }}" variant="ghost">
                    <flux:icon.arrow-left /> Back to Import Manager
                </flux:button>
            </div>
        </div>

        {{-- Overall Progress Banner --}}
        <flux:card class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <flux:badge :variant="$import->status === 'current' ? 'success' : ($import->status === 'importing' ? 'info' : ($import->status === 'failed' ? 'danger' : 'warning'))" size="lg">
                            {{ ucfirst($import->status) }}
                        </flux:badge>
                        @if($import->status === 'importing')
                            <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="font-medium">Processing...</span>
                            </div>
                        @endif
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div class="{{ $import->status === 'failed' ? 'bg-red-600' : ($import->status === 'current' ? 'bg-green-600' : 'bg-blue-600') }} h-4 rounded-full transition-all duration-500 ease-out flex items-center justify-end pr-2"
                             style="width: {{ $import->progress_percentage }}%">
                            <span class="text-xs font-bold text-white">{{ number_format($import->progress_percentage, 1) }}%</span>
                        </div>
                    </div>
                </div>
                <div class="ml-6 text-right">
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ number_format($import->record_count ?? 0) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        records imported
                    </div>
                </div>
            </div>
        </flux:card>

        {{-- 3-Column Layout --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left Column: File List --}}
            <div class="lg:col-span-1">
                <flux:card>
                    <flux:heading>Files to Process</flux:heading>
                    <flux:subheading>Track progress across multiple regional files</flux:subheading>

                    <div class="mt-4 space-y-2">
                        @if($import->files && count($import->files) > 0)
                            @foreach($import->files as $fileInfo)
                                <div class="flex items-center gap-3 p-3 rounded-lg border {{ $fileInfo['status'] === 'completed' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : ($fileInfo['status'] === 'processing' ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700') }}">
                                    @if($fileInfo['status'] === 'completed')
                                        <svg class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @elseif($fileInfo['status'] === 'processing')
                                        <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    @else
                                        <div class="h-5 w-5 rounded-full border-2 border-gray-300 dark:border-gray-600 flex-shrink-0"></div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium {{ $fileInfo['status'] === 'completed' ? 'text-green-900 dark:text-green-100 line-through' : 'text-gray-900 dark:text-gray-100' }} truncate">
                                            {{ $fileInfo['name'] }}
                                        </p>
                                        @if(isset($fileInfo['records']))
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ number_format($fileInfo['records']) }} records
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <flux:icon.document-text class="mx-auto h-12 w-12 mb-2 opacity-50" />
                                <p class="text-sm">File information will appear here once import starts</p>
                            </div>
                        @endif
                    </div>
                </flux:card>
            </div>

            {{-- Right Column: 2 rows --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Current File Progress --}}
                <flux:card>
                    <flux:heading>Current File Details</flux:heading>
                    <flux:subheading>Live progress for the file being processed</flux:subheading>

                    <div class="mt-4">
                        @if($import->status === 'importing' && $import->current_file <= $import->total_files)
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            File {{ $import->current_file }} of {{ $import->total_files }}
                                        </p>
                                        @if($import->status_message)
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                {{ $import->status_message }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                            {{ number_format($import->record_count ?? 0) }}
                                        </p>
                                        <p class="text-xs text-gray-600 dark:text-gray-400">records so far</p>
                                    </div>
                                </div>

                                {{-- File-level progress bar --}}
                                @if($import->files && isset($import->files[$import->current_file - 1]))
                                    @php
                                        $currentFile = $import->files[$import->current_file - 1];
                                        $fileProgress = isset($currentFile['processed'], $currentFile['total']) && $currentFile['total'] > 0
                                            ? ($currentFile['processed'] / $currentFile['total']) * 100
                                            : 0;
                                    @endphp
                                    <div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-500"
                                                 style="width: {{ $fileProgress }}%"></div>
                                        </div>
                                        <div class="flex justify-between mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            <span>{{ number_format($currentFile['processed'] ?? 0) }} processed</span>
                                            <span>{{ number_format($currentFile['total'] ?? 0) }} total</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @elseif($import->status === 'failed')
                            <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <flux:icon.exclamation-triangle class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <p class="text-sm font-medium text-red-900 dark:text-red-100">Import Failed</p>
                                        @if($import->status_message)
                                            <p class="text-xs text-red-700 dark:text-red-300 mt-1">{{ $import->status_message }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif($import->status === 'current')
                            <div class="p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <svg class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-green-900 dark:text-green-100">Import Completed Successfully</p>
                                        <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                                            Processed {{ number_format($import->record_count) }} records across {{ $import->total_files }} file(s)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <p class="text-sm">Waiting for import to start...</p>
                            </div>
                        @endif
                    </div>
                </flux:card>

                {{-- Real-time Log Feed --}}
                <flux:card>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <flux:heading>Real-time Processing Log</flux:heading>
                            <flux:subheading>Live feedback from the import process</flux:subheading>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge variant="info" size="sm">
                                <flux:icon.arrow-path class="{{ $import->status === 'importing' ? 'animate-spin' : '' }}" />
                                Updates every 1s
                            </flux:badge>
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" wire:model.live="autoScroll" class="rounded">
                                <span class="text-gray-700 dark:text-gray-300">Auto-scroll</span>
                            </label>
                        </div>
                    </div>

                    <div class="bg-gray-900 dark:bg-black rounded-lg p-4 font-mono text-xs h-96 overflow-y-auto"
                         id="log-container"
                         x-data="{ autoScroll: @entangle('autoScroll') }"
                         x-init="$watch('autoScroll', value => { if(value) $el.scrollTop = $el.scrollHeight })"
                         x-effect="if(autoScroll) $el.scrollTop = $el.scrollHeight">
                        @forelse($logLines as $line)
                            <div class="text-green-400 leading-relaxed">{{ $line }}</div>
                        @empty
                            <div class="text-gray-500 text-center py-8">
                                <p>Log output will appear here once import starts...</p>
                                @if($import->log_file)
                                    <p class="mt-2 text-xs">Log file: {{ basename($import->log_file) }}</p>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </flux:card>
            </div>
        </div>

        {{-- Statistics Cards --}}
        @if($import->stats)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-6">
                <flux:card>
                    <flux:subheading>Successful</flux:subheading>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">
                        {{ number_format($import->stats['successful'] ?? 0) }}
                    </p>
                </flux:card>
                <flux:card>
                    <flux:subheading>Skipped</flux:subheading>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">
                        {{ number_format($import->stats['skipped'] ?? 0) }}
                    </p>
                </flux:card>
                <flux:card>
                    <flux:subheading>Errors</flux:subheading>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">
                        {{ number_format($import->stats['errors'] ?? 0) }}
                    </p>
                </flux:card>
                <flux:card>
                    <flux:subheading>Coordinate Errors</flux:subheading>
                    <p class="text-2xl font-bold text-orange-600 dark:text-orange-400 mt-1">
                        {{ number_format($import->stats['coordinate_errors'] ?? 0) }}
                    </p>
                </flux:card>
            </div>
        @endif
    </div>
</div>
