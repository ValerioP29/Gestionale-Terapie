<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapyConditionSurvey extends Model
{
    use HasFactory;

    protected $table = 'jta_therapy_condition_surveys';

    protected $fillable = [
        'therapy_id',
        'condition_type',
        'level',
        'answers',
        'compiled_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'compiled_at' => 'datetime',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }
}
