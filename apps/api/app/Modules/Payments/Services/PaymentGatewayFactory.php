<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Gateways\BkashGatewayAdapter;
use App\Modules\Payments\Gateways\CodGatewayAdapter;
use App\Modules\Payments\Gateways\MockPaymentGatewayAdapter;
use App\Modules\Payments\Gateways\NagadGatewayAdapter;
use App\Modules\Payments\Gateways\SslCommerzGatewayAdapter;
use App\Shared\Contracts\Payments\PaymentGateway;
use App\Shared\Exceptions\Payments\PaymentConfigurationException;

class PaymentGatewayFactory
{
    public function resolve(string $gateway): PaymentGateway
    {
        $normalized = strtolower(trim($gateway));

        return match ($normalized) {
            'cod'                   => app(CodGatewayAdapter::class),
            'bkash'                 => app(BkashGatewayAdapter::class),
            'nagad'                 => app(NagadGatewayAdapter::class),
            'sslcommerz', 'card', 'online' => app(SslCommerzGatewayAdapter::class),
            'mock'                  => app(MockPaymentGatewayAdapter::class),
            default                 => throw new PaymentConfigurationException(
                "Unsupported payment gateway '{$gateway}'.",
                ['gateway' => $gateway]
            ),
        };
    }
}

