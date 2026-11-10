<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierApiLog extends Model
{
    public $timestamps = false;

    protected $table = 'courier_api_logs';

    protected $fillable = [
        'order_id',
        'shipment_id',
        'courier',
        'operation',
        'http_method',
        'endpoint',
        'response_status',
        'attempts',
        'is_error',
        'error_message',
        'request_payload',
        'response_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_status'  => 'integer',
            'attempts'         => 'integer',
            'is_error'         => 'boolean',
            'request_payload'  => 'array',
            'response_payload' => 'array',
            'created_at'       => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

