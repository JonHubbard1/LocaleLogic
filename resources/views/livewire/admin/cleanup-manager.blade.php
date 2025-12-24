<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">System Cleanup</flux:heading>

        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <flux:card>
                    <flux:heading>System Status</flux:heading>
                    <flux:subheading>Current storage usage and cleanup opportunities</flux:subheading>

                    <div class="mt-6 space-y-6">
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:subheading>Staging Table</flux:subheading>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Temporary import staging area
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ number_format($stagingRecords) }}
                                    </p>
                                    <p class="text-xs text-gray-500">records</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:subheading>Old Production Table</flux:subheading>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Previous production table backup
                                    </p>
                                </div>
                                <div class="text-right">
                                    @if($oldTableExists)
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                            {{ number_format($oldTableRecords) }}
                                        </p>
                                        <p class="text-xs text-gray-500">records</p>
                                    @else
                                        <flux:badge variant="success">No old table</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:subheading>Downloaded Files</flux:subheading>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Stored ONSUD import files
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ $filesCount }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ number_format($filesSize / 1024 / 1024, 2) }} MB
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <flux:button wire:click="loadStats" variant="ghost" icon="arrow-path" :disabled="$working">
                            Refresh Stats
                        </flux:button>
                    </div>
                </flux:card>
            </div>

            <div>
                <flux:card>
                    <flux:heading>Cleanup Operations</flux:heading>
                    <flux:subheading>Free up storage space and remove temporary data</flux:subheading>

                    <div class="mt-6 space-y-4">
                        @if($stagingRecords > 0)
                            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                                <div class="mb-3 flex items-start">
                                    <flux:icon.exclamation-triangle class="mr-2 h-5 w-5 text-yellow-600 dark:text-yellow-500" />
                                    <div>
                                        <h4 class="font-semibold text-yellow-900 dark:text-yellow-200">Clear Staging Table</h4>
                                        <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                            Remove {{ number_format($stagingRecords) }} records from the staging table.
                                            This is safe after a successful import.
                                        </p>
                                    </div>
                                </div>
                                <flux:button
                                    wire:click="cleanupStaging"
                                    wire:confirm="Are you sure you want to clear the staging table? This will remove {{ number_format($stagingRecords) }} records."
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    :disabled="$working"
                                >
                                    Clear Staging
                                </flux:button>
                            </div>
                        @else
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                                <div class="flex items-center">
                                    <flux:icon.check-circle class="mr-2 h-5 w-5 text-green-600 dark:text-green-500" />
                                    <p class="text-sm text-green-700 dark:text-green-300">Staging table is empty</p>
                                </div>
                            </div>
                        @endif

                        @if($oldTableExists)
                            <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20">
                                <div class="mb-3 flex items-start">
                                    <flux:icon.exclamation-triangle class="mr-2 h-5 w-5 text-orange-600 dark:text-orange-500" />
                                    <div>
                                        <h4 class="font-semibold text-orange-900 dark:text-orange-200">Drop Old Table</h4>
                                        <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                            Permanently delete the old production table with {{ number_format($oldTableRecords) }} records.
                                            This cannot be undone.
                                        </p>
                                    </div>
                                </div>
                                <flux:button
                                    wire:click="cleanupOldTable"
                                    wire:confirm="Are you sure you want to drop the old table? This will permanently delete {{ number_format($oldTableRecords) }} records and cannot be undone!"
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    :disabled="$working"
                                >
                                    Drop Old Table
                                </flux:button>
                            </div>
                        @else
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                                <div class="flex items-center">
                                    <flux:icon.check-circle class="mr-2 h-5 w-5 text-green-600 dark:text-green-500" />
                                    <p class="text-sm text-green-700 dark:text-green-300">No old table exists</p>
                                </div>
                            </div>
                        @endif

                        @if($filesCount > 0)
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                <div class="mb-3 flex items-start">
                                    <flux:icon.information-circle class="mr-2 h-5 w-5 text-blue-600 dark:text-blue-500" />
                                    <div>
                                        <h4 class="font-semibold text-blue-900 dark:text-blue-200">Remove Downloaded Files</h4>
                                        <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                            Delete {{ $filesCount }} downloaded ONSUD file(s) totaling {{ number_format($filesSize / 1024 / 1024, 2) }} MB.
                                            Files can be re-downloaded if needed.
                                        </p>
                                    </div>
                                </div>
                                <flux:button
                                    wire:click="cleanupFiles"
                                    wire:confirm="Are you sure you want to delete {{ $filesCount }} downloaded file(s)?"
                                    variant="primary"
                                    size="sm"
                                    icon="trash"
                                    :disabled="$working"
                                >
                                    Remove Files
                                </flux:button>
                            </div>
                        @else
                            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                                <div class="flex items-center">
                                    <flux:icon.check-circle class="mr-2 h-5 w-5 text-green-600 dark:text-green-500" />
                                    <p class="text-sm text-green-700 dark:text-green-300">No downloaded files</p>
                                </div>
                            </div>
                        @endif

                        @if($stagingRecords > 0 || $oldTableExists || $filesCount > 0)
                            <div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                                <flux:button
                                    wire:click="cleanupAll"
                                    wire:confirm="Are you sure you want to run ALL cleanup operations? This will clear staging, drop old table, and remove all files!"
                                    variant="danger"
                                    class="w-full"
                                    icon="trash"
                                    :disabled="$working"
                                >
                                    @if($working)
                                        <flux:icon.arrow-path class="animate-spin" /> Running Cleanup...
                                    @else
                                        Run All Cleanup Operations
                                    @endif
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </flux:card>

                <flux:card class="mt-6">
                    <flux:heading>Quick Actions</flux:heading>
                    <div class="mt-4 space-y-2">
                        <flux:button href="{{ route('admin.import') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.arrow-up-tray /> New Import
                        </flux:button>
                        <flux:button href="{{ route('admin.versions') }}" variant="ghost" class="w-full justify-start">
                            <flux:icon.clock /> View History
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('cleanup-success', (event) => {
            alert(event.message);
        });

        Livewire.on('cleanup-error', (event) => {
            alert('Cleanup failed: ' + event.message);
        });
    });
</script>
