<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapyConsent extends Model
{
    use HasFactory;

    protected $table = 'jta_therapy_consents';

    public $timestamps = false;

    protected $fillable = [
        'therapy_id',
        'signer_name',
        'signer_relation',
        'consent_text',
        'signed_at',
        'ip_address',
        'signature_image',
        'scopes_json',
        'signer_role',
        'created_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'created_at' => 'datetime',
        'scopes_json' => 'array',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }
}
