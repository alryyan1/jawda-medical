<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $fillable = [
        'waba_id',
        'phone_number_id',
        'to',
        'from',
        'type',
        'body',
        'status',
        'message_id',
        'direction',
        'raw_payload'
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];
}
