<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * Upload and attach an image to a product.
     * Uses storage abstraction — disk is configurable via env (public, s3, etc.).
     */
    public function upload(Product $product, UploadedFile $file, array $data = []): ProductImage
    {
        $disk = config('filesystems.default', 'public');

        // Store the file; path is disk-relative, never a hardcoded local path
        $path = $file->store("products/{$product->id}/images", $disk);

        return DB::transaction(function () use ($product, $disk, $path, $file, $data) {
            $isPrimary = $data['is_primary'] ?? false;

            // If this image should be primary, demote existing primary image(s)
            if ($isPrimary) {
                $product->images()->where('is_primary', true)->update(['is_primary' => false]);
            }

            // Auto-assign as primary if product has no images yet
            $hasPrimary = $product->images()->where('is_primary', true)->exists();
            if (!$hasPrimary) {
                $isPrimary = true;
            }

            $maxOrder = $product->images()->max('sort_order') ?? -1;

            return ProductImage::create([
                'product_id' => $product->id,
                'disk'       => $disk,
                'path'       => $path,
                'url'        => null, // resolved on-the-fly via getPublicUrl()
                'alt_text'   => $data['alt_text'] ?? null,
                'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
                'is_primary' => $isPrimary,
            ]);
        });
    }

    /**
     * Reorder product images given an ordered array of image IDs.
     */
    public function reorder(Product $product, array $orderedIds): void
    {
        DB::transaction(function () use ($product, $orderedIds) {
            foreach ($orderedIds as $position => $imageId) {
                $product->images()
                    ->where('id', $imageId)
                    ->update(['sort_order' => $position]);
            }

            // Set first image as primary if not already set
            $firstId = $orderedIds[0] ?? null;
            if ($firstId) {
                $product->images()->where('is_primary', true)->update(['is_primary' => false]);
                $product->images()->where('id', $firstId)->update(['is_primary' => true]);
            }
        });
    }

    /**
     * Delete an image from storage and the database.
     */
    public function delete(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $wasPrimary = $image->is_primary;
            $productId  = $image->product_id;

            // Remove file from the configured storage disk
            Storage::disk($image->disk)->delete($image->path);

            $image->delete();

            // Promote the next image to primary if the deleted one was primary
            if ($wasPrimary) {
                $next = ProductImage::where('product_id', $productId)
                    ->orderBy('sort_order')
                    ->first();

                $next?->update(['is_primary' => true]);
            }
        });
    }
}
