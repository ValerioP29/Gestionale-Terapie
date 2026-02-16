<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Model;

class Therapy extends Model
{
    use BelongsToPharmacy;

    protected $table = 'jta_therapies';

    protected $guarded = [];
}
