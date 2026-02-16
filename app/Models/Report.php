<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use BelongsToPharmacy;

    protected $table = 'jta_therapy_reports';

    protected $guarded = [];
}
