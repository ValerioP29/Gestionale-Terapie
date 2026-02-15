<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $table = 'message_logs';

    protected $fillable = [
        'pharma_id','patient_id','to','body','status','provider','provider_message_id','error','sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
