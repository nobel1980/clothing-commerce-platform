'use client';

import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

export interface ConfirmDialogProps {
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'default' | 'destructive';
  requireReason?: boolean;
  reasonLabel?: string;
  reasonPlaceholder?: string;
  isSubmitting?: boolean;
  onConfirm: (reason?: string) => void;
  onCancel: () => void;
}

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel = 'Confirm Action',
  cancelLabel = 'Cancel',
  variant = 'default',
  requireReason = false,
  reasonLabel = 'Reason / Audit Note',
  reasonPlaceholder = 'Enter reason for audit log...',
  isSubmitting = false,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const [reason, setReason] = React.useState('');

  React.useEffect(() => {
    if (!open) {
      setReason('');
    }
  }, [open]);

  if (!open) {
    return null;
  }

  const canSubmit = !requireReason || reason.trim().length > 0;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/60 p-4 backdrop-blur-xs"
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="admin-confirm-dialog-title"
      aria-describedby="admin-confirm-dialog-desc"
    >
      <div className="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-2xl">
        <div className="flex items-start justify-between gap-3">
          <div>
            <Badge variant={variant === 'destructive' ? 'destructive' : 'secondary'}>
              {variant === 'destructive' ? 'Destructive Action' : 'Confirmation Required'}
            </Badge>
            <h3
              id="admin-confirm-dialog-title"
              className="mt-2 text-lg font-semibold text-neutral-900"
            >
              {title}
            </h3>
          </div>
        </div>

        <p
          id="admin-confirm-dialog-desc"
          className="mt-2 text-sm leading-relaxed text-neutral-600"
        >
          {description}
        </p>

        {requireReason && (
          <div className="mt-4">
            <label
              htmlFor="admin-confirm-reason-input"
              className="mb-1.5 block text-xs font-medium uppercase tracking-wider text-neutral-700"
            >
              {reasonLabel} *
            </label>
            <Input
              id="admin-confirm-reason-input"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder={reasonPlaceholder}
              required
            />
          </div>
        )}

        <div className="mt-6 flex items-center justify-end gap-3">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={isSubmitting}
          >
            {cancelLabel}
          </Button>
          <Button
            type="button"
            variant={variant === 'destructive' ? 'destructive' : 'default'}
            disabled={!canSubmit || isSubmitting}
            onClick={() => onConfirm(reason.trim() || undefined)}
          >
            {isSubmitting ? 'Processing...' : confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}

export interface FilterConfig {
  key: string;
  label: string;
  value: string;
  options: Array<{ label: string; value: string }>;
  onChange: (value: string) => void;
}

export interface AdminDataToolbarProps {
  searchPlaceholder?: string;
  searchValue: string;
  onSearchChange: (val: string) => void;
  filters?: FilterConfig[];
  onExportCsv?: () => void;
  exportLabel?: string;
  isExporting?: boolean;
  primaryAction?: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
  };
  totalCount?: number;
}

export function AdminDataToolbar({
  searchPlaceholder = 'Search records...',
  searchValue,
  onSearchChange,
  filters = [],
  onExportCsv,
  exportLabel = 'Export CSV',
  isExporting = false,
  primaryAction,
  totalCount,
}: AdminDataToolbarProps) {
  return (
    <div className="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 shadow-2xs lg:flex-row lg:items-center lg:justify-between">
      <div className="flex flex-1 flex-wrap items-center gap-3">
        <div className="min-w-[220px] flex-1 sm:max-w-xs">
          <Input
            type="search"
            aria-label={searchPlaceholder}
            placeholder={searchPlaceholder}
            value={searchValue}
            onChange={(e) => onSearchChange(e.target.value)}
          />
        </div>

        {filters.map((filter) => (
          <div key={filter.key} className="flex items-center gap-1.5">
            <label
              htmlFor={`admin-filter-${filter.key}`}
              className="text-xs font-medium text-neutral-500"
            >
              {filter.label}:
            </label>
            <select
              id={`admin-filter-${filter.key}`}
              value={filter.value}
              onChange={(e) => filter.onChange(e.target.value)}
              className="h-9 rounded-md border border-neutral-300 bg-white px-2.5 text-xs font-medium text-neutral-800 focus:border-neutral-900 focus:outline-hidden"
            >
              <option value="">All</option>
              {filter.options.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
        ))}

        {typeof totalCount === 'number' && (
          <span className="text-xs font-medium text-neutral-500">
            {totalCount} {totalCount === 1 ? 'record' : 'records'}
          </span>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        {onExportCsv && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onExportCsv}
            disabled={isExporting}
          >
            {isExporting ? 'Exporting...' : exportLabel}
          </Button>
        )}

        {primaryAction && (
          <Button
            type="button"
            size="sm"
            onClick={primaryAction.onClick}
            disabled={primaryAction.disabled}
          >
            {primaryAction.label}
          </Button>
        )}
      </div>
    </div>
  );
}

export interface AdminPaginationBarProps {
  currentPage: number;
  lastPage: number;
  total: number;
  perPage?: number;
  onPageChange: (page: number) => void;
}

export function AdminPaginationBar({
  currentPage,
  lastPage,
  total,
  onPageChange,
}: AdminPaginationBarProps) {
  if (lastPage <= 1 && total === 0) {
    return null;
  }

  return (
    <div
      className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-white px-4 py-3 text-xs text-neutral-600"
      aria-label="Table Pagination"
    >
      <div>
        Showing page <span className="font-semibold text-neutral-900">{currentPage}</span> of{' '}
        <span className="font-semibold text-neutral-900">{Math.max(1, lastPage)}</span> ({total}{' '}
        total records)
      </div>

      <div className="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(currentPage - 1)}
        >
          Previous
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={currentPage >= lastPage}
          onClick={() => onPageChange(currentPage + 1)}
        >
          Next
        </Button>
      </div>
    </div>
  );
}

