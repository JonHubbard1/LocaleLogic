<?php

namespace App\Jobs;

use App\Models\Council;
use App\Services\CouncillorImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportCouncillorJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $councilGssCode)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(CouncillorImportService $service): void
    {
        $council = Council::findByGssCode($this->councilGssCode);

        if (! $council) {
            Log::warning('ImportCouncillorJob: council not found', ['gss_code' => $this->councilGssCode]);

            return;
        }

        $result = $service->importForCouncil($council);

        Log::info('ImportCouncillorJob complete', [
            'council' => $council->name,
            'inserted' => $result['inserted'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ]);
    }
}
