<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatch extends Model
{
    use HasFactory;

    protected $table = 'reminder_dispatches';

    protected $fillable = [
        'reminder_id',
        'message_log_id',
        'scheduled_for',
        'dispatched_at',
        'status',
        'error',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(TherapyReminder::class, 'reminder_id');
    }

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(MessageLog::class, 'message_log_id');
    }
}
