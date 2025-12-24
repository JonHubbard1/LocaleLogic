<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('System Cleanup')]
class CleanupManager extends Component
{
    public $stagingRecords = 0;
    public $oldTableExists = false;
    public $oldTableRecords = 0;
    public $filesCount = 0;
    public $filesSize = 0;
    public $working = false;

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        try {
            // Check staging table
            $this->stagingRecords = DB::table('properties_staging')->count();
        } catch (\Exception $e) {
            $this->stagingRecords = 0;
        }

        try {
            // Check old table
            if (DB::getSchemaBuilder()->hasTable('properties_old')) {
                $this->oldTableExists = true;
                $this->oldTableRecords = DB::table('properties_old')->count();
            } else {
                $this->oldTableExists = false;
                $this->oldTableRecords = 0;
            }
        } catch (\Exception $e) {
            $this->oldTableExists = false;
            $this->oldTableRecords = 0;
        }

        // Check downloaded files
        $files = Storage::disk('local')->files('onsud');
        $this->filesCount = count($files);
        $this->filesSize = 0;

        foreach ($files as $file) {
            $this->filesSize += Storage::disk('local')->size($file);
        }
    }

    public function cleanupStaging()
    {
        $this->working = true;

        try {
            Artisan::call('onsud:cleanup', ['--staging' => true, '--force' => true]);
            $this->loadStats();
            $this->dispatch('cleanup-success', message: 'Staging table cleared successfully');
        } catch (\Exception $e) {
            $this->dispatch('cleanup-error', message: $e->getMessage());
        } finally {
            $this->working = false;
        }
    }

    public function cleanupOldTable()
    {
        $this->working = true;

        try {
            Artisan::call('onsud:cleanup', ['--old' => true, '--force' => true]);
            $this->loadStats();
            $this->dispatch('cleanup-success', message: 'Old table dropped successfully');
        } catch (\Exception $e) {
            $this->dispatch('cleanup-error', message: $e->getMessage());
        } finally {
            $this->working = false;
        }
    }

    public function cleanupFiles()
    {
        $this->working = true;

        try {
            Artisan::call('onsud:cleanup', ['--files' => true, '--force' => true]);
            $this->loadStats();
            $this->dispatch('cleanup-success', message: 'Downloaded files removed successfully');
        } catch (\Exception $e) {
            $this->dispatch('cleanup-error', message: $e->getMessage());
        } finally {
            $this->working = false;
        }
    }

    public function cleanupAll()
    {
        $this->working = true;

        try {
            Artisan::call('onsud:cleanup', ['--all' => true, '--force' => true]);
            $this->loadStats();
            $this->dispatch('cleanup-success', message: 'All cleanup operations completed successfully');
        } catch (\Exception $e) {
            $this->dispatch('cleanup-error', message: $e->getMessage());
        } finally {
            $this->working = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.cleanup-manager');
    }
}
