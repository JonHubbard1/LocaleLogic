<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Data Version History</flux:heading>

        <flux:card>
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading>ONSUD Import History</flux:heading>
                    <flux:subheading>View and manage all ONSUD data versions</flux:subheading>
                </div>
                <flux:button href="{{ route('admin.import') }}" icon="arrow-up-tray">
                    New Import
                </flux:button>
            </div>

            <div class="mb-6 flex items-center gap-4">
                <flux:select wire:model.live="statusFilter" placeholder="Filter by status">
                    <option value="all">All Statuses</option>
                    <option value="current">Current</option>
                    <option value="archived">Archived</option>
                    <option value="importing">Importing</option>
                    <option value="failed">Failed</option>
                </flux:select>
            </div>

            @if($versions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th wire:click="sortByField('epoch')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Epoch
                                    @if($sortBy === 'epoch')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('release_date')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Release Date
                                    @if($sortBy === 'release_date')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Records</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th wire:click="sortByField('imported_at')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Imported
                                    @if($sortBy === 'imported_at')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($versions as $version)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $version->epoch }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $version->release_date->format('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ number_format($version->record_count ?? 0) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php
                                            $variant = match($version->status) {
                                                'current' => 'success',
                                                'importing' => 'info',
                                                'failed' => 'danger',
                                                default => 'warning'
                                            };
                                        @endphp
                                        <flux:badge :variant="$variant">{{ ucfirst($version->status) }}</flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $version->imported_at->diffForHumans() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <flux:dropdown align="end">
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                            <flux:menu>
                                                @if($version->status === 'current')
                                                    <flux:menu.item icon="archive-box" wire:click="archiveVersion({{ $version->id }})">
                                                        Archive
                                                    </flux:menu.item>
                                                @endif

                                                @if($version->status !== 'current')
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteVersion({{ $version->id }})" wire:confirm="Are you sure you want to delete this version?">
                                                        Delete
                                                    </flux:menu.item>
                                                @endif

                                                <flux:menu.item icon="information-circle" wire:click="viewDetails({{ $version->id }})">
                                                    View Details
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $versions->links() }}
                </div>
            @else
                <div class="py-12 text-center">
                    <flux:icon.inbox class="mx-auto h-12 w-12 text-gray-400" />
                    <flux:heading size="lg" class="mt-2">No data versions found</flux:heading>
                    <flux:subheading>Import your first ONSUD dataset to get started</flux:subheading>
                    <flux:button href="{{ route('admin.import') }}" class="mt-4">
                        Import Now
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Version Details Modal --}}
    <flux:modal wire:model="showDetailsModal" class="max-w-4xl">
        @if($selectedVersion)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $selectedVersion->dataset }} Epoch {{ $selectedVersion->epoch }}</flux:heading>
                    <flux:subheading>
                        Released {{ $selectedVersion->release_date->format('d M Y') }}
                        &middot; Imported {{ $selectedVersion->imported_at?->diffForHumans() ?? 'never' }}
                    </flux:subheading>
                </div>

                {{-- Status + progress --}}
                <div class="flex items-center gap-3">
                    @php
                        $variant = match($selectedVersion->status) {
                            'current' => 'success',
                            'importing' => 'info',
                            'failed' => 'danger',
                            default => 'warning'
                        };
                    @endphp
                    <flux:badge :variant="$variant" size="lg">{{ ucfirst($selectedVersion->status) }}</flux:badge>
                    @if($selectedVersion->progress_percentage !== null)
                        <div class="flex-1">
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                <div class="bg-blue-600 h-3 rounded-full transition-all"
                                     style="width: {{ $selectedVersion->progress_percentage }}%"></div>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ number_format((float) $selectedVersion->progress_percentage, 1) }}%
                        </span>
                    @endif
                </div>

                @if($selectedVersion->status_message)
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-sm text-gray-700 dark:text-gray-300">
                        {{ $selectedVersion->status_message }}
                    </div>
                @endif

                {{-- Summary grid --}}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Records</p>
                        <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($selectedVersion->record_count ?? 0) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Files</p>
                        <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ ($selectedVersion->current_file ?? 0) }} / {{ ($selectedVersion->total_files ?? 0) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Successful</p>
                        <p class="text-xl font-semibold text-green-600 dark:text-green-400">
                            {{ number_format($selectedVersion->stats['successful'] ?? 0) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Errors</p>
                        <p class="text-xl font-semibold text-red-600 dark:text-red-400">
                            {{ number_format(($selectedVersion->stats['errors'] ?? 0) + ($selectedVersion->stats['coordinate_errors'] ?? 0)) }}
                        </p>
                    </div>
                </div>

                {{-- File list --}}
                @if($selectedVersion->files && count($selectedVersion->files) > 0)
                    <div>
                        <flux:heading size="sm" class="mb-2">Source Files</flux:heading>
                        <div class="max-h-64 overflow-y-auto space-y-1 rounded-lg border border-gray-200 dark:border-gray-700 p-2">
                            @foreach($selectedVersion->files as $fileInfo)
                                <div class="flex items-center justify-between gap-3 text-sm p-2 rounded {{ ($fileInfo['status'] ?? '') === 'completed' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-gray-800' }}">
                                    <span class="font-mono text-gray-900 dark:text-gray-100 truncate">{{ $fileInfo['name'] ?? '—' }}</span>
                                    <span class="text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        @if(isset($fileInfo['processed']))
                                            {{ number_format($fileInfo['processed']) }} processed
                                        @endif
                                        @if(isset($fileInfo['status']))
                                            &middot; {{ ucfirst($fileInfo['status']) }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Metadata --}}
                <div class="grid gap-3 sm:grid-cols-2 text-sm">
                    @if($selectedVersion->file_hash)
                        <div>
                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">File Hash</p>
                            <p class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ $selectedVersion->file_hash }}</p>
                        </div>
                    @endif
                    @if($selectedVersion->log_file)
                        <div>
                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Log File</p>
                            <p class="font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ $selectedVersion->log_file }}</p>
                        </div>
                    @endif
                    @if($selectedVersion->notes)
                        <div class="sm:col-span-2">
                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Notes</p>
                            <p class="text-gray-700 dark:text-gray-300">{{ $selectedVersion->notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="$set('showDetailsModal', false)">Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('version-archived', () => {
            alert('Version archived successfully');
        });

        Livewire.on('version-deleted', () => {
            alert('Version deleted successfully');
        });
    });
</script>
