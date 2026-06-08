<?php

namespace App\Services;

use App\Models\Council;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CouncilDiscoveryService
{
    /**
     * Known ModernGov hosted domain patterns.
     */
    private array $modernGovHosts = [
        'moderngov.co.uk',
        'govserv.com',
    ];

    /**
     * Sub-paths to probe once we have a candidate base URL.
     */
    private array $probePaths = [
        '/mgWebService.asmx?WSDL',
        '/mgMemberIndex.aspx',
        '/mgFindMember.aspx',
    ];

    /**
     * Attempt to discover whether a council uses ModernGov.
     * Uses multiple strategies in order of reliability.
     */
    public function discover(Council $council): array
    {
        $result = [
            'uses_modern_gov' => false,
            'modern_gov_base_url' => null,
            'democracy_url' => null,
        ];

        // Strategy 1: If a URL is already known, verify it first
        if ($council->modern_gov_base_url) {
            $verified = $this->verifyModernGovUrl($council->modern_gov_base_url);
            if ($verified) {
                return [
                    'uses_modern_gov' => true,
                    'modern_gov_base_url' => rtrim($council->modern_gov_base_url, '/'),
                    'democracy_url' => $council->modern_gov_base_url . '/mgMemberIndex.aspx',
                ];
            }
        }

        // Strategy 2: Scrape the council's official website for ModernGov links
        if ($council->website_url) {
            $found = $this->scrapeWebsiteForModernGov($council->website_url);
            if ($found) {
                return $found;
            }
        }

        // Strategy 3: Try known ModernGov hosted domains (e.g. .moderngov.co.uk)
        $hostedResult = $this->probeModernGovHosts($council);
        if ($hostedResult) {
            return $hostedResult;
        }

        // Strategy 4: Try democracy subdomain and common path variations
        $candidateResult = $this->probeCandidateUrls($council);
        if ($candidateResult) {
            return $candidateResult;
        }

        return $result;
    }

    /**
     * Strategy 2: Scrape the council's website homepage for links to ModernGov.
     * Most councils that use ModernGov link to it from their "Councillors" or
     * "Democracy" page.
     */
    private function scrapeWebsiteForModernGov(string $websiteUrl): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->get($websiteUrl);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            // Look for ModernGov links in the HTML
            $patterns = [
                // Direct modgov/moderngov links
                '/href=["\'](https?:\/\/[^"\']*modgov[^"\']*)["\']/i',
                '/href=["\'](https?:\/\/[^"\']*moderngov[^"\']*)["\']/i',
                // mgMemberIndex / mgWebService
                '/href=["\'](https?:\/\/[^"\']*mgMemberIndex[^"\']*)["\']/i',
                '/href=["\'](https?:\/\/[^"\']*mgWebService[^"\']*)["\']/i',
                // Democracy pages that might redirect to ModernGov
                '/href=["\'](https?:\/\/[^"\']*democracy[^"\']*councillor[^"\']*)["\']/i',
                '/href=["\'](https?:\/\/[^"\']*councillor[^"\']*democracy[^"\']*)["\']/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $body, $matches)) {
                    $candidateUrl = $matches[1];
                    $baseUrl = $this->extractBaseUrl($candidateUrl);

                    if ($baseUrl && $this->verifyModernGovUrl($baseUrl)) {
                        Log::info('ModernGov discovered via website scrape', [
                            'council' => parse_url($websiteUrl, PHP_URL_HOST),
                            'url' => $baseUrl,
                        ]);

                        return [
                            'uses_modern_gov' => true,
                            'modern_gov_base_url' => $baseUrl,
                            'democracy_url' => $baseUrl . '/mgMemberIndex.aspx',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Website scrape failed', ['url' => $websiteUrl, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Strategy 3: Probe known ModernGov hosted domains like .moderngov.co.uk.
     * Many councils are hosted by Civica on shared subdomains.
     */
    private function probeModernGovHosts(Council $council): ?array
    {
        $slugs = $this->buildHostedSlugs($council);

        foreach ($slugs as $slug) {
            foreach ($this->modernGovHosts as $host) {
                $baseUrl = "https://{$slug}.{$host}";

                if ($this->verifyModernGovUrl($baseUrl)) {
                    Log::info('ModernGov discovered on hosted domain', [
                        'council' => $council->name,
                        'url' => $baseUrl,
                    ]);

                    return [
                        'uses_modern_gov' => true,
                        'modern_gov_base_url' => $baseUrl,
                        'democracy_url' => $baseUrl . '/mgMemberIndex.aspx',
                    ];
                }

                usleep(100_000); // 100 ms between probes
            }
        }

        return null;
    }

    /**
     * Strategy 4: Try common URL patterns derived from the council name/website.
     */
    private function probeCandidateUrls(Council $council): ?array
    {
        $candidates = $this->buildCandidateUrls($council);

        foreach ($candidates as $baseUrl) {
            $baseUrl = rtrim($baseUrl, '/');

            if ($this->verifyModernGovUrl($baseUrl)) {
                return [
                    'uses_modern_gov' => true,
                    'modern_gov_base_url' => $baseUrl,
                    'democracy_url' => $baseUrl . '/mgMemberIndex.aspx',
                ];
            }

            usleep(100_000); // 100 ms between probes
        }

        return null;
    }

    /**
     * Verify that a URL hosts a ModernGov installation.
     */
    private function verifyModernGovUrl(string $url): bool
    {
        try {
            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->get(rtrim($url, '/') . '/mgWebService.asmx?WSDL');

            if ($response->successful()) {
                $body = strtolower($response->body());
                return str_contains($body, 'wsdl:definitions')
                    || str_contains($body, 'mgwebservice')
                    || str_contains($body, 'targetnamespace');
            }
        } catch (\Throwable $e) {
            Log::debug('ModernGov verification failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Extract the base URL from a full ModernGov link.
     * E.g. "https://lbbd.moderngov.co.uk/mgMemberIndex.aspx" -> "https://lbbd.moderngov.co.uk"
     */
    private function extractBaseUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (! isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        $base = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }

        return $base;
    }

    /**
     * Build candidate hosted-domain slugs for a council.
     */
    private function buildHostedSlugs(Council $council): array
    {
        $slugs = [];
        $name = strtolower($council->name);

        // Common abbreviations
        $abbreviations = [
            'london borough of barking and dagenham' => ['lbbd'],
            'london borough of barnet' => ['barnet'],
            'london borough of bexley' => ['bexley'],
            'london borough of brent' => ['brent'],
            'london borough of bromley' => ['bromley'],
            'london borough of camden' => ['camden'],
            'london borough of croydon' => ['croydon'],
            'london borough of ealing' => ['ealing'],
            'london borough of enfield' => ['enfield'],
            'london borough of greenwich' => ['greenwich'],
            'london borough of hackney' => ['hackney'],
            'london borough of hammersmith and fulham' => ['lbhf'],
            'london borough of haringey' => ['haringey'],
            'london borough of harrow' => ['harrow'],
            'london borough of havering' => ['havering'],
            'london borough of hillingdon' => ['hillingdon'],
            'london borough of hounslow' => ['hounslow'],
            'london borough of islington' => ['islington'],
            'london borough of kensington and chelsea' => ['rbkc'],
            'royal borough of kensington and chelsea' => ['rbkc'],
            'london borough of kingston upon thames' => ['kingston'],
            'london borough of lambeth' => ['lambeth'],
            'london borough of lewisham' => ['lewisham'],
            'london borough of merton' => ['merton'],
            'london borough of newham' => ['newham'],
            'london borough of redbridge' => ['redbridge'],
            'london borough of richmond upon thames' => ['richmond'],
            'london borough of southwark' => ['southwark'],
            'london borough of sutton' => ['sutton'],
            'london borough of tower hamlets' => ['towerhamlets'],
            'london borough of waltham forest' => ['walthamforest'],
            'london borough of wandsworth' => ['wandsworth'],
            'city of westminster' => ['westminster'],
            'city of london' => ['cityoflondon'],
        ];

        foreach ($abbreviations as $pattern => $patternSlugs) {
            if (str_contains($name, $pattern)) {
                foreach ($patternSlugs as $slug) {
                    $slugs[] = $slug;
                }
            }
        }

        // Also try the generic slug
        $genericSlug = $this->slugify($council->name);
        if ($genericSlug) {
            $slugs[] = $genericSlug;
        }

        // Extract from existing website URL
        if ($council->website_url) {
            $host = parse_url($council->website_url, PHP_URL_HOST);
            if ($host) {
                $hostSlug = preg_replace('/\.(gov\.uk|org\.uk|com)$/', '', $host);
                $hostSlug = preg_replace('/^(www\.|democracy\.|cms\.|cmis\.)/', '', $hostSlug);
                if ($hostSlug && ! in_array($hostSlug, $slugs, true)) {
                    $slugs[] = $hostSlug;
                }
            }
        }

        return array_unique($slugs);
    }

    /**
     * Build candidate base URLs from the council name and website.
     */
    private function buildCandidateUrls(Council $council): array
    {
        $candidates = [];

        // If the council already has a website URL, try to derive a democracy subdomain
        if ($council->website_url) {
            $parsed = parse_url($council->website_url);
            if (isset($parsed['host'])) {
                $host = $parsed['host'];
                $scheme = $parsed['scheme'] ?? 'https';

                $candidates[] = "{$scheme}://{$host}";

                // Try democracy subdomain
                $hostParts = explode('.', $host);
                if (count($hostParts) > 2) {
                    $hostParts[0] = 'democracy';
                    $candidates[] = "{$scheme}://" . implode('.', $hostParts);
                }
            }
        }

        // Build from council name heuristics
        $slug = $this->slugify($council->name);
        $candidates[] = "https://democracy.{$slug}.gov.uk";
        $candidates[] = "https://{$slug}.gov.uk";
        $candidates[] = "https://www.{$slug}.gov.uk";

        return array_unique($candidates);
    }

    /**
     * Convert a council name into a URL-friendly slug.
     */
    private function slugify(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/\bcouncil\b/', '', $name);
        $name = preg_replace('/\bcity of\b/', '', $name);
        $name = preg_replace('/\blondon borough of\b/', '', $name);
        $name = preg_replace('/\broyal borough of\b/', '', $name);
        $name = preg_replace('/[^a-z0-9\s-]/', '', $name);
        $name = preg_replace('/\s+/', '-', trim($name));

        return $name;
    }
}
