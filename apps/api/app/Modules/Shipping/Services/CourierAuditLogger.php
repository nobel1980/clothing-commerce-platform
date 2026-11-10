<?php

namespace App\Modules\Shipping\Services;

use App\Modules\Shipping\Models\CourierApiLog;
use App\Shared\Support\SensitiveDataRedactor;

class CourierAuditLogger
{
    public function log(
        string $courier,
        string $operation,
        string $httpMethod,
        string $endpoint,
        ?int $responseStatus,
        array $requestPayload = [],
        array $responsePayload = [],
        int $attempts = 1,
        bool $isError = false,
        ?string $errorMessage = null,
        ?int $orderId = null,
        ?int $shipmentId = null
    ): CourierApiLog {
        return CourierApiLog::query()->create([
            'order_id'         => $orderId,
            'shipment_id'      => $shipmentId,
            'courier'          => strtolower($courier),
            'operation'        => $operation,
            'http_method'      => strtoupper($httpMethod),
            'endpoint'         => substr($endpoint, 0, 191),
            'response_status'  => $responseStatus,
            'attempts'         => max(1, $attempts),
            'is_error'         => $isError,
            'error_message'    => $errorMessage !== null ? substr($errorMessage, 0, 191) : null,
            'request_payload'  => SensitiveDataRedactor::redact($requestPayload),
            'response_payload' => SensitiveDataRedactor::redact($responsePayload),
            'created_at'       => now(),
        ]);
    }
}

