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
     * Two-step approach:
     *  1. Find links with text like "Councillors", "Democracy", "Committees"
     *  2. Follow those links and verify if the target is ModernGov
     * Also keeps the old URL-pattern matching as a fallback.
     */
    private function scrapeWebsiteForModernGov(string $websiteUrl): ?array
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withOptions(['verify' => false])
                ->get($websiteUrl);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            // Step 1: Look for links by text content and follow them
            $linkTexts = [
                'your councillors',
                'councillors',
                'council',
                'democracy',
                'committees',
                'meetings',
                'decision making',
                'decisions',
                'council meetings',
                'council business',
                'governance',
            ];

            $dom = new \DOMDocument;
            @$dom->loadHTML($body);

            /** @var \DOMElement $link */
            foreach ($dom->getElementsByTagName('a') as $link) {
                $text = strtolower(trim($link->textContent));

                foreach ($linkTexts as $search) {
                    if (str_contains($text, $search)) {
                        $href = $link->getAttribute('href');
                        if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                            continue;
                        }

                        // Resolve relative URLs
                        $resolved = $this->resolveUrl($websiteUrl, $href);
                        if (! $resolved) {
                            continue;
                        }

                        // Quick follow — check if the linked page is ModernGov
                        $baseUrl = $this->extractBaseUrl($resolved);
                        if ($baseUrl && $this->verifyModernGovUrl($baseUrl)) {
                            Log::info('ModernGov discovered via website link follow', [
                                'council' => parse_url($websiteUrl, PHP_URL_HOST),
                                'link_text' => $text,
                                'url' => $baseUrl,
                            ]);

                            return [
                                'uses_modern_gov' => true,
                                'modern_gov_base_url' => $baseUrl,
                                'democracy_url' => $baseUrl . '/mgMemberIndex.aspx',
                            ];
                        }

                        // If the link itself doesn't verify, try the homepage
                        // of the linked domain (in case it redirected)
                        $parsed = parse_url($resolved);
                        if (isset($parsed['scheme'], $parsed['host'])) {
                            $linkedHome = $parsed['scheme'] . '://' . $parsed['host'];
                            if ($linkedHome !== rtrim($websiteUrl, '/') && $this->verifyModernGovUrl($linkedHome)) {
                                Log::info('ModernGov discovered via linked domain homepage', [
                                    'council' => parse_url($websiteUrl, PHP_URL_HOST),
                                    'link_text' => $text,
                                    'url' => $linkedHome,
                                ]);

                                return [
                                    'uses_modern_gov' => true,
                                    'modern_gov_base_url' => $linkedHome,
                                    'democracy_url' => $linkedHome . '/mgMemberIndex.aspx',
                                ];
                            }
                        }
                    }
                }
            }

            // Fallback: look for direct URL patterns in the raw HTML
            $urlPatterns = [
                '/href=["\'](https?:\/\/[^"\']*modgov[^"\']*)["\']/i',
                '/href=["\'](https?:\/\/[^"\']*moderngov[^"\']*)["\']/i',
                '/href=["\'](https?:\/\/[^"\']*mgMemberIndex[^"\']*)["\']/i',
                '/href=["\'](https?:\/\/[^"\']*mgWebService[^"\']*)["\']/i',
            ];

            foreach ($urlPatterns as $pattern) {
                if (preg_match($pattern, $body, $matches)) {
                    $baseUrl = $this->extractBaseUrl($matches[1]);

                    if ($baseUrl && $this->verifyModernGovUrl($baseUrl)) {
                        Log::info('ModernGov discovered via URL pattern in HTML', [
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
     * Tries the WSDL first, then falls back to the member index page
     * and looks for ModernGov-specific signatures.
     */
    public function verifyModernGovUrl(string $url): bool
    {
        $base = rtrim($url, '/');

        // Try the WSDL endpoint first
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withOptions(['verify' => false])
                ->get($base . '/mgWebService.asmx?WSDL');

            if ($response->successful()) {
                $body = strtolower($response->body());
                if (str_contains($body, 'wsdl:definitions')
                    || str_contains($body, 'mgwebservice')
                    || str_contains($body, 'targetnamespace')) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('ModernGov WSDL probe failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        // Fall back to the member index page and look for signatures
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withOptions(['verify' => false])
                ->get($base . '/mgMemberIndex.aspx');

            if ($response->successful()) {
                $body = strtolower($response->body());
                $signatures = [
                    '$moderngov',
                    'mgmemberindex',
                    'modern.gov reverse cms',
                    'mgmemberindex.aspx',
                    'mgcalendar',
                    'mgplanshome',
                    'mgfindmember',
                    'modgov specifics',
                    'mg.jqueryaddons',
                    'ssmgstyles.css',
                ];
                foreach ($signatures as $sig) {
                    if (str_contains($body, $sig)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('ModernGov member-index probe failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Resolve a (possibly relative) URL against a base URL.
     */
    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $baseParsed = parse_url(rtrim($baseUrl, '/'));
        if (! isset($baseParsed['scheme'], $baseParsed['host'])) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return $baseParsed['scheme'] . '://' . $baseParsed['host'] . $href;
        }

        $path = $baseParsed['path'] ?? '';
        $dir = $path ? dirname($path) : '';

        return $baseParsed['scheme'] . '://' . $baseParsed['host'] . $dir . '/' . $href;
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

        // Common abbreviations — match on short name or full name
        $abbreviations = [
            'barking and dagenham' => ['lbbd'],
            'basildon' => ['basildon'],
            'barnet' => ['barnet'],
            'bexley' => ['bexley'],
            'brent' => ['brent'],
            'bromley' => ['bromley'],
            'camden' => ['camden'],
            'croydon' => ['croydon'],
            'ealing' => ['ealing'],
            'enfield' => ['enfield'],
            'greenwich' => ['greenwich'],
            'hackney' => ['hackney'],
            'hammersmith and fulham' => ['lbhf'],
            'haringey' => ['haringey'],
            'harrow' => ['harrow'],
            'havering' => ['havering'],
            'hillingdon' => ['hillingdon'],
            'hounslow' => ['hounslow'],
            'islington' => ['islington'],
            'kensington and chelsea' => ['rbkc'],
            'kingston upon thames' => ['kingston'],
            'lambeth' => ['lambeth'],
            'lewisham' => ['lewisham'],
            'merton' => ['merton'],
            'newham' => ['newham'],
            'redbridge' => ['redbridge'],
            'richmond upon thames' => ['richmond'],
            'southwark' => ['southwark'],
            'sutton' => ['sutton'],
            'tower hamlets' => ['towerhamlets'],
            'waltham forest' => ['walthamforest'],
            'wandsworth' => ['wandsworth'],
            'westminster' => ['westminster'],
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

        // Additional TLDs and patterns some councils use
        $candidates[] = "https://{$slug}.moderngov.co.uk";
        $candidates[] = "https://{$slug}meetings.info";
        $candidates[] = "https://www.{$slug}meetings.info";
        $candidates[] = "https://{$slug}.info";
        $candidates[] = "https://www.{$slug}.info";
        $candidates[] = "https://{$slug}meetings.org.uk";
        $candidates[] = "https://{$slug}meetings.com";

        return array_unique($candidates);
    }

    /**
     * Convert a council name into a URL-friendly slug.
     */
    private function slugify(string $name): string
    {
        $name = strtolower($name);
        // Strip common administrative words
        $name = preg_replace('/\bcouncil\b/', '', $name);
        $name = preg_replace('/\bcity of\b/', '', $name);
        $name = preg_replace('/\blondon borough of\b/', '', $name);
        $name = preg_replace('/\broyal borough of\b/', '', $name);
        $name = preg_replace('/\bborough of\b/', '', $name);
        $name = preg_replace('/\bdistrict\b/', '', $name);
        $name = preg_replace('/\bcounty\b/', '', $name);
        $name = preg_replace('/\bmetropolitan\b/', '', $name);
        $name = preg_replace('/\bunitary\b/', '', $name);
        $name = preg_replace('/\bthe\b/', '', $name);
        $name = preg_replace('/\band\b/', '', $name);
        $name = preg_replace('/\bof\b/', '', $name);
        $name = preg_replace('/[^a-z0-9\s-]/', '', $name);
        $name = preg_replace('/\s+/', '-', trim($name));

        return $name;
    }
}
