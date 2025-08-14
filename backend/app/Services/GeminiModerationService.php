<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class GeminiModerationService
{
    private string $apiKey;

    private string $model;

    private string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key not configured');
        }

        $this->endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";
    }

    /**
     * Moderate content
     */
    public function moderate(string $content): array
    {
        try {
            return $this->parseResponse(
                $this->makeRequest($content)
            );
        } catch (Exception $e) {
            logger()->error('Gemini moderation failed: '.$e->getMessage());

            return [
                'approved' => false,
                'reason' => 'Service unavailable',
                'error' => true,
            ];
        }
    }

    /** Make API request. */
    private function makeRequest(string $content): array
    {
        $prompt = 'Analyze this content for spam, toxicity, hate speech, violence, adult content. 
                   Respond with JSON: {
                     "approved": boolean, 
                     "categories": ["list of violated categories"], 
                     "severity": "low|medium|high", 
                     "confidence": 0.0-1.0, 
                     "reason": "brief explanation"
                   }';

        $response = Http::timeout(10)
            ->withOptions(['headers' => ['Connection' => 'close']])
            ->post($this->endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt."\n\nContent: ".$content],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 500,
                    'responseMimeType' => 'application/json',
                ],
                'safetySettings' => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ],
            ]);

        if (! $response->successful()) {
            throw new Exception('API error: '.$response->body());
        }

        return $response->json();
    }

    /** Parse API response */
    private function parseResponse(array $response): array
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            throw new Exception('Empty response');
        }

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }

        return [
            'approved' => $result['approved'] ?? false,
            'categories' => $result['categories'] ?? [],
            'severity' => $result['severity'] ?? 'medium',
            'confidence' => $result['confidence'] ?? null,
            'reason' => $result['reason'] ?? 'Unknown',
            'error' => false,
        ];
    }
}
