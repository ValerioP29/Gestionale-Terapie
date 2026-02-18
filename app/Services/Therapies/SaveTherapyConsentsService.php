<?php

namespace App\Services\Therapies;

use App\Models\Therapy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class SaveTherapyConsentsService
{
    public function handle(Therapy $therapy, ?array $consent): void
    {
        if ($consent === null || $consent === []) {
            return;
        }

        $signatureBinary = null;
        if (! empty($consent['signature_path']) && Storage::disk(config('filesystems.default'))->exists($consent['signature_path'])) {
            $signatureBinary = Storage::disk(config('filesystems.default'))->get($consent['signature_path']);
        }

        $therapy->consents()->create([
            'signer_name' => $consent['signer_name'],
            'signer_relation' => $consent['signer_relation'],
            'consent_text' => $consent['consent_text'],
            'signed_at' => $consent['signed_at'] ?? Carbon::now(),
            'ip_address' => $consent['ip_address'] ?? null,
            'signature_image' => $signatureBinary,
            'scopes_json' => $consent['scopes_json'] ?? null,
            'signer_role' => $consent['signer_role'] ?? null,
            'created_at' => Carbon::now(),
        ]);
    }
}
