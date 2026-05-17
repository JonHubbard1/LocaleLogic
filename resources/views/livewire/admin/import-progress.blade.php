<div class="py-12" wire:poll.1s="refreshProgress">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">ONSUD Import Progress</flux:heading>
                @if($import->epoch > 0)
                    <flux:subheading class="mt-1">
                        Epoch {{ $import->epoch }} &bull; {{ $import->release_date->format('d M Y') }}
                    </flux:subheading>
                @else
                    <flux:subheading class="mt-1">Discovering latest release...</flux:subheading>
                @endif
            </div>
            <div class="flex gap-3">
                @if($import->status === 'importing')
                    <flux:button variant="danger" wire:click="cancelImport" wire:confirm="Are you sure you want to cancel this import?">
                        <flux:icon.x-mark /> Cancel Import
                    </flux:button>
                @endif
                <flux:button href="{{ route('dashboard') }}" variant="ghost">
                    <flux:icon.arrow-left /> Back to Dashboard
                </flux:button>
            </div>
        </div>

        {{-- Overall Progress --}}
        <flux:card class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <flux:badge :variant="$import->status === 'current' ? 'success' : ($import->status === 'importing' ? 'info' : ($import->status === 'failed' ? 'danger' : 'warning'))" size="lg">
                            {{ ucfirst($import->status) }}
                        </flux:badge>
                        @if($import->status === 'importing')
                            <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                                <flux:icon.arrow-path class="animate-spin h-4 w-4" />
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
                    @if($import->status_message)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">{{ $import->status_message }}</p>
                    @endif
                </div>
                @if($import->record_count > 0)
                    <div class="ml-6 text-right">
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($import->record_count) }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            records imported
                        </div>
                    </div>
                @endif
            </div>
        </flux:card>

        {{-- Step-by-Step Progress --}}
        <flux:card class="mb-6">
            <flux:heading class="mb-4">Import Steps</flux:heading>

            @php
                $stepIcons = [
                    'discover'  => 'magnifying-glass',
                    'download'  => 'arrow-down-tray',
                    'extract'   => 'folder-open',
                    'import'    => 'table-cells',
                    'lookups'   => 'book-open',
                    'reconcile' => 'map-pin',
                    'validate'  => 'shield-check',
                    'swap'      => 'arrow-path',
                    'index'     => 'adjustments-vertical',
                ];
                $steps = $import->steps ?? [];
            @endphp

            <div class="space-y-0">
                @foreach($steps as $index => $step)
                    @php
                        $isLast = $index === count($steps) - 1;
                        $hasConnector = ! $isLast;
                        $status = $step['status'] ?? 'pending';
                    @endphp
                    <div class="flex gap-4">
                        {{-- Icon column --}}
                        <div class="flex flex-col items-center">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ match($status) {
                                'completed' => 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
                                'active'    => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                                'failed'    => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                                default     => 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500',
                            } }}">
                                @if($status === 'completed')
                                    <flux:icon.check-circle class="h-5 w-5" />
                                @elseif($status === 'active')
                                    <flux:icon.arrow-path class="h-5 w-5 animate-spin" />
                                @elseif($status === 'failed')
                                    <flux:icon.x-circle class="h-5 w-5" />
                                @else
                                    <flux:icon :name="$stepIcons[$step['key']] ?? 'minus-circle'" class="h-5 w-5" />
                                @endif
                            </div>
                            @if($hasConnector)
                                <div class="w-px flex-1 bg-gray-200 dark:bg-gray-700 my-1"></div>
                            @endif
                        </div>

                        {{-- Content column --}}
                        <div class="pb-6 flex-1">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium {{ match($status) {
                                    'completed' => 'text-gray-900 dark:text-gray-100',
                                    'active'    => 'text-blue-700 dark:text-blue-300',
                                    'failed'    => 'text-red-700 dark:text-red-300',
                                    default     => 'text-gray-500 dark:text-gray-400',
                                } }}">
                                    {{ $step['label'] }}
                                </span>
                                @if($status === 'completed')
                                    <span class="text-xs text-green-600 dark:text-green-400 font-medium">Done</span>
                                @elseif($status === 'active')
                                    <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">In progress</span>
                                @elseif($status === 'failed')
                                    <span class="text-xs text-red-600 dark:text-red-400 font-medium">Failed</span>
                                @endif
                            </div>

                            @if(isset($step['message']) && $step['message'])
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $step['message'] }}</p>
                            @endif

                            {{-- Step-level progress bar for active steps with progress > 0 --}}
                            @if($status === 'active' && isset($step['progress']) && $step['progress'] > 0)
                                <div class="mt-2">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                        <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500"
                                             style="width: {{ $step['progress'] }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-500">{{ number_format($step['progress'], 1) }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        {{-- 2-Column Layout: Files + Log --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Files --}}
            <flux:card>
                <flux:heading>Files</flux:heading>
                <flux:subheading>Regional CSV and geography lookup files being processed</flux:subheading>

                <div class="mt-4 space-y-2">
                    @if($import->files && count($import->files) > 0)
                        @foreach($import->files as $fileInfo)
                            <div class="flex items-center gap-3 p-3 rounded-lg border {{ $fileInfo['status'] === 'completed' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : ($fileInfo['status'] === 'processing' ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700') }}">
                                @if($fileInfo['status'] === 'completed')
                                    <flux:icon.check-circle class="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                                @elseif($fileInfo['status'] === 'processing')
                                    <flux:icon.arrow-path class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                                @else
                                    <div class="h-5 w-5 rounded-full border-2 border-gray-300 dark:border-gray-600 flex-shrink-0"></div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium {{ $fileInfo['status'] === 'completed' ? 'text-green-900 dark:text-green-100 line-through' : 'text-gray-900 dark:text-gray-100' }} truncate">
                                        {{ $fileInfo['name'] }}
                                    </p>

                                    @if($fileInfo['status'] === 'processing' && isset($fileInfo['total']) && $fileInfo['total'] > 0)
                                        <div class="mt-1">
                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                @php
                                                    $filePct = min(100, round(($fileInfo['processed'] / $fileInfo['total']) * 100, 1));
                                                @endphp
                                                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $filePct }}%"></div>
                                            </div>
                                            <div class="flex items-center justify-between mt-0.5">
                                                <span class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ number_format($fileInfo['processed']) }} / {{ number_format($fileInfo['total']) }} rows
                                                </span>
                                                <span class="text-xs font-medium text-blue-600 dark:text-blue-400">{{ $filePct }}%</span>
                                            </div>
                                        </div>
                                    @elseif($fileInfo['status'] === 'completed' && isset($fileInfo['total']) && $fileInfo['total'] > 0)
                                        <p class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ number_format($fileInfo['total']) }} rows imported
                                        </p>
                                    @elseif(isset($fileInfo['records']) && $fileInfo['records'] > 0)
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
                            <p class="text-sm">File information will appear here once extraction completes</p>
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Log --}}
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <flux:heading>Processing Log</flux:heading>
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

        {{-- Statistics --}}
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
