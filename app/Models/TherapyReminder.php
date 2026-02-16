<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class TherapyReminder extends Model
{
    use HasFactory;

    protected $table = 'jta_therapy_reminders';

    protected $fillable = [
        'therapy_id',
        'title',
        'description',
        'frequency',
        'interval_value',
        'weekday',
        'first_due_at',
        'next_due_at',
        'status',
    ];

    protected $casts = [
        'first_due_at' => 'datetime',
        'next_due_at' => 'datetime',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }

    public function pharmacy(): HasOneThrough
    {
        return $this->hasOneThrough(
            Pharmacy::class,
            Therapy::class,
            'id',
            'id',
            'therapy_id',
            'pharmacy_id'
        );
    }
}
