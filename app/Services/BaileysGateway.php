<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BaileysGateway
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly ?string $token = null,
        private readonly int $timeout = 10,
    ) {
        $this->baseUrl = config('baileys.url');
        $this->token = config('baileys.token');
        $this->timeout = config('baileys.timeout');
    }

    private function client()
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'X-Internal-Token' => $this->token ?? '',
            ]);
    }

    public function status(int $pharmaId): array
    {
        return $this->client()
            ->get("{$this->baseUrl}/sessions/{$pharmaId}/status")
            ->throw()
            ->json();
    }

    public function send(int $pharmaId, string $to, string $message): array
    {
        return $this->client()
            ->post("{$this->baseUrl}/sessions/{$pharmaId}/send", [
                'to' => $to,
                'message' => $message,
            ])
            ->throw()
            ->json();
    }

    public function qr(int $pharmaId): array
    {
        return $this->client()
            ->get("{$this->baseUrl}/sessions/{$pharmaId}/qr")
            ->throw()
            ->json();
    }
}
