<?php

namespace App\Jobs;

use App\Models\MessageLog;
use App\Services\BaileysGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\RateLimiter;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $messageLogId) {}

    public function backoff(): array
    {
        return [10, 30, 60, 180, 300];
    }

    public function handle(BaileysGateway $gateway): void
    {
        $log = MessageLog::find($this->messageLogId);
        if (!$log) return;

        // idempotenza base: se già sent/failed non rifare
        if (in_array($log->status, ['sent', 'failed'], true)) return;

        // Rate limit per farmacia (es: 10 msg / minuto)
        $key = "wa:pharma:{$log->pharma_id}";
        $allowed = RateLimiter::attempt($key, 10, function () {}, 60);

        if (!$allowed) {
            // rimanda più tardi
            $this->release(15);
            return;
        }

        $log->update(['status' => 'sending', 'error' => null]);

        try {
            $resp = $gateway->send($log->pharma_id, $log->to, $log->body);

            $log->update([
                'status' => 'sent',
                'provider' => 'baileys',
                'provider_message_id' => $resp['provider_message_id'] ?? null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e; // così Horizon gestisce retry secondo tries/backoff
        }
    }
}
