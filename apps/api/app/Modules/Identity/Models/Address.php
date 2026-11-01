<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'division',
        'district',
        'upazila_thana',
        'street_address',
        'postal_code',
        'type',
        'is_default',
        'delivery_instructions',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for default addresses.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for shipping addresses.
     */
    public function scopeShipping($query)
    {
        return $query->whereIn('type', ['shipping', 'both']);
    }

    /**
     * Scope for billing addresses.
     */
    public function scopeBilling($query)
    {
        return $query->whereIn('type', ['billing', 'both']);
    }

    /**
     * Formatted Bangladesh address string.
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = [
            $this->street_address,
            $this->upazila_thana,
            $this->district . ($this->postal_code ? ' - ' . $this->postal_code : ''),
            $this->division,
            'Bangladesh',
        ];

        return implode(', ', array_filter($parts));
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\Identity\AddressFactory
    {
        return \Database\Factories\Identity\AddressFactory::new();
    }
}
