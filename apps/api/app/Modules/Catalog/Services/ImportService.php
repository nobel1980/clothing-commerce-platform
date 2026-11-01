<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk import foundation — parses CSV or JSON rows and creates products+variants.
 * Heavy imports (large files) are dispatched to a queue job by the controller.
 * This service handles the synchronous row-by-row creation logic.
 */
class ImportService
{
    /**
     * Parse a CSV file and return an array of row data.
     *
     * Expected CSV columns (header row):
     * name, slug, description, category_slug, brand_slug,
     * sku, price_amount, compare_at_price, cost_price, currency,
     * color, size, status
     */
    public function parseCsv(UploadedFile $file): array
    {
        $rows   = [];
        $handle = fopen($file->getPathname(), 'r');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($headers)) {
                continue; // skip malformed rows
            }

            $rows[] = array_combine($headers, $line);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Import a batch of rows synchronously.
     * Returns a summary of created/skipped records.
     */
    public function importRows(array $rows): array
    {
        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rows as $index => $row) {
            try {
                DB::transaction(function () use ($row) {
                    $product = $this->findOrCreateProduct($row);
                    $this->findOrCreateVariant($product, $row);
                });

                $created++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row'     => $index + 1,
                    'message' => $e->getMessage(),
                    'data'    => $row,
                ];
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function findOrCreateProduct(array $row): Product
    {
        $slug = $row['slug'] ?? Str::slug($row['name'] ?? '');

        $product = Product::where('slug', $slug)->first();

        if ($product) {
            return $product;
        }

        $category = !empty($row['category_slug'])
            ? Category::where('slug', $row['category_slug'])->first()
            : null;

        $brand = !empty($row['brand_slug'])
            ? Brand::where('slug', $row['brand_slug'])->first()
            : null;

        return Product::create([
            'name'        => $row['name'],
            'slug'        => $slug,
            'description' => $row['description'] ?? null,
            'category_id' => $category?->id,
            'brand_id'    => $brand?->id,
            'status'      => $row['status'] ?? 'draft',
        ]);
    }

    private function findOrCreateVariant(Product $product, array $row): ProductVariant
    {
        $sku = trim($row['sku'] ?? '');

        if (empty($sku)) {
            throw new \InvalidArgumentException('SKU is required for each variant row.');
        }

        $existing = ProductVariant::where('sku', $sku)->first();

        if ($existing) {
            return $existing;
        }

        // price_amount must be integer sub-units — never float
        $priceAmount = isset($row['price_amount']) ? (int) $row['price_amount'] : 0;

        return ProductVariant::create([
            'product_id'       => $product->id,
            'sku'              => $sku,
            'color'            => $row['color'] ?? null,
            'size'             => $row['size'] ?? null,
            'price_amount'     => $priceAmount,
            'compare_at_price' => isset($row['compare_at_price']) && $row['compare_at_price'] !== ''
                ? (int) $row['compare_at_price'] : null,
            'cost_price'       => isset($row['cost_price']) && $row['cost_price'] !== ''
                ? (int) $row['cost_price'] : null,
            'currency'         => $row['currency'] ?? 'BDT',
            'status'           => 'active',
        ]);
    }
}
