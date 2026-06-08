<?php

namespace App\Jobs;

use App\Models\Council;
use App\Services\CouncilDiscoveryService;
use App\Services\DemocracyClubService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverCouncilSystemJob implements ShouldQueue
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
    public function handle(CouncilDiscoveryService $councilService, DemocracyClubService $dcService): void
    {
        $council = Council::findByGssCode($this->councilGssCode);

        if (! $council) {
            Log::warning('DiscoverCouncilSystemJob: council not found', ['gss_code' => $this->councilGssCode]);

            return;
        }

        // ModernGov probe
        $mgResult = $councilService->discover($council);

        // Only overwrite if discovery found something, or if the field was empty
        $updateData = [];
        if ($mgResult['uses_modern_gov'] || ! $council->modern_gov_base_url) {
            $updateData['uses_modern_gov'] = $mgResult['uses_modern_gov'];
            $updateData['modern_gov_base_url'] = $mgResult['modern_gov_base_url'];
            $updateData['democracy_url'] = $mgResult['democracy_url'];
        }

        if (! empty($updateData)) {
            $council->update($updateData);
        }

        // Democracy Club probe
        $dcOrgId = $dcService->findCouncilOrganization($council);
        $council->update([
            'uses_democracy_club' => $dcOrgId !== null,
            'democracy_club_org_id' => $dcOrgId,
            'scraped_at' => now(),
        ]);

        Log::info('DiscoverCouncilSystemJob complete', [
            'council' => $council->name,
            'uses_modern_gov' => $mgResult['uses_modern_gov'],
            'uses_democracy_club' => $dcOrgId !== null,
        ]);
    }
}
