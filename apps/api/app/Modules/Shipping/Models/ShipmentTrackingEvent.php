<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    public $timestamps = false;

    protected $table = 'shipment_tracking_events';

    protected $fillable = [
        'shipment_id',
        'courier',
        'consignment_id',
        'event_key',
        'external_status',
        'internal_status',
        'description',
        'location',
        'raw_payload',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}

