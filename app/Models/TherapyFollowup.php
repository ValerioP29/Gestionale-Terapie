<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TherapyFollowup extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_therapy_followups';

    protected $fillable = [
        'therapy_id',
        'pharmacy_id',
        'created_by',
        'entry_type',
        'check_type',
        'occurred_at',
        'risk_score',
        'pharmacist_notes',
        'education_notes',
        'snapshot',
        'follow_up_date',
        'canceled_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'follow_up_date' => 'date',
        'occurred_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }

    public function checklistAnswers(): HasMany
    {
        return $this->hasMany(TherapyChecklistAnswer::class, 'followup_id');
    }
}
