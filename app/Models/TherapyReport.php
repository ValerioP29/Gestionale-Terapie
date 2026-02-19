<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapyReport extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_therapy_reports';

    protected $fillable = [
        'therapy_id',
        'pharmacy_id',
        'content',
        'share_token',
        'valid_until',
        'pdf_path',
        'pdf_generated_at',
    ];

    protected $casts = [
        'content' => 'array',
        'valid_until' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }
}
