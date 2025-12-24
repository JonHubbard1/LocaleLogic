<?php

namespace App\Livewire\Admin;

use App\Models\DataVersion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
    public $downloadUrl = '';
    public $epoch = '';
    public $releaseDate = '';
    public $batchSize = 1000;
    public $importing = false;
    public $lastImport = null;
    public $useUrl = false;

    public function mount()
    {
        $this->refreshLastImport();
    }

    public function refreshLastImport()
    {
        $this->lastImport = DataVersion::where('dataset', 'ONSUD')
            ->whereIn('status', ['current', 'importing'])
            ->latest()
            ->first();
    }

    public function startImport()
    {
        // Debug: Log everything we can see
        Log::info('Import attempt - detailed debug', [
            'file_property' => $this->file,
            'file_exists' => $this->file !== null,
            'file_type' => $this->file ? get_class($this->file) : 'N/A',
            'use_url' => $this->useUrl,
            'download_url' => $this->downloadUrl,
            'epoch' => $this->epoch,
            'releaseDate' => $this->releaseDate,
            'all_properties' => get_object_vars($this),
        ]);

        // Validate based on whether using URL or file upload
        try {
            if ($this->useUrl) {
                $this->validate([
                    'downloadUrl' => 'required|url',
                    'epoch' => 'required|integer',
                    'releaseDate' => 'required|date',
                ]);
            } else {
                $this->validate([
                    'file' => 'required|file|mimes:csv,zip|max:5242880', // 5GB max (ONSUD files are 2-3GB)
                    'epoch' => 'required|integer',
                    'releaseDate' => 'required|date',
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'file_value' => $this->file,
                'url_value' => $this->downloadUrl,
            ]);
            throw $e;
        }

        // Cancel any existing incomplete imports before starting new one
        DataVersion::where('dataset', 'ONSUD')
            ->where('status', 'importing')
            ->update([
                'status' => 'cancelled',
                'status_message' => 'Cancelled - new import started',
            ]);

        $this->importing = true;

        try {
            // Build the artisan command to run in background
            $basePath = base_path();
            $phpBinary = '/usr/bin/php'; // Use CLI binary, not PHP_BINARY which returns php-fpm
            $logFile = storage_path('logs/onsud-import-' . date('Y-m-d-His') . '.log');

            // Create the DataVersion record first so we can redirect to it
            $dataVersion = DataVersion::updateOrCreate(
                [
                    'dataset' => 'ONSUD',
                    'epoch' => $this->epoch,
                ],
                [
                    'release_date' => $this->releaseDate,
                    'status' => 'importing',
                    'progress_percentage' => 0,
                    'status_message' => 'Preparing to start import...',
                    'current_file' => 0,
                    'total_files' => 0,
                    'record_count' => 0,
                    'files' => null,
                    'stats' => null,
                    'log_file' => $logFile,
                    'imported_at' => now(),
                    'file_hash' => null,
                    'notes' => 'Import started at ' . now()->toDateTimeString(),
                ]
            );

            if ($this->useUrl) {
                // Download from URL
                $command = sprintf(
                    '%s %s/artisan onsud:import --url=%s --epoch=%s --release-date=%s --batch-size=%s --log-file=%s >> %s 2>&1 &',
                    escapeshellarg($phpBinary),
                    escapeshellarg($basePath),
                    escapeshellarg($this->downloadUrl),
                    escapeshellarg($this->epoch),
                    escapeshellarg($this->releaseDate),
                    escapeshellarg($this->batchSize),
                    escapeshellarg($logFile),
                    escapeshellarg($logFile)
                );
            } else {
                // Upload file first
                $path = $this->file->store('onsud', 'local');
                $fullPath = Storage::path($path);

                $command = sprintf(
                    '%s %s/artisan onsud:import --file=%s --epoch=%s --release-date=%s --batch-size=%s --log-file=%s >> %s 2>&1 &',
                    escapeshellarg($phpBinary),
                    escapeshellarg($basePath),
                    escapeshellarg($fullPath),
                    escapeshellarg($this->epoch),
                    escapeshellarg($this->releaseDate),
                    escapeshellarg($this->batchSize),
                    escapeshellarg($logFile),
                    escapeshellarg($logFile)
                );
            }

            // Run the import in background
            exec($command);

            Log::info('Import started in background', [
                'method' => $this->useUrl ? 'url' : 'file',
                'source' => $this->useUrl ? $this->downloadUrl : ($fullPath ?? null),
                'epoch' => $this->epoch,
                'release_date' => $this->releaseDate,
                'command' => $command,
                'log_file' => $logFile,
                'data_version_id' => $dataVersion->id,
            ]);

            // Redirect to the progress screen
            return redirect()->route('admin.import.progress', ['import' => $dataVersion->id]);

        } catch (\Exception $e) {
            Log::error('Failed to start import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
