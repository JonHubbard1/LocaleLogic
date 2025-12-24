<?php

namespace App\Livewire\Admin;

use App\Models\DataVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('ONSUD Import Manager')]
class ImportManager extends Component
{
    use WithFileUploads;

    public $file;
    public $epoch = '';
    public $releaseDate = '';
    public $batchSize = 10000;
    public $importing = false;
    public $lastImport = null;

    public function mount()
    {
        $this->lastImport = DataVersion::where('dataset', 'ONSUD')->latest()->first();
    }

    public function startImport()
    {
        $this->validate([
            'file' => 'required|file|mimes:csv,zip|max:512000',
            'epoch' => 'required|integer',
            'releaseDate' => 'required|date',
        ]);

        $this->importing = true;

        try {
            $path = $this->file->store('onsud', 'local');
            $fullPath = Storage::path($path);

            Artisan::call('onsud:import', [
                '--file' => $fullPath,
                '--epoch' => $this->epoch,
                '--release-date' => $this->releaseDate,
                '--batch-size' => $this->batchSize,
            ]);

            $this->dispatch('import-complete');
            $this->reset(['file', 'epoch', 'releaseDate']);
            $this->lastImport = DataVersion::where('dataset', 'ONSUD')->latest()->first();

        } catch (\Exception $e) {
            $this->dispatch('import-error', message: $e->getMessage());
        } finally {
            $this->importing = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.import-manager');
    }
}
