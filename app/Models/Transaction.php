<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory\<\Database\Factories\TransactionFactory\> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_email',
        'amount',
        'currency',
        'type',
        'zone',
        'item_id',
        'item_title',
        'transaction_id',
        'status',
        'provider',
        'payment_status',
        'reference',
        'buyer_name',
        'buyer_phone',
        'provider_transaction_id',
        'channel',
        'msisdn',
        'provider_event',
        'provider_payload',
        'access_granted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'access_granted_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
