<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebSocketNotifyJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public array $backoff = [1, 3, 5];

    public function __construct(
        public private(set) readonly string $event,
        public private(set) readonly array $data,
    ) {}

    public function handle(): void
    {
        $wsUrl = config('services.websocket.url', 'http://localhost:3001/notify');

        $payload = [
            'event' => $this->event,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];

        try {
            $response = Http::timeout(5)->post($wsUrl, $payload);

            if (! $response->successful()) {
                Log::debug('Sending WebSocket notification failed', [
                    'event' => $this->event,
                    'post_id' => $this->data['id'] ?? null,
                    'status' => $this->data['status'] ?? null,
                    'ws_url' => $wsUrl,
                    'attempt' => $this->attempts(),
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                throw new \Exception('WebSocket server responded with: '.$response->status().' - '.$response->body());
            }
        } catch (\Exception $e) {
            Log::warning('WebSocket notification failed', [
                'event' => $this->event,
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                Log::error('WebSocket notification permanently failed', [
                    'event' => $this->event,
                    'final_error' => $e->getMessage(),
                ]);
            } else {
                throw $e;
            }
        }
    }
}
