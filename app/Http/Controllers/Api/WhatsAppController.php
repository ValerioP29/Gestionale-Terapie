<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\MessageLog;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function queue(Request $request)
    {
        $data = $request->validate([
            'pharma_id' => ['required','integer'],
            'patient_id' => ['nullable','integer'],
            'to' => ['required','string','max:30'],
            'body' => ['required','string'],
        ]);

        $log = MessageLog::create([
            'pharma_id' => $data['pharma_id'],
            'patient_id' => $data['patient_id'] ?? null,
            'to' => $data['to'],
            'body' => $data['body'],
            'status' => 'queued',
            'provider' => 'baileys',
        ]);

        SendWhatsAppMessageJob::dispatch($log->id);

        return response()->json(['ok' => true, 'message_log_id' => $log->id]);
    }
}
