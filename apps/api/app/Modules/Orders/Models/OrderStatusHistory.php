<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'from_payment_status',
        'to_payment_status',
        'from_fulfilment_status',
        'to_fulfilment_status',
        'actor_type',
        'actor_id',
        'reason',
        'notes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id'   => 'integer',
            'actor_id'   => 'integer',
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

