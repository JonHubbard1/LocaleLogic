<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">Boundary & Administrative Data Viewer</flux:heading>

        <flux:card>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex-1">
                    <flux:heading>{{ $tableLabel }}</flux:heading>
                    <flux:subheading>View and search {{ strtolower($tableLabel) }} records</flux:subheading>
                </div>
            </div>

            <div class="mb-6 flex flex-col gap-4 sm:flex-row">
                <div class="w-full sm:w-64">
                    <flux:select wire:model.live="selectedTable" placeholder="Select a table">
                        @foreach($availableTables as $tableKey => $tableLabel)
                            <option value="{{ $tableKey }}">{{ $tableLabel }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="searchTerm"
                        placeholder="Search..."
                        icon="magnifying-glass"
                    />
                </div>
            </div>

            @if($records->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                @foreach($columns as $column)
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ ucwords(str_replace('_', ' ', $column)) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @foreach($records as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    @foreach($columns as $column)
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            @if($record->$column)
                                                {{ $record->$column }}
                                            @else
                                                <span class="text-gray-400 dark:text-gray-600">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $records->links() }}
                </div>

                <div class="mt-4">
                    <flux:subheading>
                        Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} records
                    </flux:subheading>
                </div>
            @else
                <div class="py-12 text-center">
                    <flux:icon.inbox class="mx-auto h-12 w-12 text-gray-400" />
                    <flux:heading size="lg" class="mt-2">No records found</flux:heading>
                    <flux:subheading>
                        @if(!empty($searchTerm))
                            No records match your search term "{{ $searchTerm }}"
                        @else
                            This table appears to be empty
                        @endif
                    </flux:subheading>
                </div>
            @endif
        </flux:card>
    </div>
</div>
