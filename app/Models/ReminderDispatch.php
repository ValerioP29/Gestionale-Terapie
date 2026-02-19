<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatch extends Model
{
    use BelongsToPharmacy;
    use HasFactory;

    protected $table = 'jta_reminder_dispatches';

    protected $fillable = [
        'pharmacy_id',
        'reminder_id',
        'due_at',
        'attempt',
        'outcome',
        'message_log_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
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
