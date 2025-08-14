<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiModerationService
{
    private string $apiKey;

    public private(set) string $model;

    public private(set) string $endpoint;

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
            Log::error('Gemini moderation failed: '.$e->getMessage());

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

        $responseData = $response->json();

        if ($responseData === null) {
            throw new Exception('Empty or invalid response from API');
        }

        return $responseData;
    }

    /** Parse API response */
    private function parseResponse(array $response): array
    {
        if (isset($response['error'])) {
            throw new Exception('API error: '.($response['error']['message'] ?? 'Unknown error'));
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            throw new Exception('Empty response');
        }

        $result = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: '.$text);
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

    public static function fakeAnswers(?string $type = null): array
    {
        $responses = match ($type) {
            'approved' => [
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'approved' => true,
                                        'categories' => [],
                                        'severity' => 'low',
                                        'confidence' => 0.95,
                                        'reason' => 'Content is safe',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'rejected' => [
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'approved' => false,
                                        'categories' => ['spam', 'hate_speech'],
                                        'severity' => 'high',
                                        'confidence' => 0.99,
                                        'reason' => 'Content violates community standards',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'error' => [
                'error' => [
                    'code' => 500,
                    'message' => 'Internal server error',
                    'status' => 'INTERNAL',
                ],
            ],
            'invalid_json' => [
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Invalid JSON response',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'empty_response' => [
                'candidates' => [],
            ],
            default => [
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'approved' => random_int(0, 1) === 1,
                                        'categories' => [],
                                        'severity' => 'low',
                                        'confidence' => 0.9,
                                        'reason' => 'Random test response',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        };

        return $responses;
    }
}
