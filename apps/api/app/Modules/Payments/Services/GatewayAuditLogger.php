<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\PaymentGatewayLog;
use App\Shared\Support\SensitiveDataRedactor;

class GatewayAuditLogger
{
    public function log(
        string $gateway,
        string $operation,
        string $httpMethod,
        string $endpoint,
        ?int $responseStatus,
        array $requestPayload = [],
        array $responsePayload = [],
        ?int $orderId = null,
        ?int $paymentTransactionId = null
    ): PaymentGatewayLog {
        return PaymentGatewayLog::query()->create([
            'order_id'               => $orderId,
            'payment_transaction_id' => $paymentTransactionId,
            'gateway'                => $gateway,
            'operation'              => $operation,
            'http_method'            => strtoupper($httpMethod),
            'endpoint'               => substr($endpoint, 0, 191),
            'response_status'        => $responseStatus,
            'request_payload'        => SensitiveDataRedactor::redact($requestPayload),
            'response_payload'       => SensitiveDataRedactor::redact($responsePayload),
            'created_at'             => now(),
        ]);
    }
}

