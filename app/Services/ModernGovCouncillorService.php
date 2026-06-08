<?php

namespace App\Services;

use App\Models\Council;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class ModernGovCouncillorService
{
    /**
     * Fetch councillors by ward from a ModernGov SOAP endpoint.
     */
    public function fetchCouncillorsByWard(Council $council): array
    {
        if (! $council->modern_gov_base_url) {
            return [];
        }

        $url = rtrim($council->modern_gov_base_url, '/') . '/mgWebService.asmx/GetCouncillorsByWard';

        try {
            $response = Http::timeout(20)
                ->withOptions(['verify' => false])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('ModernGov councillor fetch failed', [
                    'council' => $council->name,
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $this->parseCouncillorsXml($response->body(), $council->gss_code);
        } catch (\Throwable $e) {
            Log::error('ModernGov councillor fetch exception', [
                'council' => $council->name,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse the ModernGov XML response into a plain array.
     *
     * ModernGov returns XML like:
     * <;councillorsbyward>;
     *   <;wards>;
     *     <;ward>;
     *       <;wardtitle>;Anchorsholme<;/wardtitle>;
     *       <;councillors>;
     *         <;councillor>;
     *           <;fullusername>;Councillor Anita Cooper<;/fullusername>;
     *           <;politicalpartytitle>;Conservative<;/politicalpartytitle>;
     *           <;photosmallurl>;http://...<;/photosmallurl>;
     *           <;workaddress>;
     *             <;email>;...@blackpool.gov.uk<;/email>;
     *             <;mobile>;07427 619704<;/mobile>;
     *           <;/workaddress>;
     *         <;/councillor>;
     *       <;/councillors>;
     *     <;/ward>;
     *   <;/wards>;
     * <;/councillorsbyward>;
     */
    private function parseCouncillorsXml(string $xml, string $councilGssCode): array
    {
        $councillors = [];

        try {
            // ModernGov sometimes returns HTML-wrapped XML; try to extract inner XML
            if (str_contains($xml, '&lt;')) {
                $xml = html_entity_decode($xml);
            }

            $doc = new SimpleXMLElement($xml);

            // Pre-load ward lookups for this council
            $wardLookups = \App\Models\WardHierarchyLookup::where('lad_code', $councilGssCode)
                ->get()
                ->mapWithKeys(fn ($w) => [strtolower($w->wd_name) => $w->wd_code]);

            foreach ($doc->xpath('//ward') ?? [] as $wardNode) {
                $wardName = (string) ($wardNode->wardtitle ?? '');
                $wardGssCode = null;

                // Attempt to match ward name to our hierarchy lookups
                if ($wardName) {
                    $lookupCode = $wardLookups->get(strtolower($wardName));
                    if ($lookupCode) {
                        $wardGssCode = $lookupCode;
                    }
                }

                foreach ($wardNode->xpath('.//councillor') ?? [] as $councillorNode) {
                    $name = (string) ($councillorNode->fullusername ?? '');
                    if (empty($name)) {
                        continue;
                    }

                    // Extract email from workaddress or homeaddress
                    $email = null;
                    $workEmail = (string) ($councillorNode->workaddress->email ?? '');
                    $homeEmail = (string) ($councillorNode->homeaddress->email ?? '');
                    $email = $workEmail ?: $homeEmail ?: null;

                    // Extract phone from workaddress or homeaddress
                    $phone = null;
                    $workPhone = (string) ($councillorNode->workaddress->phone ?? '');
                    $workMobile = (string) ($councillorNode->workaddress->mobile ?? '');
                    $homePhone = (string) ($councillorNode->homeaddress->phone ?? '');
                    $homeMobile = (string) ($councillorNode->homeaddress->mobile ?? '');
                    $phone = $workPhone ?: $workMobile ?: $homePhone ?: $homeMobile ?: null;

                    // Photo URL — prefer big, fall back to small
                    $photoUrl = null;
                    $bigPhoto = (string) ($councillorNode->photobigurl ?? '');
                    $smallPhoto = (string) ($councillorNode->photosmallurl ?? '');
                    $photoUrl = $bigPhoto ?: $smallPhoto ?: null;

                    // Build profile URL from council base + councillor ID
                    $profileUrl = null;
                    $councillorId = (string) ($councillorNode->councillorid ?? '');
                    if ($councillorId) {
                        $profileUrl = $this->buildProfileUrl($councilGssCode, $councillorId);
                    }

                    $councillors[] = [
                        'council_gss_code' => $councilGssCode,
                        'ward_gss_code' => $wardGssCode,
                        'name' => $name,
                        'party' => (string) ($councillorNode->politicalpartytitle ?? ''),
                        'email' => $email,
                        'phone' => $phone,
                        'photo_url' => $photoUrl,
                        'profile_url' => $profileUrl,
                        'source' => 'modern_gov',
                        'scraped_at' => now(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('ModernGov XML parse failed', [
                'council_gss_code' => $councilGssCode,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('ModernGov councillors fetched', [
            'council_gss_code' => $councilGssCode,
            'count' => count($councillors),
        ]);

        return $councillors;
    }

    /**
     * Build a councillor profile URL from the council's base URL and councillor ID.
     */
    private function buildProfileUrl(string $councilGssCode, string $councillorId): ?string
    {
        $council = Council::findByGssCode($councilGssCode);
        if (! $council || ! $council->modern_gov_base_url) {
            return null;
        }

        return rtrim($council->modern_gov_base_url, '/') . '/mgUserInfo.aspx?UID=' . $councillorId;
    }
}
