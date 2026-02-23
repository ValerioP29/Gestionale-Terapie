<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\MessageLog;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WhatsAppController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pharma_id' => ['nullable', 'integer'],
            'pharmacy_id' => ['nullable', 'integer'],
            'patient_id' => ['nullable', 'integer'],
            'to' => ['required', 'string', 'max:30'],
            'body' => ['required', 'string'],
        ]);

        $tenantPharmacyId = app(CurrentPharmacy::class)->getId();
        $requestedPharmacyId = $data['pharmacy_id'] ?? $data['pharma_id'] ?? null;
        $resolvedPharmacyId = $tenantPharmacyId ?? $requestedPharmacyId;

        if ($resolvedPharmacyId === null) {
            throw ValidationException::withMessages([
                'pharmacy_id' => 'Farmacia non risolta. Imposta pharmacy_id o usa il tenant corrente.',
            ]);
        }

        if ($tenantPharmacyId !== null && $requestedPharmacyId !== null && (int) $requestedPharmacyId !== (int) $tenantPharmacyId) {
            throw ValidationException::withMessages([
                'pharmacy_id' => 'Il pharmacy_id inviato non coincide con il tenant corrente.',
            ]);
        }

        $log = MessageLog::create([
            'pharma_id' => $resolvedPharmacyId,
            'patient_id' => $data['patient_id'] ?? null,
            'to' => $data['to'],
            'body' => $data['body'],
            'status' => 'queued',
            'provider' => 'baileys',
        ]);

        SendWhatsAppMessageJob::dispatch($log->id);

        return response()->json([
            'ok' => true,
            'message_log_id' => $log->id,
            'pharmacy_id' => $log->pharmacy_id,
        ]);
    }
}
