<?php

namespace LBHurtado\OmniChannel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OmniChannelService
{
    protected string $url;
    protected string $accessKey;
    protected string $service;

    public function __construct(string $url, string $accessKey, string $service = 'mt')
    {
        $this->url = $url;
        $this->accessKey = $accessKey;
        $this->service = $service;
    }

    /**
     * Send an SMS message.
     *
     * @param  string $to
     * @param  string $message
     * @return bool
     */
    public function send(string $to, string $message): bool
    {
        $payload = [
            'accesskey' => $this->accessKey,
            'service'   => $this->service,
            'data'      => [
                'to'      => $to,
                'message' => $message,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post($this->url, $payload);

            $body = $response->body();

            Log::info('[SmsSenderService] SMS sent', [
                'to'      => $to,
                'message' => $message,
                'status'  => $response->status(),
                'body'    => $body,
            ]);

            return $response->status() === 200 && str_starts_with($body, 'ACK|');
        } catch (\Throwable $e) {
            Log::error('[SmsSenderService] Failed to send SMS', [
                'to'      => $to,
                'message' => $message,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
