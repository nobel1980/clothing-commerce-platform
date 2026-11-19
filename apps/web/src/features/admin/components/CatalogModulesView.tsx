'use client';

import * as React from 'react';
import type {
  CatalogCategory,
  CatalogProduct,
  CatalogVariant,
  PaginationMeta,
} from '@clothing-commerce/types';
import {
  createAdminCategory,
  createAdminProduct,
  createAdminVariant,
  deleteAdminCategory,
  deleteAdminProduct,
  deleteAdminVariant,
  downloadAdminCsvExport,
  fetchAdminCategories,
  fetchAdminProducts,
  fetchAdminVariants,
  publishAdminProduct,
  unpublishAdminProduct,
} from '@/features/admin/api/adminApi';
import { useAdminAuth } from '@/features/admin/hooks/useAdminAuth';
import {
  AdminDataToolbar,
  AdminPaginationBar,
  ConfirmDialog,
} from '@/features/admin/components/AdminDataToolbar';
import { formatPrice } from '@/lib/utils/format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

// ─── 1. Products Module View ─────────────────────────────────────────────────

export function ProductsAdminView({
  initialProducts = [],
  initialMeta,
}: {
  initialProducts?: CatalogProduct[];
  initialMeta?: PaginationMeta;
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('catalog.manage');

  const [products, setProducts] = React.useState<CatalogProduct[]>(initialProducts);
  const [meta, setMeta] = React.useState<PaginationMeta>(
    initialMeta ?? { current_page: 1, last_page: 1, per_page: 20, total: initialProducts.length }
  );
  const [search, setSearch] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [isLoading, setIsLoading] = React.useState(initialProducts.length === 0);
  const [showCreateForm, setShowCreateForm] = React.useState(false);
  const [newName, setNewName] = React.useState('');
  const [newDesc, setNewDesc] = React.useState('');
  const [newStatus, setNewStatus] = React.useState<'draft' | 'published'>('draft');
  const [confirmDelete, setConfirmDelete] = React.useState<CatalogProduct | null>(null);

  const loadProducts = React.useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await fetchAdminProducts({
        search: search || undefined,
        status: statusFilter || undefined,
        page,
      });
      setProducts(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    } finally {
      setIsLoading(false);
    }
  }, [search, statusFilter, page]);

  React.useEffect(() => {
    if (initialProducts.length === 0) {
      void loadProducts();
    }
  }, [initialProducts.length, loadProducts]);

  const handleCreateProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim()) return;
    await createAdminProduct({
      name: newName.trim(),
      description: newDesc.trim() || undefined,
      status: newStatus,
    });
    setNewName('');
    setNewDesc('');
    setShowCreateForm(false);
    await loadProducts();
  };

  const handleConfirmDelete = async () => {
    if (!confirmDelete) return;
    await deleteAdminProduct(confirmDelete.id);
    setConfirmDelete(null);
    await loadProducts();
  };

  const handleTogglePublish = async (product: CatalogProduct) => {
    if (product.status === 'published') {
      await unpublishAdminProduct(product.id);
    } else {
      await publishAdminProduct(product.id);
    }
    await loadProducts();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search products by name, slug, or SKU..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'status',
            label: 'Status',
            value: statusFilter,
            options: [
              { label: 'Published', value: 'published' },
              { label: 'Draft', value: 'draft' },
              { label: 'Archived', value: 'archived' },
            ],
            onChange: (val) => {
              setStatusFilter(val);
              setPage(1);
            },
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('products')}
        primaryAction={
          canManage
            ? {
                label: showCreateForm ? 'Close Form' : '+ New Product',
                onClick: () => setShowCreateForm((v) => !v),
              }
            : undefined
        }
      />

      {showCreateForm && canManage && (
        <form
          onSubmit={(e) => void handleCreateProduct(e)}
          className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
        >
          <h3 className="text-sm font-bold text-neutral-900">Create New Product</h3>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Input
              placeholder="Product Name *"
              value={newName}
              onChange={(e) => setNewName(e.target.value)}
              required
            />
            <Input
              placeholder="Short Description"
              value={newDesc}
              onChange={(e) => setNewDesc(e.target.value)}
            />
            <select
              value={newStatus}
              onChange={(e) => setNewStatus(e.target.value as 'draft' | 'published')}
              className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-xs"
            >
              <option value="draft">Draft</option>
              <option value="published">Published</option>
            </select>
          </div>
          <div className="mt-3 flex justify-end gap-2">
            <Button type="submit" size="sm">
              Save Product
            </Button>
          </div>
        </form>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Product</th>
              <th className="px-4 py-3">Category / Brand</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Variants</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {isLoading && products.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                  Loading catalog products...
                </td>
              </tr>
            ) : products.length === 0 ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                  No products match the current filters.
                </td>
              </tr>
            ) : (
              products.map((product) => (
                <tr key={product.id}>
                  <td className="px-4 py-3">
                    <div className="font-semibold text-neutral-900">{product.name}</div>
                    <div className="font-mono text-[11px] text-neutral-500">
                      /{product.slug}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {product.category?.name || 'Uncategorized'}
                    {product.brand?.name ? ` · ${product.brand.name}` : ''}
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      variant={product.status === 'published' ? 'default' : 'secondary'}
                    >
                      {product.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right font-medium text-neutral-800">
                    {product.variants?.length ?? 0}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <div className="inline-flex items-center gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => void handleTogglePublish(product)}
                        >
                          {product.status === 'published' ? 'Unpublish' : 'Publish'}
                        </Button>
                        <Button
                          type="button"
                          variant="destructive"
                          size="sm"
                          onClick={() => setConfirmDelete(product)}
                        >
                          Delete
                        </Button>
                      </div>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <AdminPaginationBar
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPageChange={setPage}
      />

      <ConfirmDialog
        open={confirmDelete !== null}
        title={`Delete Product "${confirmDelete?.name ?? ''}"?`}
        description="This action permanently deletes the product and its variants from the catalog and logs the event in the Audit Trail."
        confirmLabel="Delete Product"
        variant="destructive"
        onConfirm={() => void handleConfirmDelete()}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}

// ─── 2. Categories Module View ───────────────────────────────────────────────

export function CategoriesAdminView({
  initialCategories = [],
}: {
  initialCategories?: CatalogCategory[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('catalog.manage');

  const [categories, setCategories] = React.useState<CatalogCategory[]>(initialCategories);
  const [search, setSearch] = React.useState('');
  const [activeFilter, setActiveFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: initialCategories.length,
  });
  const [showCreateForm, setShowCreateForm] = React.useState(false);
  const [newName, setNewName] = React.useState('');
  const [confirmDelete, setConfirmDelete] = React.useState<CatalogCategory | null>(null);

  const loadCategories = React.useCallback(async () => {
    try {
      const res = await fetchAdminCategories({
        search: search || undefined,
        is_active: activeFilter || undefined,
        page,
      });
      setCategories(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, activeFilter, page]);

  React.useEffect(() => {
    if (initialCategories.length === 0) {
      void loadCategories();
    }
  }, [initialCategories.length, loadCategories]);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim()) return;
    await createAdminCategory({ name: newName.trim(), is_active: true });
    setNewName('');
    setShowCreateForm(false);
    await loadCategories();
  };

  const handleConfirmDelete = async () => {
    if (!confirmDelete) return;
    await deleteAdminCategory(confirmDelete.id);
    setConfirmDelete(null);
    await loadCategories();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search categories by name or slug..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'is_active',
            label: 'Visibility',
            value: activeFilter,
            options: [
              { label: 'Active', value: '1' },
              { label: 'Inactive', value: '0' },
            ],
            onChange: setActiveFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('categories')}
        primaryAction={
          canManage
            ? {
                label: showCreateForm ? 'Close Form' : '+ New Category',
                onClick: () => setShowCreateForm((v) => !v),
              }
            : undefined
        }
      />

      {showCreateForm && canManage && (
        <form
          onSubmit={(e) => void handleCreate(e)}
          className="flex flex-wrap items-center gap-3 rounded-xl border border-neutral-200 bg-white p-4"
        >
          <Input
            placeholder="Category Name *"
            value={newName}
            onChange={(e) => setNewName(e.target.value)}
            className="max-w-xs"
            required
          />
          <Button type="submit" size="sm">
            Create Category
          </Button>
        </form>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">Category</th>
              <th className="px-4 py-3">Slug</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {categories.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-4 py-8 text-center text-neutral-500">
                  No categories found.
                </td>
              </tr>
            ) : (
              categories.map((cat) => (
                <tr key={cat.id}>
                  <td className="px-4 py-3 font-semibold text-neutral-900">
                    {cat.name}
                  </td>
                  <td className="px-4 py-3 font-mono text-neutral-500">/{cat.slug}</td>
                  <td className="px-4 py-3">
                    <Badge variant={cat.is_active !== false ? 'default' : 'secondary'}>
                      {cat.is_active !== false ? 'Active' : 'Inactive'}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && (
                      <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={() => setConfirmDelete(cat)}
                      >
                        Delete
                      </Button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <AdminPaginationBar
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPageChange={setPage}
      />

      <ConfirmDialog
        open={confirmDelete !== null}
        title={`Delete Category "${confirmDelete?.name ?? ''}"?`}
        description="Child categories will be safely orphaned (parent_id set to null)."
        confirmLabel="Delete Category"
        variant="destructive"
        onConfirm={() => void handleConfirmDelete()}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}

// ─── 3. Variants Module View ─────────────────────────────────────────────────

export function VariantsAdminView({
  initialVariants = [],
}: {
  initialVariants?: CatalogVariant[];
}) {
  const { hasPermission } = useAdminAuth();
  const canManage = hasPermission('catalog.manage');

  const [variants, setVariants] = React.useState<CatalogVariant[]>(initialVariants);
  const [search, setSearch] = React.useState('');
  const [statusFilter, setStatusFilter] = React.useState('');
  const [page, setPage] = React.useState(1);
  const [meta, setMeta] = React.useState<PaginationMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: initialVariants.length,
  });
  const [showCreateForm, setShowCreateForm] = React.useState(false);
  const [productIdInput, setProductIdInput] = React.useState('');
  const [skuInput, setSkuInput] = React.useState('');
  const [colorInput, setColorInput] = React.useState('');
  const [sizeInput, setSizeInput] = React.useState('');
  const [pricePaisaInput, setPricePaisaInput] = React.useState('250000');
  const [confirmDelete, setConfirmDelete] = React.useState<CatalogVariant | null>(null);

  const loadVariants = React.useCallback(async () => {
    try {
      const res = await fetchAdminVariants({
        search: search || undefined,
        status: statusFilter || undefined,
        page,
      });
      setVariants(res.data);
      if (res.meta) {
        setMeta(res.meta);
      }
    } catch {
      // Handled gracefully
    }
  }, [search, statusFilter, page]);

  React.useEffect(() => {
    if (initialVariants.length === 0) {
      void loadVariants();
    }
  }, [initialVariants.length, loadVariants]);

  const handleCreateVariant = async (e: React.FormEvent) => {
    e.preventDefault();
    const pid = Number(productIdInput);
    const priceAmount = Number(pricePaisaInput);
    if (!pid || !skuInput.trim() || Number.isNaN(priceAmount)) return;
    await createAdminVariant(pid, {
      sku: skuInput.trim(),
      color: colorInput.trim() || undefined,
      size: sizeInput.trim() || undefined,
      price_amount: Math.round(priceAmount),
      status: 'active',
    });
    setSkuInput('');
    setShowCreateForm(false);
    await loadVariants();
  };

  const handleConfirmDelete = async () => {
    if (!confirmDelete || !confirmDelete.product_id) return;
    await deleteAdminVariant(confirmDelete.product_id, confirmDelete.id);
    setConfirmDelete(null);
    await loadVariants();
  };

  return (
    <div className="space-y-4">
      <AdminDataToolbar
        searchPlaceholder="Search variants by SKU, color, size, or product..."
        searchValue={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        filters={[
          {
            key: 'status',
            label: 'Status',
            value: statusFilter,
            options: [
              { label: 'Active', value: 'active' },
              { label: 'Inactive', value: 'inactive' },
              { label: 'Discontinued', value: 'discontinued' },
            ],
            onChange: setStatusFilter,
          },
        ]}
        totalCount={meta.total}
        onExportCsv={() => void downloadAdminCsvExport('variants')}
        primaryAction={
          canManage
            ? {
                label: showCreateForm ? 'Close Form' : '+ Add Variant',
                onClick: () => setShowCreateForm((v) => !v),
              }
            : undefined
        }
      />

      {showCreateForm && canManage && (
        <form
          onSubmit={(e) => void handleCreateVariant(e)}
          className="rounded-xl border border-neutral-200 bg-white p-5 shadow-2xs"
        >
          <h3 className="text-sm font-bold text-neutral-900">
            Create Product Variant (Integer Paisa Price)
          </h3>
          <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-5">
            <Input
              type="number"
              placeholder="Product ID *"
              value={productIdInput}
              onChange={(e) => setProductIdInput(e.target.value)}
              required
            />
            <Input
              placeholder="SKU (e.g. PNJ-BLK-M) *"
              value={skuInput}
              onChange={(e) => setSkuInput(e.target.value)}
              required
            />
            <Input
              placeholder="Color"
              value={colorInput}
              onChange={(e) => setColorInput(e.target.value)}
            />
            <Input
              placeholder="Size"
              value={sizeInput}
              onChange={(e) => setSizeInput(e.target.value)}
            />
            <Input
              type="number"
              placeholder="Price in Paisa (e.g. 250000)"
              value={pricePaisaInput}
              onChange={(e) => setPricePaisaInput(e.target.value)}
              required
            />
          </div>
          <div className="mt-3 flex justify-end">
            <Button type="submit" size="sm">
              Save Variant
            </Button>
          </div>
        </form>
      )}

      <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-white shadow-2xs">
        <table className="w-full text-left text-xs">
          <thead>
            <tr className="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wider text-neutral-500">
              <th className="px-4 py-3">SKU</th>
              <th className="px-4 py-3">Product</th>
              <th className="px-4 py-3">Attributes</th>
              <th className="px-4 py-3 text-right">Price (BDT)</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {variants.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                  No variants found.
                </td>
              </tr>
            ) : (
              variants.map((variant) => (
                <tr key={variant.id}>
                  <td className="px-4 py-3 font-mono font-semibold text-neutral-900">
                    {variant.sku}
                  </td>
                  <td className="px-4 py-3 text-neutral-700">
                    {variant.product?.name ?? `Product #${variant.product_id ?? '-'}`}
                  </td>
                  <td className="px-4 py-3 text-neutral-600">
                    {[variant.color, variant.size].filter(Boolean).join(' / ') || 'Standard'}
                  </td>
                  <td className="px-4 py-3 text-right font-semibold text-neutral-900">
                    {formatPrice({
                      amount: variant.price.amount,
                      currency: variant.price.currency || 'BDT',
                    })}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={variant.status === 'active' ? 'default' : 'secondary'}>
                      {variant.status}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {canManage && variant.product_id && (
                      <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={() => setConfirmDelete(variant)}
                      >
                        Delete
                      </Button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      <AdminPaginationBar
        currentPage={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPageChange={setPage}
      />

      <ConfirmDialog
        open={confirmDelete !== null}
        title={`Delete Variant "${confirmDelete?.sku ?? ''}"?`}
        description="Deleting this SKU removes it from active product options."
        confirmLabel="Delete Variant"
        variant="destructive"
        onConfirm={() => void handleConfirmDelete()}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}

