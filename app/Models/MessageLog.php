<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageLog extends Model
{
    use HasFactory;

    protected $table = 'message_logs';

    protected $fillable = [
        'pharma_id',
        'patient_id',
        'to',
        'body',
        'status',
        'provider',
        'provider_message_id',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharma_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function reminderDispatches(): HasMany
    {
        return $this->hasMany(ReminderDispatch::class, 'message_log_id');
    }
}
