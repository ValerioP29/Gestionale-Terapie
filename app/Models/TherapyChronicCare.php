<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapyChronicCare extends Model
{
    use HasFactory;

    protected $table = 'jta_therapy_chronic_care';

    protected $fillable = [
        'therapy_id',
        'primary_condition',
        'care_context',
        'doctor_info',
        'general_anamnesis',
        'biometric_info',
        'detailed_intake',
        'adherence_base',
        'risk_score',
        'flags',
        'notes_initial',
        'follow_up_date',
        'consent',
    ];

    protected $casts = [
        'care_context' => 'array',
        'doctor_info' => 'array',
        'general_anamnesis' => 'array',
        'biometric_info' => 'array',
        'detailed_intake' => 'array',
        'adherence_base' => 'array',
        'flags' => 'array',
        'consent' => 'array',
        'follow_up_date' => 'date',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }
}
