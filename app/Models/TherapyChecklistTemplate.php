<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapyChecklistTemplate extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_therapy_checklist_templates';

    protected $fillable = [
        'pharmacy_id',
        'condition_key',
        'questionnaire_step',
        'section',
        'question_key',
        'label',
        'input_type',
        'options_json',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'options_json' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];
}
