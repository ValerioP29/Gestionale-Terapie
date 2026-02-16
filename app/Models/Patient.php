<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use BelongsToPharmacy;

    protected $table = 'jta_patients';

    protected $guarded = [];
}
