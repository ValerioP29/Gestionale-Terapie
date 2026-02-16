<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assistant extends Model
{
    use HasFactory;

    protected $table = 'jta_assistants';

    protected $fillable = [
        'pharma_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'preferred_contact',
        'type',
        'relation_to_patient',
        'notes',
        'extra_info',
    ];

    protected $casts = [
        'extra_info' => 'array',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharma_id');
    }
}
