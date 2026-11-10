<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Couriers\PathaoCourierAdapter;
use App\Modules\Shipping\Couriers\RedxCourierAdapter;
use App\Modules\Shipping\Couriers\SteadfastCourierAdapter;
use App\Shared\Contracts\Shipping\CourierProvider;
use App\Shared\Exceptions\Shipping\ShippingException;

class CourierProviderFactory
{
    public function __construct(
        protected CourierAuditLogger $auditLogger
    ) {}

    public function resolve(?string $courier = null): CourierProvider
    {
        $normalized = strtolower(trim($courier ?? (string) config('shipping.default_courier', 'pathao')));

        return match ($normalized) {
            'pathao'    => new PathaoCourierAdapter($this->auditLogger),
            'steadfast' => new SteadfastCourierAdapter($this->auditLogger),
            'redx'      => new RedxCourierAdapter($this->auditLogger),
            default     => throw new ShippingException("Unsupported courier provider '{$courier}'.", 422, 'courier'),
        };
    }
}

