<div wire:poll.10s class="py-8">
    @php
        $onsud       = $this->getOnsudStatus();
        $boundaries  = $this->getBoundaryStatuses();
        $lookups     = $this->getLookupStatuses();
        $summary     = $this->getSummary();
    @endphp

    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Data Health</flux:heading>
                <flux:subheading class="mt-1">Real-time status of all LocaleLogic data sources</flux:subheading>
            </div>
            @if(config('app.env') !== 'production')
                <div class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                    <flux:icon.exclamation-triangle class="h-3.5 w-3.5" />
                    Dev Mode
                </div>
            @endif
        </div>

        {{-- Summary Bar --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3 text-center">
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['total'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Sources</div>
            </div>
            <div class="rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-center border border-green-200 dark:border-green-800">
                <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $summary['current'] }}</div>
                <div class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wide font-medium">Current</div>
            </div>
            <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-center border border-amber-200 dark:border-amber-800">
                <div class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $summary['outdated'] }}</div>
                <div class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wide font-medium">Outdated</div>
            </div>
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3 text-center border border-blue-200 dark:border-blue-800">
                <div class="text-2xl font-bold text-blue-700 dark:text-blue-300">{{ $summary['importing'] }}</div>
                <div class="text-xs text-blue-600 dark:text-blue-400 uppercase tracking-wide font-medium">Importing</div>
            </div>
            <div class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-center border border-red-200 dark:border-red-800">
                <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $summary['failed'] }}</div>
                <div class="text-xs text-red-600 dark:text-red-400 uppercase tracking-wide font-medium">Failed</div>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-3 text-center border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-gray-500 dark:text-gray-400">{{ $summary['missing'] }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">Missing</div>
            </div>
        </div>

        {{-- ONSUD Hero Card --}}
        <flux:card class="{{ match($onsud['state']) {
            'current'    => 'border-l-4 border-green-500',
            'outdated'   => 'border-l-4 border-amber-500',
            'importing'  => 'border-l-4 border-blue-500',
            'failed'     => 'border-l-4 border-red-500',
            default      => 'border-l-4 border-gray-400',
        } }}">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ match($onsud['state']) {
                        'current'    => 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400',
                        'outdated'   => 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
                        'importing'  => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
                        'failed'     => 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400',
                        default      => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
                    } }}">
                        <flux:icon.circle-stack class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">ONS UPRN Directory</flux:heading>
                            @if($onsud['state'] === 'importing')
                                <flux:icon.arrow-path class="h-4 w-4 text-blue-500 animate-spin" />
                            @elseif($onsud['state'] === 'current')
                                <flux:icon.check-circle class="h-5 w-5 text-green-500" />
                            @elseif($onsud['state'] === 'outdated')
                                <flux:icon.exclamation-triangle class="h-5 w-5 text-amber-500" />
                            @elseif($onsud['state'] === 'failed')
                                <flux:icon.x-circle class="h-5 w-5 text-red-500" />
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            @if($onsud['epoch'])
                                <span class="text-gray-700 dark:text-gray-300"><strong>Epoch {{ $onsud['epoch'] }}</strong> · {{ $onsud['release_date'] }}</span>
                            @endif
                            @if($onsud['records'] > 0)
                                <span class="text-gray-600 dark:text-gray-400">{{ number_format($onsud['records']) }} records</span>
                            @endif
                            @if($onsud['state'] === 'outdated' && isset($onsud['newer_epoch']))
                                <span class="text-amber-600 dark:text-amber-400 font-medium">Epoch {{ $onsud['newer_epoch'] }} available</span>
                            @endif
                        </div>
                        @if($onsud['state'] === 'importing')
                            <div class="mt-2">
                                @if(isset($onsud['progress']) && $onsud['progress'] > 0)
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: {{ $onsud['progress'] }}%"></div>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $onsud['message'] }} · {{ number_format($onsud['progress'], 1) }}%</div>
                                @else
                                    <div class="text-sm text-blue-700 dark:text-blue-300 flex items-center gap-2">
                                        <flux:icon.arrow-path class="h-4 w-4 animate-spin" />
                                        {{ $onsud['message'] }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if($onsud['state'] === 'failed')
                            <div class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $onsud['message'] }}</div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    @if($onsud['state'] === 'importing')
                        @if(isset($onsud['id']))
                            <flux:button href="{{ route('admin.import.progress', ['import' => $onsud['id']]) }}" variant="ghost" icon="eye">View Progress</flux:button>
                        @else
                            <flux:badge variant="info">
                                <flux:icon.arrow-path class="h-3 w-3 animate-spin" /> Starting...
                            </flux:badge>
                        @endif
                    @elseif($onsud['state'] === 'failed')
                        <flux:button wire:click="autoImportOnsud" variant="danger" icon="arrow-path">Retry</flux:button>
                    @elseif($onsud['state'] === 'outdated')
                        <flux:button wire:click="autoImportOnsud" variant="primary" icon="arrow-path">Update ONSUD</flux:button>
                    @elseif($onsud['state'] === 'missing')
                        <flux:button wire:click="autoImportOnsud" variant="primary" icon="arrow-down-tray">Import Now</flux:button>
                    @else
                        <flux:button wire:click="autoImportOnsud" variant="ghost" icon="arrow-path">Re-import</flux:button>
                    @endif
                </div>
            </div>
        </flux:card>

        {{-- Boundary Polygons Grid --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <flux:heading>Boundary Polygons</flux:heading>
                <flux:button href="{{ route('admin.boundaries') }}" variant="ghost" size="sm" icon="arrow-top-right-on-square">Manage</flux:button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($boundaries as $key => $status)
                    <flux:card class="{{ match($status['state']) {
                        'current'    => 'border-l-4 border-green-500',
                        'outdated'   => 'border-l-4 border-amber-500',
                        'importing'  => 'border-l-4 border-blue-500',
                        'failed'     => 'border-l-4 border-red-500',
                        default      => 'border-l-4 border-gray-400',
                    } }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <flux:icon :name="$status['icon']" class="h-4 w-4 text-gray-400" />
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" title="{{ $status['label'] }}">{{ $status['label'] }}</span>
                            </div>
                            @if($status['state'] === 'current')
                                <flux:icon.check-circle class="h-5 w-5 text-green-500 shrink-0" />
                            @elseif($status['state'] === 'outdated')
                                <flux:icon.exclamation-triangle class="h-5 w-5 text-amber-500 shrink-0" />
                            @elseif($status['state'] === 'importing')
                                <flux:icon.arrow-path class="h-5 w-5 text-blue-500 animate-spin shrink-0" />
                            @elseif($status['state'] === 'failed')
                                <flux:icon.x-circle class="h-5 w-5 text-red-500 shrink-0" />
                            @else
                                <flux:icon.minus-circle class="h-5 w-5 text-gray-400 shrink-0" />
                            @endif
                        </div>

                        <div class="mt-3 space-y-1">
                            @if($status['version'])
                                <div class="text-xs text-gray-600 dark:text-gray-400">{{ $status['version'] }}</div>
                            @endif
                            @if($status['records'] > 0)
                                <div class="text-xs text-gray-500 dark:text-gray-500">{{ number_format($status['records']) }} features</div>
                            @endif
                            @if($status['state'] === 'importing' && isset($status['progress']))
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $status['progress'] }}%"></div>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $status['message'] }}</div>
                            @elseif($status['state'] === 'failed')
                                <div class="text-xs text-red-600 dark:text-red-400 mt-1 truncate" title="{{ $status['message'] }}">{{ $status['message'] }}</div>
                            @elseif($status['state'] === 'missing')
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Not imported</div>
                            @endif
                        </div>

                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            @if($status['state'] === 'outdated')
                                <flux:button wire:click="autoImportBoundary('{{ $key }}')" size="sm" variant="primary" class="w-full" icon="arrow-down-tray">Update Now</flux:button>
                            @elseif($status['state'] === 'importing')
                                <flux:button href="{{ route('admin.boundaries') }}" size="sm" variant="ghost" class="w-full" disabled icon="arrow-path">Processing...</flux:button>
                            @elseif($status['state'] === 'failed')
                                <flux:button wire:click="autoImportBoundary('{{ $key }}')" size="sm" variant="danger" class="w-full" icon="arrow-path">Retry</flux:button>
                            @elseif($status['state'] === 'missing')
                                <flux:button wire:click="autoImportBoundary('{{ $key }}')" size="sm" variant="ghost" class="w-full" icon="arrow-down-tray">Import Now</flux:button>
                            @else
                                <flux:button wire:click="autoImportBoundary('{{ $key }}')" size="sm" variant="ghost" class="w-full" icon="arrow-path">Re-import</flux:button>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>

        {{-- Hierarchy Lookups Grid --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <flux:heading>Hierarchy Lookups</flux:heading>
                <flux:button href="{{ route('admin.boundaries') }}" variant="ghost" size="sm" icon="arrow-top-right-on-square">Manage</flux:button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($lookups as $key => $status)
                    <flux:card class="{{ match($status['state']) {
                        'current'    => 'border-l-4 border-green-500',
                        'outdated'   => 'border-l-4 border-amber-500',
                        'importing'  => 'border-l-4 border-blue-500',
                        'failed'     => 'border-l-4 border-red-500',
                        default      => 'border-l-4 border-gray-400',
                    } }}">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <flux:icon.document-text class="h-4 w-4 text-gray-400" />
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $status['label'] }}</span>
                            </div>
                            @if($status['state'] === 'current')
                                <flux:icon.check-circle class="h-5 w-5 text-green-500 shrink-0" />
                            @elseif($status['state'] === 'outdated')
                                <flux:icon.exclamation-triangle class="h-5 w-5 text-amber-500 shrink-0" />
                            @elseif($status['state'] === 'importing')
                                <flux:icon.arrow-path class="h-5 w-5 text-blue-500 animate-spin shrink-0" />
                            @elseif($status['state'] === 'failed')
                                <flux:icon.x-circle class="h-5 w-5 text-red-500 shrink-0" />
                            @else
                                <flux:icon.minus-circle class="h-5 w-5 text-gray-400 shrink-0" />
                            @endif
                        </div>

                        <div class="mt-3 space-y-1">
                            @if($status['version'])
                                <div class="text-xs text-gray-600 dark:text-gray-400">{{ $status['version'] }}</div>
                            @endif
                            @if($status['records'] > 0)
                                <div class="text-xs text-gray-500 dark:text-gray-500">{{ number_format($status['records']) }} records</div>
                            @endif
                            @if($status['state'] === 'importing')
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $status['progress'] ?? 0 }}%"></div>
                                </div>
                            @elseif($status['state'] === 'failed')
                                <div class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $status['message'] }}</div>
                            @elseif($status['state'] === 'missing')
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">Not imported</div>
                            @endif
                        </div>

                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            @if($status['state'] === 'outdated' || $status['state'] === 'missing')
                                <flux:button href="{{ route('admin.boundaries') }}" size="sm" variant="primary" class="w-full" icon="arrow-up-tray">Upload New CSV</flux:button>
                            @elseif($status['state'] === 'failed')
                                <flux:button href="{{ route('admin.boundaries') }}" size="sm" variant="danger" class="w-full" icon="arrow-up-tray">Upload & Retry</flux:button>
                            @else
                                <flux:button href="{{ route('admin.boundaries') }}" size="sm" variant="ghost" class="w-full" icon="arrow-up-tray">Upload New Version</flux:button>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>

        {{-- Quick Links --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <flux:icon.magnifying-glass class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Postcode Lookup</div>
                        <flux:button href="{{ route('tools.lookup') }}" size="sm" variant="ghost" class="px-0">Try it →</flux:button>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <flux:icon.map class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Property Map</div>
                        <flux:button href="{{ route('tools.map') }}" size="sm" variant="ghost" class="px-0">View →</flux:button>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
                        <flux:icon.book-open class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">API Docs</div>
                        <flux:button href="{{ route('api-docs') }}" size="sm" variant="ghost" class="px-0" target="_blank">Read →</flux:button>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                        <flux:icon.key class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">API Tokens</div>
                        <flux:button href="{{ route('admin.api-tokens') }}" size="sm" variant="ghost" class="px-0">Manage →</flux:button>
                    </div>
                </div>
            </flux:card>
        </div>

    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('toast', (event) => {
                // Non-blocking toast; rely on visual card feedback instead of alert
                console.log('[Dashboard Toast]', event.message);
            });

            Livewire.on('import-started', (event) => {
                console.log('[Dashboard Import Started]', event.logFile);
            });

            Livewire.on('import-error', (event) => {
                alert('Failed to start import: ' + event.message);
            });
        });
    </script>
</div>
