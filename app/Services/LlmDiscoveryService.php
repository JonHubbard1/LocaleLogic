<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmDiscoveryService
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.llm.base_url', 'https://api.openai.com/v1'), '/');
        $this->apiKey = config('services.llm.api_key');
        $this->model = config('services.llm.model', 'gpt-4o-mini');
    }

    /**
     * Discover ModernGov base URLs for a batch of councils using an LLM.
     *
     * @param  array<array{name:string, gss_code:string}>  $councils
     * @return array<array{name:string, url:string, region:string}>
     */
    public function discoverForCouncils(array $councils): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('LLM API key not configured. Set LLM_API_KEY in .env');
        }

        $councilNames = array_column($councils, 'name');
        $councilList = implode("\n", array_map(fn ($n) => "- {$n}", $councilNames));

        $systemPrompt = <<<PROMPT
You are a UK local government expert. Your task is to identify which councils use the ModernGov democracy system and provide their exact base URLs.

ModernGov URLs follow these patterns (most to least common):
1. {slug}.moderngov.co.uk
2. democracy.{slug}.gov.uk
3. committees.{slug}.gov.uk
4. cms.{slug}.gov.uk
5. moderngov.{slug}.gov.uk
6. mycouncil.{slug}.gov.uk
7. decisions.{slug}.gov.uk
8. mgov.{slug}.gov.uk
9. cmis.{slug}.gov.uk
10. democratic.{slug}.gov.uk
11. present.{slug}.gov.uk

Return ONLY a raw JSON array. No markdown, no explanations, no prose.
Each object must have keys: "name" (exact full official name), "url" (the ModernGov base URL, e.g. https://lbbd.moderngov.co.uk), "region" (one of: London, South, East, Midlands, North, Yorkshire, Scotland, Wales, Northern Ireland).

If a council does NOT use ModernGov, omit it from the array entirely.
If you are uncertain about a URL, include it anyway — wrong URLs can be corrected later.
PROMPT;

        $userPrompt = "For each of the following UK councils, determine if they use ModernGov and provide the exact base URL.\n\n{$councilList}\n\nReturn only the JSON array.";

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . '/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 4000,
                ]);

            if (! $response->successful()) {
                Log::error('LLM discovery API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $json = $response->json();
            $rawContent = $json['choices'][0]['message']['content'] ?? '';

            return $this->parseJsonResponse($rawContent);
        } catch (\Throwable $e) {
            Log::error('LLM discovery exception', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Strip markdown fencing and parse the JSON array.
     *
     * @return array<array{name:string, url:string, region:string}>
     */
    private function parseJsonResponse(string $rawContent): array
    {
        $cleaned = trim($rawContent);

        // Strip markdown code fences
        if (str_starts_with($cleaned, '```json')) {
            $cleaned = preg_replace('/^```json\s*/', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        } elseif (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```\s*/', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        }

        $cleaned = trim($cleaned);

        try {
            $decoded = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                Log::warning('LLM response was not a JSON array', ['content' => $cleaned]);

                return [];
            }

            // Validate and filter entries
            return array_values(array_filter($decoded, fn ($item) =>
                is_array($item)
                && ! empty($item['name'])
                && ! empty($item['url'])
                && filter_var($item['url'], FILTER_VALIDATE_URL)
            ));
        } catch (\JsonException $e) {
            Log::warning('LLM response JSON parse failed', [
                'content' => $cleaned,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
