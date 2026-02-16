<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Model;

class Followup extends Model
{
    use BelongsToPharmacy;

    protected $table = 'jta_therapy_followups';

    protected $guarded = [];
}
