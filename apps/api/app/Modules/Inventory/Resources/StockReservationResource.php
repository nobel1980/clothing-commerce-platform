<?php

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'reference_type'  => $this->reference_type,
            'reference_id'    => $this->reference_id,
            'status'          => $this->status,
            'expires_at'      => $this->expires_at?->toIso8601String(),
            'committed_at'    => $this->committed_at?->toIso8601String(),
            'released_at'     => $this->released_at?->toIso8601String(),
            'expired_at'      => $this->expired_at?->toIso8601String(),
            'metadata'        => $this->metadata,
            'items'           => StockReservationItemResource::collection($this->whenLoaded('items')),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}

