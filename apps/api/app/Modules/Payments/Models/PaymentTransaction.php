<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'idempotency_key',
        'merchant_reference',
        'gateway',
        'gateway_payment_id',
        'gateway_transaction_id',
        'gateway_payment_url',
        'amount',
        'refunded_amount',
        'currency',
        'status',
        'failure_reason',
        'raw_initiation_request',
        'raw_initiation_response',
        'raw_verification_request',
        'raw_verification_response',
        'metadata',
        'paid_at',
        'failed_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id'                  => 'integer',
            'amount'                    => 'integer',
            'refunded_amount'           => 'integer',
            'raw_initiation_request'    => 'array',
            'raw_initiation_response'   => 'array',
            'raw_verification_request'  => 'array',
            'raw_verification_response' => 'array',
            'metadata'                  => 'array',
            'paid_at'                   => 'datetime',
            'failed_at'                 => 'datetime',
            'refunded_at'               => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }
}

