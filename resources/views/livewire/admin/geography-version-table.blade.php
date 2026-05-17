<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Geography Version History</flux:heading>

        <flux:card>
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <flux:heading>ONS Geography Imports</flux:heading>
                    <flux:subheading>LAD, ward, parish, CED, constituency, region, and PFA name lookups</flux:subheading>
                </div>
                <flux:button href="{{ route('admin.boundaries') }}" icon="map">
                    Boundary Imports
                </flux:button>
            </div>

            <div class="mb-6 flex flex-wrap items-center gap-4">
                <flux:select wire:model.live="statusFilter" placeholder="Filter by status">
                    <option value="all">All Statuses</option>
                    <option value="current">Current</option>
                    <option value="archived">Archived</option>
                    <option value="importing">Importing</option>
                </flux:select>

                <flux:select wire:model.live="typeFilter" placeholder="Filter by type">
                    <option value="all">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </flux:select>
            </div>

            @if($versions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th wire:click="sortByField('geography_type')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Type
                                    @if($sortBy === 'geography_type')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('year_code')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Year
                                    @if($sortBy === 'year_code')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('release_date')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Release Date
                                    @if($sortBy === 'release_date')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th wire:click="sortByField('record_count')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Records
                                    @if($sortBy === 'record_count')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Source File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th wire:click="sortByField('imported_at')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Imported
                                    @if($sortBy === 'imported_at')
                                        <flux:icon.chevron-{{ $sortDirection === 'asc' ? 'up' : 'down' }} class="inline h-4 w-4" />
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($versions as $version)
                                <tr wire:key="geo-version-{{ $version->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ ucfirst($version->geography_type) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        20{{ $version->year_code }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $version->release_date->format('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ number_format($version->record_count ?? 0) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ $version->source_file ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php
                                            $variant = match($version->status) {
                                                'current' => 'success',
                                                'importing' => 'info',
                                                default => 'warning'
                                            };
                                        @endphp
                                        <flux:badge :variant="$variant">{{ ucfirst($version->status) }}</flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $version->imported_at?->diffForHumans() ?? '—' }}
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
                    <flux:icon.map class="mx-auto h-12 w-12 text-gray-400" />
                    <flux:heading size="lg" class="mt-2">No geography versions found</flux:heading>
                    <flux:subheading>Import ONS geography lookup tables (LAD, wards, parishes, etc.) to populate this list.</flux:subheading>
                </div>
            @endif
        </flux:card>
    </div>
</div>
