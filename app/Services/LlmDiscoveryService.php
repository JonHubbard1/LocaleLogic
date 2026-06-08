<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmDiscoveryService
{
    private string $baseUrl;
    private ?string $apiKey;
    private string $model;
    private string $driver;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.llm.base_url', 'http://localhost:11434'), '/');
        $this->apiKey = config('services.llm.api_key') ?: null;
        $this->model = config('services.llm.model', 'minimax-m2.7:cloud');
        $this->driver = config('services.llm.driver', 'ollama');
    }

    /**
     * Discover ModernGov base URLs for a batch of councils using an LLM.
     *
     * @param  array<array{name:string, gss_code:string}>  $councils
     * @return array<array{name:string, url:string, region:string}>
     */
    public function discoverForCouncils(array $councils): array
    {
        if ($this->driver === 'openai' && empty($this->apiKey)) {
            throw new \RuntimeException('LLM API key not configured. Set LLM_API_KEY in .env');
        }

        $councilNames = array_column($councils, 'name');
        $councilList = implode("\n", array_map(fn ($n) => "- {$n}", $councilNames));

        $systemPrompt = <<<PROMPT
You are a UK local government expert. Identify which councils use the ModernGov democracy system.

Known URL patterns (most common first):
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
12. {slug}meetings.info
13. {slug}meetings.org.uk
14. {slug}meetings.com
15. {slug}.info

Councils may also use custom domains not matching these patterns.

Output rules:
- Return ONLY a JSON array. No markdown, no explanations.
- Each object: {"name":"Council Name","url":"https://...","region":"London"}
- Omit councils that do NOT use ModernGov.
- If uncertain about a URL, include it anyway.
PROMPT;

        $userPrompt = "Which of these councils use ModernGov? Provide exact base URLs.\n\n{$councilList}\n\nReturn only the JSON array.";

        try {
            $response = $this->sendRequest($systemPrompt, $userPrompt);

            if (! $response->successful()) {
                Log::error('LLM discovery API error', [
                    'driver' => $this->driver,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $rawContent = $this->extractContent($response->json());

            return $this->parseJsonResponse($rawContent);
        } catch (\Throwable $e) {
            Log::error('LLM discovery exception', [
                'driver' => $this->driver,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Send the request using the appropriate driver format.
     */
    private function sendRequest(string $systemPrompt, string $userPrompt): \Illuminate\Http\Client\Response
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        if ($this->driver === 'ollama') {
            return Http::timeout(180)
                ->withHeaders($headers)
                ->post($this->baseUrl . '/api/chat', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.1,
                    ],
                ]);
        }

        // OpenAI-compatible format
        return Http::timeout(120)
            ->withHeaders($headers)
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.1,
                'max_tokens' => 4000,
            ]);
    }

    /**
     * Extract the assistant's content from the response body.
     */
    private function extractContent(?array $json): string
    {
        if (empty($json)) {
            return '';
        }

        // Ollama format: { message: { content: "..." } }
        if (isset($json['message']['content'])) {
            return $json['message']['content'];
        }

        // OpenAI format: { choices: [ { message: { content: "..." } } ] }
        return $json['choices'][0]['message']['content'] ?? '';
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

        // Some models wrap the whole response in brackets with extra text — try to isolate JSON
        if (! str_starts_with($cleaned, '[')) {
            if (preg_match('/(\[.*\])/s', $cleaned, $matches)) {
                $cleaned = $matches[1];
            }
        }

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
