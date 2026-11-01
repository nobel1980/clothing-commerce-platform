<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\Catalog\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    protected $fillable = [
        'product_id',
        'sku',
        'color',
        'size',
        'price_amount',
        'compare_at_price',
        'cost_price',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Prices are stored as integer sub-units — never cast to float
            'price_amount'     => 'integer',
            'compare_at_price' => 'integer',
            'cost_price'       => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variantAttributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values',
            'product_variant_id',
            'attribute_value_id'
        )->withPivot('attribute_id')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Return price in major units (taka/dollar) for display.
     * Conversion: stored paisa → taka = /100
     */
    public function getPriceInMajorUnits(): float
    {
        return $this->price_amount / 100;
    }
}
