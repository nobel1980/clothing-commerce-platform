<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\Catalog\ProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected static function newFactory(): ProductImageFactory
    {
        return ProductImageFactory::new();
    }

    protected $fillable = [
        'product_id',
        'disk',
        'path',
        'url',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'  => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Always generate the public URL through the storage abstraction,
     * never hardcode a local path.
     */
    public function getPublicUrl(): string
    {
        if ($this->url) {
            return $this->url;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
