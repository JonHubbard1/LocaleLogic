<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Data Version History</flux:heading>

        <flux:card>
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading>ONSUD Import History</flux:heading>
                    <flux:subheading>View and manage all ONSUD data versions</flux:subheading>
                </div>
                <flux:button href="{{ route('admin.imports') }}" icon="arrow-up-tray">
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

                                                <flux:menu.item icon="information-circle">
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
                    <flux:button href="{{ route('admin.imports') }}" class="mt-4">
                        Import Now
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </div>

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
