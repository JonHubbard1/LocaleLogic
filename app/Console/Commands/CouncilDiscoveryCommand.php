<?php

namespace App\Console\Commands;

use App\Models\Council;
use App\Services\CouncilDiscoveryService;
use App\Services\DemocracyClubService;
use Illuminate\Console\Command;

class CouncilDiscoveryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'councils:discover
                            {gssCode? : Optional GSS code of a single council to discover}
                            {--batch=20 : Number of councils to process in this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover ModernGov and Democracy Club availability for councils';

    /**
     * Execute the console command.
     */
    public function handle(CouncilDiscoveryService $councilService, DemocracyClubService $dcService): int
    {
        $gssCode = $this->argument('gssCode');
        $batch = (int) $this->option('batch');

        if ($gssCode) {
            $council = Council::findByGssCode($gssCode);

            if (! $council) {
                $this->error("Council {$gssCode} not found.");

                return self::FAILURE;
            }

            $this->info("Discovering {$council->name}...");

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

            $this->info('ModernGov: ' . ($mgResult['uses_modern_gov'] ? 'Yes' : 'No'));
            $this->info('Democracy Club: ' . ($dcOrgId ? 'Yes (' . $dcOrgId . ')' : 'No'));

            return self::SUCCESS;
        }

        $query = Council::whereNull('uses_modern_gov')
            ->orWhereNull('uses_democracy_club')
            ->orWhereNull('scraped_at');

        $count = $query->count();

        if ($count === 0) {
            $this->info('All councils have already been discovered.');

            return self::SUCCESS;
        }

        $councils = $query->limit($batch)->get();
        $this->info("Discovering {$councils->count()} of {$count} remaining councils...");

        $mgDetected = 0;
        $dcDetected = 0;
        $notDetected = 0;

        foreach ($councils as $council) {
            $this->output->write("Probing {$council->name}... ");

            // ModernGov
            $mgResult = $councilService->discover($council);

            $updateData = [];
            if ($mgResult['uses_modern_gov'] || ! $council->modern_gov_base_url) {
                $updateData['uses_modern_gov'] = $mgResult['uses_modern_gov'];
                $updateData['modern_gov_base_url'] = $mgResult['modern_gov_base_url'];
                $updateData['democracy_url'] = $mgResult['democracy_url'];
            }

            if (! empty($updateData)) {
                $council->update($updateData);
            }

            if ($mgResult['uses_modern_gov']) {
                $mgDetected++;
            }

            // Democracy Club
            $dcOrgId = $dcService->findCouncilOrganization($council);
            $council->update([
                'uses_democracy_club' => $dcOrgId !== null,
                'democracy_club_org_id' => $dcOrgId,
                'scraped_at' => now(),
            ]);

            if ($dcOrgId) {
                $dcDetected++;
            }

            if (! $mgResult['uses_modern_gov'] && ! $dcOrgId) {
                $notDetected++;
            }

            $mgLabel = $mgResult['uses_modern_gov'] ? 'MGov' : '--';
            $dcLabel = $dcOrgId ? 'DC' : '--';
            $this->output->writeln("[{$mgLabel}|{$dcLabel}]");
        }

        $this->newLine();
        $this->info("Done. ModernGov: {$mgDetected}, Democracy Club: {$dcDetected}, Neither: {$notDetected}, Remaining: " . max(0, $count - $councils->count()));

        return self::SUCCESS;
    }
}
