<?php

namespace App\Services;

use App\Models\Council;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DemocracyClubService
{
    private string $baseUrl;

    private ?string $apiKey;

    private int $rateLimitMs;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.democracy_club.base_url', 'https://candidates.democracyclub.org.uk/api/v0.9'), '/');
        $this->apiKey = config('services.democracy_club.api_key');
        $this->rateLimitMs = $this->apiKey ? 50 : 200;
    }

    /**
     * Search Democracy Club organizations for a council matching the given name.
     * Returns the DC organization ID (e.g., 'party:53') or null.
     */
    public function findCouncilOrganization(Council $council): ?string
    {
        $searchTerms = [
            $council->name,
            preg_replace('/\bCouncil\b/i', '', $council->name),
        ];

        foreach (array_unique($searchTerms) as $term) {
            $term = trim($term);
            if (empty($term)) {
                continue;
            }

            $response = $this->get('/organizations/', ['name' => $term]);

            foreach ($response['results'] ?? [] as $org) {
                if (strcasecmp($org['name'] ?? '', $council->name) === 0) {
                    Log::info('Democracy Club organization found', [
                        'council' => $council->name,
                        'dc_org_id' => $org['id'],
                    ]);

                    return $org['id'];
                }
            }
        }

        Log::info('Democracy Club organization not found', ['council' => $council->name]);

        return null;
    }

    /**
     * Fetch all elected councillors for a council via Democracy Club.
     *
     * @return array<array> Array of councillor data ready for upsert.
     */
    public function fetchElectedCouncillors(Council $council): array
    {
        $councillors = [];

        // Gather ward GSS codes from our lookup table
        $wardGssCodes = \App\Models\WardHierarchyLookup::where('lad_code', $council->gss_code)
            ->pluck('wd_code')
            ->toArray();

        if (empty($wardGssCodes)) {
            Log::warning('No wards found for council in hierarchy lookups', [
                'council' => $council->name,
                'gss_code' => $council->gss_code,
            ]);

            return [];
        }

        foreach ($wardGssCodes as $wardGssCode) {
            $dcPostId = 'gss:' . $wardGssCode;

            $page = 1;
            while (true) {
                $response = $this->get('/memberships/', [
                    'elected' => 'true',
                    'post__id' => $dcPostId,
                    'page' => $page,
                    'page_size' => 100,
                ]);

                $results = $response['results'] ?? [];
                if (empty($results)) {
                    break;
                }

                foreach ($results as $membership) {
                    $person = $membership['person'] ?? null;
                    if (! $person) {
                        continue;
                    }

                    // Enrich with person details (email, photo, etc.)
                    $personDetails = $this->fetchPersonDetails((int) $person['id']);

                    $councillors[] = [
                        'council_gss_code' => $council->gss_code,
                        'ward_gss_code' => $wardGssCode,
                        'name' => $person['name'] ?? '',
                        'party' => $membership['on_behalf_of']['name'] ?? null,
                        'email' => $personDetails['email'] ?? null,
                        'phone' => $personDetails['phone'] ?? null,
                        'photo_url' => $personDetails['photo_url'] ?? null,
                        'profile_url' => $person['url'] ?? null,
                        'source' => 'democracy_club',
                        'scraped_at' => now(),
                    ];
                }

                if ($response['next'] === null) {
                    break;
                }

                $page++;
            }
        }

        Log::info('Democracy Club councillors fetched', [
            'council' => $council->name,
            'count' => count($councillors),
        ]);

        return $councillors;
    }

    /**
     * Fetch person details from DC to enrich email, photo, phone.
     *
     * @return array{email: ?string, phone: ?string, photo_url: ?string}
     */
    private function fetchPersonDetails(int $personId): array
    {
        $response = $this->get("/persons/{$personId}/");

        $email = null;
        $phone = null;
        $photoUrl = null;

        // Email from contact_details
        foreach ($response['contact_details'] ?? [] as $contact) {
            $type = $contact['type'] ?? null;
            if ($type === 'email' && ! empty($contact['value'])) {
                $email = $contact['value'];
            }
            if ($type === 'voice' && ! empty($contact['value'])) {
                $phone = $contact['value'];
            }
        }

        // Photo from image field or links
        if (! empty($response['image'])) {
            $photoUrl = $response['image'];
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'photo_url' => $photoUrl,
        ];
    }

    /**
     * Perform a GET request against the DC API with rate limiting.
     */
    private function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;

        $request = Http::timeout(15);

        if ($this->apiKey) {
            $request = $request->withHeader('Authorization', 'Token ' . $this->apiKey);
        }

        try {
            $response = $request->get($url, $params);

            if ($response->failed()) {
                Log::warning('Democracy Club API error', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            usleep($this->rateLimitMs * 1000);

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('Democracy Club API exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
