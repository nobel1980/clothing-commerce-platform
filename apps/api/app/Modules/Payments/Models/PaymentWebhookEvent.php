<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'gateway',
        'gateway_event_id',
        'payment_transaction_id',
        'order_id',
        'event_type',
        'status',
        'payload',
        'headers',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_transaction_id' => 'integer',
            'order_id'               => 'integer',
            'payload'                => 'array',
            'headers'                => 'array',
            'processed_at'           => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

