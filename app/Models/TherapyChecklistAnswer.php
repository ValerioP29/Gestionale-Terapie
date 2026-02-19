<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class TherapyChecklistAnswer extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_therapy_checklist_answers';

    protected $fillable = [
        'pharmacy_id',
        'therapy_id',
        'followup_id',
        'question_id',
        'answer_value',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(TherapyChecklistQuestion::class, 'question_id');
    }

    public function followup(): BelongsTo
    {
        return $this->belongsTo(TherapyFollowup::class, 'followup_id');
    }

    public function therapy(): HasOneThrough
    {
        return $this->hasOneThrough(
            Therapy::class,
            TherapyFollowup::class,
            'id',
            'id',
            'followup_id',
            'therapy_id'
        );
    }
}
