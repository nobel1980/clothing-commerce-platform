'use client';

import * as React from 'react';
import { usePathname, useRouter } from 'next/navigation';
import type { CatalogCategory, ProductFilterParams, ProductSortOption } from '@clothing-commerce/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';

export interface ProductFiltersBarProps {
  categories?: CatalogCategory[];
  currentFilters: ProductFilterParams;
  totalResults?: number;
}

const AVAILABLE_COLORS = ['Black', 'White', 'Navy', 'Olive', 'Sand', 'Red', 'Blue', 'Grey'];
const AVAILABLE_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

export function ProductFiltersBar({
  categories = [],
  currentFilters,
  totalResults,
}: ProductFiltersBarProps) {
  const router = useRouter();
  const pathname = usePathname();

  const [search, setSearch] = React.useState(currentFilters.search ?? '');
  const [category, setCategory] = React.useState(currentFilters.category ?? '');
  const [color, setColor] = React.useState(currentFilters.color ?? '');
  const [size, setSize] = React.useState(currentFilters.size ?? '');
  const [sort, setSort] = React.useState<ProductSortOption>(currentFilters.sort ?? 'newest');

  const applyFilters = (overrides: Partial<ProductFilterParams> = {}) => {
    const merged: ProductFilterParams = {
      search: search.trim() || undefined,
      category: category || undefined,
      color: color || undefined,
      size: size || undefined,
      sort,
      ...overrides,
    };

    const params = new URLSearchParams();
    if (merged.search) params.set('search', merged.search);
    if (merged.category) params.set('category', merged.category);
    if (merged.color) params.set('color', merged.color);
    if (merged.size) params.set('size', merged.size);
    if (merged.sort && merged.sort !== 'newest') params.set('sort', merged.sort);

    const qs = params.toString();
    router.push(qs ? `${pathname}?${qs}` : pathname);
  };

  const handleReset = () => {
    setSearch('');
    setCategory('');
    setColor('');
    setSize('');
    setSort('newest');
    router.push(pathname);
  };

  const hasActiveFilters = Boolean(
    currentFilters.search ||
      currentFilters.category ||
      currentFilters.color ||
      currentFilters.size ||
      (currentFilters.sort && currentFilters.sort !== 'newest')
  );

  return (
    <section
      aria-label="Catalog filters and sorting"
      className="mb-8 rounded-xl border bg-surface p-4"
      style={{
        backgroundColor: '#ffffff',
        border: '1px solid #e5e2dc',
        borderRadius: '0.75rem',
        padding: '1.25rem',
      }}
    >
      <form
        onSubmit={(e) => {
          e.preventDefault();
          applyFilters();
        }}
        className="flex flex-wrap items-end gap-3"
      >
        <div className="flex-1" style={{ minWidth: '12rem' }}>
          <label htmlFor="filter-search" className="mb-1 block text-xs font-medium text-muted">
            Keyword or SKU
          </label>
          <Input
            id="filter-search"
            type="search"
            placeholder="Search garments, fabrics, SKU…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>

        {categories.length > 0 && (
          <div>
            <label htmlFor="filter-category" className="mb-1 block text-xs font-medium text-muted">
              Category
            </label>
            <Select
              id="filter-category"
              value={category}
              onChange={(e) => {
                setCategory(e.target.value);
                applyFilters({ category: e.target.value || undefined });
              }}
            >
              <option value="">All Categories</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.slug}>
                  {cat.name}
                </option>
              ))}
            </Select>
          </div>
        )}

        <div>
          <label htmlFor="filter-color" className="mb-1 block text-xs font-medium text-muted">
            Color
          </label>
          <Select
            id="filter-color"
            value={color}
            onChange={(e) => {
              setColor(e.target.value);
              applyFilters({ color: e.target.value || undefined });
            }}
          >
            <option value="">All Colors</option>
            {AVAILABLE_COLORS.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <label htmlFor="filter-size" className="mb-1 block text-xs font-medium text-muted">
            Size
          </label>
          <Select
            id="filter-size"
            value={size}
            onChange={(e) => {
              setSize(e.target.value);
              applyFilters({ size: e.target.value || undefined });
            }}
          >
            <option value="">All Sizes</option>
            {AVAILABLE_SIZES.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <label htmlFor="filter-sort" className="mb-1 block text-xs font-medium text-muted">
            Sort By
          </label>
          <Select
            id="filter-sort"
            value={sort}
            onChange={(e) => {
              const nextSort = e.target.value as ProductSortOption;
              setSort(nextSort);
              applyFilters({ sort: nextSort });
            }}
          >
            <option value="newest">Newest Arrivals</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="name_asc">Name: A to Z</option>
            <option value="oldest">Archive / Oldest</option>
          </Select>
        </div>

        <Button type="submit" variant="default">
          Apply
        </Button>

        {hasActiveFilters && (
          <Button type="button" variant="ghost" onClick={handleReset}>
            Clear All
          </Button>
        )}
      </form>

      {(hasActiveFilters || typeof totalResults === 'number') && (
        <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-xs text-muted">
          <div className="flex flex-wrap items-center gap-2">
            {currentFilters.category && (
              <Badge variant="secondary">Category: {currentFilters.category}</Badge>
            )}
            {currentFilters.color && (
              <Badge variant="secondary">Color: {currentFilters.color}</Badge>
            )}
            {currentFilters.size && (
              <Badge variant="secondary">Size: {currentFilters.size}</Badge>
            )}
            {currentFilters.search && (
              <Badge variant="secondary">Search: “{currentFilters.search}”</Badge>
            )}
          </div>
          {typeof totalResults === 'number' && (
            <span>
              <strong>{totalResults}</strong> {totalResults === 1 ? 'piece' : 'pieces'} found
            </span>
          )}
        </div>
      )}
    </section>
  );
}

