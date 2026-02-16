<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Model;

class ChecklistQuestion extends Model
{
    use BelongsToPharmacy;

    protected $table = 'jta_therapy_checklist_questions';

    protected $guarded = [];
}
