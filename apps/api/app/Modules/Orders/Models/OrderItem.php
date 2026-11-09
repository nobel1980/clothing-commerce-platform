<?php

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'sku_snapshot',
        'product_name_snapshot',
        'brand_name_snapshot',
        'color_snapshot',
        'size_snapshot',
        'image_url_snapshot',
        'attributes_snapshot',
        'quantity',
        'unit_price_amount',
        'compare_at_price_amount',
        'discount_amount',
        'tax_amount',
        'line_total_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'order_id'                => 'integer',
            'product_id'              => 'integer',
            'product_variant_id'      => 'integer',
            'attributes_snapshot'     => 'array',
            'quantity'                => 'integer',
            'unit_price_amount'       => 'integer',
            'compare_at_price_amount' => 'integer',
            'discount_amount'         => 'integer',
            'tax_amount'              => 'integer',
            'line_total_amount'       => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

