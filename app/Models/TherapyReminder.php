<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TherapyReminder extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_therapy_reminders';

    protected $fillable = [
        'pharmacy_id',
        'therapy_id',
        'title',
        'frequency',
        'weekday',
        'first_due_at',
        'next_due_at',
        'last_done_at',
        'status',
    ];

    protected $casts = [
        'first_due_at' => 'datetime',
        'next_due_at' => 'datetime',
        'last_done_at' => 'datetime',
    ];

    public function therapy(): BelongsTo
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(ReminderDispatch::class, 'reminder_id');
    }
}
