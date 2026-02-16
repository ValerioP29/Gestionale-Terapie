<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_patients';

    protected $fillable = [
        'pharmacy_id',
        'first_name',
        'last_name',
        'birth_date',
        'codice_fiscale',
        'gender',
        'phone',
        'email',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }

    public function therapies(): HasMany
    {
        return $this->hasMany(Therapy::class, 'patient_id');
    }
}
