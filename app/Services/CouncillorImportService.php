<?php

namespace App\Services;

use App\Models\Council;
use App\Models\Councillor;
use Illuminate\Support\Facades\Log;

class CouncillorImportService
{
    public function __construct(
        private ModernGovCouncillorService $modernGovService,
        private DemocracyClubService $democracyClubService,
    ) {
    }

    /**
     * Import councillors for a single council.
     *
     * NOTE: Democracy Club v0.9 API list filtering is broken — the `post__id`
     * and `elected` query parameters are ignored, returning random memberships
     * from across the entire country. DC import is therefore disabled pending a
     * rewrite using the postcode-based Developers API v1.
     *
     * @param  string  $source  'auto', 'democracy_club', or 'modern_gov'
     * @return array{dc_inserted: int, mg_inserted: int, skipped: int, errors: array<string>}
     */
    public function importForCouncil(Council $council, string $source = 'modern_gov'): array
    {
        $results = [
            'dc_inserted' => 0,
            'mg_inserted' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Democracy Club v0.9 API list filtering is broken — disabled.
        if ($source === 'democracy_club') {
            Log::warning('Democracy Club import disabled: v0.9 API does not support reliable ward-level filtering');

            return $results;
        }

        if ($source === 'auto') {
            $source = 'modern_gov';
        }

        // ModernGov
        if ($source === 'modern_gov' && $council->uses_modern_gov && $council->modern_gov_base_url) {
            $mgCouncillors = $this->modernGovService->fetchCouncillorsByWard($council);

            foreach ($mgCouncillors as $data) {
                try {
                    if (empty($data['name'])) {
                        $results['skipped']++;
                        continue;
                    }

                    Councillor::upsert(
                        [$data],
                        ['ward_gss_code', 'name', 'source'],
                        ['party', 'email', 'phone', 'photo_url', 'profile_url', 'scraped_at']
                    );

                    $results['mg_inserted']++;
                } catch (\Throwable $e) {
                    $results['errors'][] = $e->getMessage();
                    Log::error('ModernGov councillor import failed', [
                        'council' => $council->name,
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }
}
