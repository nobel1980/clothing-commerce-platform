<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\VariantAttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantService
{
    /**
     * Create a new variant for a product.
     * Prices must already be integer sub-units from the request.
     */
    public function create(Product $product, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $data) {
            $variant = ProductVariant::create([
                'product_id'       => $product->id,
                'sku'              => $data['sku'],
                'color'            => $data['color'] ?? null,
                'size'             => $data['size'] ?? null,
                'price_amount'     => $data['price_amount'],
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'cost_price'       => $data['cost_price'] ?? null,
                'currency'         => $data['currency'] ?? 'BDT',
                'status'           => $data['status'] ?? 'active',
            ]);

            if (!empty($data['attribute_values'])) {
                $this->syncAttributeValues($variant, $data['attribute_values']);
            }

            return $variant->load('attributeValues.attribute');
        });
    }

    /**
     * Update an existing variant.
     */
    public function update(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $variant->update(array_filter([
                'sku'              => $data['sku'] ?? $variant->sku,
                'color'            => array_key_exists('color', $data) ? $data['color'] : $variant->color,
                'size'             => array_key_exists('size', $data) ? $data['size'] : $variant->size,
                'price_amount'     => $data['price_amount'] ?? $variant->price_amount,
                'compare_at_price' => array_key_exists('compare_at_price', $data) ? $data['compare_at_price'] : $variant->compare_at_price,
                'cost_price'       => array_key_exists('cost_price', $data) ? $data['cost_price'] : $variant->cost_price,
                'currency'         => $data['currency'] ?? $variant->currency,
                'status'           => $data['status'] ?? $variant->status,
            ], fn($v) => $v !== null) + [
                'compare_at_price' => array_key_exists('compare_at_price', $data) ? $data['compare_at_price'] : $variant->compare_at_price,
                'cost_price'       => array_key_exists('cost_price', $data) ? $data['cost_price'] : $variant->cost_price,
                'color'            => array_key_exists('color', $data) ? $data['color'] : $variant->color,
                'size'             => array_key_exists('size', $data) ? $data['size'] : $variant->size,
            ]);

            if (array_key_exists('attribute_values', $data)) {
                $this->syncAttributeValues($variant, $data['attribute_values']);
            }

            return $variant->fresh('attributeValues.attribute');
        });
    }

    /**
     * Delete a variant (soft delete).
     */
    public function delete(ProductVariant $variant): void
    {
        DB::transaction(fn() => $variant->delete());
    }

    /**
     * Sync attribute values for a variant.
     * Each attribute can only have one value per variant.
     */
    private function syncAttributeValues(ProductVariant $variant, array $attributeValues): void
    {
        // Delete existing mappings and re-create
        $variant->variantAttributeValues()->delete();

        foreach ($attributeValues as $av) {
            // Ensure the attribute_value belongs to the declared attribute
            $valid = \App\Modules\Catalog\Models\AttributeValue::where('id', $av['attribute_value_id'])
                ->where('attribute_id', $av['attribute_id'])
                ->exists();

            if (!$valid) {
                throw ValidationException::withMessages([
                    'attribute_values' => ["Attribute value {$av['attribute_value_id']} does not belong to attribute {$av['attribute_id']}."],
                ]);
            }

            VariantAttributeValue::create([
                'product_variant_id' => $variant->id,
                'attribute_id'       => $av['attribute_id'],
                'attribute_value_id' => $av['attribute_value_id'],
            ]);
        }
    }
}
