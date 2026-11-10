<?php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'payment_transaction_id',
        'gateway',
        'operation',
        'http_method',
        'endpoint',
        'response_status',
        'request_payload',
        'response_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id'               => 'integer',
            'payment_transaction_id' => 'integer',
            'response_status'        => 'integer',
            'request_payload'        => 'array',
            'response_payload'       => 'array',
            'created_at'             => 'datetime',
        ];
    }
}

