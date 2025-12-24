<?php

namespace App\Livewire\Admin;

use App\Models\DataVersion;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Import Progress')]
class ImportProgress extends Component
{
    public DataVersion $import;
    public array $logLines = [];
    public int $logOffset = 0;
    public bool $autoScroll = true;

    public function mount(DataVersion $import)
    {
        $this->import = $import;
        $this->loadRecentLogs();
    }

    public function refreshProgress()
    {
        $this->import->refresh();
        $this->loadRecentLogs();
    }

    public function loadRecentLogs()
    {
        if (!$this->import->log_file || !File::exists($this->import->log_file)) {
            return;
        }

        $file = fopen($this->import->log_file, 'r');
        if (!$file) {
            return;
        }

        // Skip to offset
        $currentLine = 0;
        while ($currentLine < $this->logOffset && fgets($file) !== false) {
            $currentLine++;
        }

        // Read next 100 lines
        $newLines = [];
        $linesRead = 0;
        while ($linesRead < 100 && ($line = fgets($file)) !== false) {
            $newLines[] = rtrim($line);
            $linesRead++;
            $currentLine++;
        }

        fclose($file);

        if (!empty($newLines)) {
            $this->logLines = array_merge($this->logLines, $newLines);
            $this->logOffset = $currentLine;

            // Keep only last 200 lines in memory
            if (count($this->logLines) > 200) {
                $this->logLines = array_slice($this->logLines, -200);
            }
        }
    }

    public function toggleAutoScroll()
    {
        $this->autoScroll = !$this->autoScroll;
    }

    public function cancelImport()
    {
        // Find and kill the background process if possible
        // This is a placeholder - actual implementation would need process tracking
        $this->import->update([
            'status' => 'cancelled',
            'status_message' => 'Import cancelled by user',
        ]);

        return redirect()->route('admin.import');
    }

    public function render()
    {
        return view('livewire.admin.import-progress');
    }
}
