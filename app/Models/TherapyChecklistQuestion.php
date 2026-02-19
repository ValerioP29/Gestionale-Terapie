<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TherapyChecklistQuestion extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_therapy_checklist_questions';

    protected $fillable = [
        'therapy_id',
        'pharmacy_id',
        'condition_key',
        'question_key',
        'label',
        'input_type',
        'options_json',
        'sort_order',
        'is_active',
        'is_custom',
    ];

    protected $casts = [
        'options_json' => 'array',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TherapyChecklistAnswer::class, 'question_id');
    }
}
