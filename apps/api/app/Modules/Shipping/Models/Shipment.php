<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $table = 'shipments';

    protected $fillable = [
        'order_id',
        'idempotency_key',
        'courier',
        'consignment_id',
        'merchant_order_reference',
        'tracking_number',
        'tracking_url',
        'status',
        'shipping_charge',
        'cod_amount',
        'cod_collected_amount',
        'cod_reconciliation_status',
        'cod_reconciled_at',
        'currency',
        'weight_grams',
        'item_quantity',
        'recipient_snapshot',
        'courier_metadata',
        'failure_reason',
        'picked_up_at',
        'delivered_at',
        'failed_at',
        'returned_at',
        'cancelled_at',
        'inventory_restored_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shipping_charge'       => 'integer',
            'cod_amount'            => 'integer',
            'cod_collected_amount'  => 'integer',
            'weight_grams'          => 'integer',
            'item_quantity'         => 'integer',
            'recipient_snapshot'    => 'array',
            'courier_metadata'      => 'array',
            'cod_reconciled_at'     => 'datetime',
            'picked_up_at'          => 'datetime',
            'delivered_at'          => 'datetime',
            'failed_at'             => 'datetime',
            'returned_at'           => 'datetime',
            'cancelled_at'          => 'datetime',
            'inventory_restored_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShipmentTrackingEvent::class, 'shipment_id')->orderBy('id');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(CourierApiLog::class, 'shipment_id')->orderBy('id');
    }
}

