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
    public $postcodeFilter = '';
    public $ladFilter = '';
    public $recordLimit = 0;
    public $devMode = false;
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
        Log::info('Import attempt - detailed debug', [
            'file_property' => $this->file,
            'file_exists' => $this->file !== null,
            'file_type' => $this->file ? get_class($this->file) : 'N/A',
            'use_url' => $this->useUrl,
            'download_url' => $this->downloadUrl,
            'epoch' => $this->epoch,
            'releaseDate' => $this->releaseDate,
            'postcodeFilter' => $this->postcodeFilter,
            'recordLimit' => $this->recordLimit,
            'devMode' => $this->devMode,
        ]);

        // Validate based on whether using URL or file upload
        try {
            $rules = [
                'epoch' => 'required|integer',
                'releaseDate' => 'required|date',
                'batchSize' => 'required|integer|min:100|max:4000',
                'recordLimit' => 'nullable|integer|min:0',
                'postcodeFilter' => 'nullable|string|max:10',
                'ladFilter' => 'nullable|string|size:9',
            ];

            if ($this->useUrl) {
                $rules['downloadUrl'] = 'required|url';
            } else {
                $rules['file'] = 'required|file|mimes:csv,zip|max:5242880';
            }

            $this->validate($rules);
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

            $extraOptions = '';
            if ($this->postcodeFilter) {
                $extraOptions .= ' --postcode-filter=' . escapeshellarg($this->postcodeFilter);
            }
            if ($this->ladFilter) {
                $extraOptions .= ' --lad-filter=' . escapeshellarg($this->ladFilter);
            }
            if ($this->recordLimit > 0) {
                $extraOptions .= ' --limit=' . escapeshellarg($this->recordLimit);
            }

            if ($this->useUrl) {
                // Download from URL
                $command = sprintf(
                    '%s %s/artisan onsud:import --url=%s --epoch=%s --release-date=%s --batch-size=%s%s --log-file=%s >> %s 2>&1 &',
                    escapeshellarg($phpBinary),
                    escapeshellarg($basePath),
                    escapeshellarg($this->downloadUrl),
                    escapeshellarg($this->epoch),
                    escapeshellarg($this->releaseDate),
                    escapeshellarg($this->batchSize),
                    $extraOptions,
                    escapeshellarg($logFile),
                    escapeshellarg($logFile)
                );
            } else {
                // Upload file first
                $path = $this->file->store('onsud', 'local');
                $fullPath = Storage::path($path);

                $command = sprintf(
                    '%s %s/artisan onsud:import --file=%s --epoch=%s --release-date=%s --batch-size=%s%s --log-file=%s >> %s 2>&1 &',
                    escapeshellarg($phpBinary),
                    escapeshellarg($basePath),
                    escapeshellarg($fullPath),
                    escapeshellarg($this->epoch),
                    escapeshellarg($this->releaseDate),
                    escapeshellarg($this->batchSize),
                    $extraOptions,
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

    public function autoDiscover(): void
    {
        $this->validate([
            'batchSize' => 'required|integer|min:100|max:4000',
            'recordLimit' => 'nullable|integer|min:0',
            'postcodeFilter' => 'nullable|string|max:10',
            'ladFilter' => 'nullable|string|size:9',
        ]);

        $this->importing = true;

        try {
            $basePath = base_path();
            $phpBinary = '/usr/bin/php';
            $logFile = storage_path('logs/onsud-import-' . date('Y-m-d-His') . '.log');

            $extraOptions = '';
            if ($this->postcodeFilter) {
                $extraOptions .= ' --postcode-filter=' . escapeshellarg($this->postcodeFilter);
            }
            if ($this->ladFilter) {
                $extraOptions .= ' --lad-filter=' . escapeshellarg($this->ladFilter);
            }
            if ($this->recordLimit > 0) {
                $extraOptions .= ' --limit=' . escapeshellarg($this->recordLimit);
            }

            $command = sprintf(
                '%s %s/artisan onsud:import --auto-discover --batch-size=%s%s --log-file=%s >> %s 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($basePath),
                escapeshellarg($this->batchSize),
                $extraOptions,
                escapeshellarg($logFile),
                escapeshellarg($logFile)
            );

            exec($command);

            Log::info('ONSUD auto-discover started', [
                'command' => $command,
                'log_file' => $logFile,
            ]);

            $this->dispatch('import-started', logFile: basename($logFile));
            $this->refreshLastImport();
        } catch (\Exception $e) {
            Log::error('Auto-discover failed', ['error' => $e->getMessage()]);
            $this->dispatch('import-error', ['message' => $e->getMessage()]);
        } finally {
            $this->importing = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.import-manager');
    }
}
