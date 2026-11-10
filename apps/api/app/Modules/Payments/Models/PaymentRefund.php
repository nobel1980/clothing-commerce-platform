<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_transaction_id',
        'order_id',
        'idempotency_key',
        'gateway',
        'gateway_refund_id',
        'amount',
        'currency',
        'status',
        'reason',
        'initiated_by',
        'raw_request',
        'raw_response',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_transaction_id' => 'integer',
            'order_id'               => 'integer',
            'amount'                 => 'integer',
            'initiated_by'           => 'integer',
            'raw_request'            => 'array',
            'raw_response'           => 'array',
            'refunded_at'            => 'datetime',
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

