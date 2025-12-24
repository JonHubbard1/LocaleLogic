<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <flux:heading size="xl" class="mb-6">ONSUD Import Manager</flux:heading>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <flux:card>
                    <flux:heading>Import New ONSUD Data</flux:heading>
                    <flux:subheading>Upload and process ONS UPRN Directory data</flux:subheading>

                    <form wire:submit="startImport" class="mt-6 space-y-6">
                        <flux:field>
                            <flux:label>ONSUD File (CSV or ZIP)</flux:label>
                            <flux:input type="file" wire:model="file" accept=".csv,.zip" />
                            <flux:error name="file" />
                            <flux:description>Maximum file size: 500MB</flux:description>
                        </flux:field>

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
                            <flux:input wire:model="batchSize" type="number" min="1000" max="50000" step="1000" />
                            <flux:description>Number of records to process per batch (recommended: 10,000)</flux:description>
                        </flux:field>

                        <flux:button type="submit" variant="primary" :disabled="$importing">
                            @if($importing)
                                <flux:icon.arrow-path class="animate-spin" /> Importing...
                            @else
                                <flux:icon.arrow-up-tray /> Start Import
                            @endif
                        </flux:button>
                    </form>
                </flux:card>
            </div>

            <div>
                <flux:card>
                    <flux:heading>Last Import</flux:heading>
                    @if($lastImport)
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
                                <flux:subheading>Records</flux:subheading>
                                <p class="text-sm">{{ number_format($lastImport->record_count) }}</p>
                            </div>
                            <div>
                                <flux:subheading>Status</flux:subheading>
                                <flux:badge :variant="$lastImport->status === 'current' ? 'success' : 'warning'">
                                    {{ ucfirst($lastImport->status) }}
                                </flux:badge>
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
        Livewire.on('import-complete', () => {
            alert('Import completed successfully!');
        });

        Livewire.on('import-error', (event) => {
            alert('Import failed: ' + event.message);
        });
    });
</script>
