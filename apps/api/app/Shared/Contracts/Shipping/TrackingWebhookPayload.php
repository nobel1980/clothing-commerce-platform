<?php

namespace App\Shared\Contracts\Shipping;

final readonly class TrackingWebhookPayload
{
    public function __construct(
        public string $courier,
        public string $consignmentId,
        public ?string $merchantOrderReference,
        public string $eventKey,
        public string $externalStatus,
        public string $internalStatus,
        public ?string $description = null,
        public ?string $location = null,
        public ?int $collectedCodAmountPaisa = null,
        public ?string $occurredAt = null,
        public array $rawPayload = [],
    ) {}
}

