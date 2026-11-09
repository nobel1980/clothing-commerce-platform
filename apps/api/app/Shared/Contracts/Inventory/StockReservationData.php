<?php

namespace App\Shared\Contracts\Inventory;

final readonly class StockReservationData
{
    /**
     * @param array<int, StockReservationItemData> $items
     */
    public function __construct(
        public int $id,
        public string $idempotencyKey,
        public string $status,
        public string $expiresAt,
        public ?string $referenceType,
        public ?string $referenceId,
        public ?string $committedAt,
        public ?string $releasedAt,
        public ?string $expiredAt,
        public array $items,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'idempotency_key' => $this->idempotencyKey,
            'status'          => $this->status,
            'expires_at'      => $this->expiresAt,
            'reference_type'  => $this->referenceType,
            'reference_id'    => $this->referenceId,
            'committed_at'    => $this->committedAt,
            'released_at'     => $this->releasedAt,
            'expired_at'      => $this->expiredAt,
            'items'           => array_map(fn (StockReservationItemData $i) => $i->toArray(), $this->items),
            'metadata'        => $this->metadata,
        ];
    }
}

