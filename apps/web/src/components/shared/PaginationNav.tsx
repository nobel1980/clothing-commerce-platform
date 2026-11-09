import * as React from 'react';
import Link from 'next/link';
import type { PaginationMeta } from '@clothing-commerce/types';
import { Button } from '@/components/ui/button';

export interface PaginationNavProps {
  meta?: PaginationMeta;
  basePath: string;
  searchParams?: Record<string, string | number | boolean | undefined>;
}

export function PaginationNav({
  meta,
  basePath,
  searchParams = {},
}: PaginationNavProps) {
  if (!meta || meta.last_page <= 1) {
    return null;
  }

  const buildPageHref = (page: number): string => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(searchParams)) {
      if (value !== undefined && value !== '' && key !== 'page') {
        params.set(key, String(value));
      }
    }
    if (page > 1) {
      params.set('page', String(page));
    }
    const qs = params.toString();
    return qs ? `${basePath}?${qs}` : basePath;
  };

  const currentPage = meta.current_page;
  const lastPage = meta.last_page;

  return (
    <nav
      aria-label="Pagination"
      className="mt-8 flex items-center justify-between border-t py-4"
    >
      <p className="text-sm text-muted">
        Showing page <strong>{currentPage}</strong> of <strong>{lastPage}</strong> ({meta.total}{' '}
        items)
      </p>
      <div className="flex items-center gap-2">
        {currentPage > 1 ? (
          <Link href={buildPageHref(currentPage - 1)} aria-label="Previous page">
            <Button variant="outline" size="sm">
              Previous
            </Button>
          </Link>
        ) : (
          <Button variant="outline" size="sm" disabled aria-disabled="true">
            Previous
          </Button>
        )}

        {currentPage < lastPage ? (
          <Link href={buildPageHref(currentPage + 1)} aria-label="Next page">
            <Button variant="outline" size="sm">
              Next
            </Button>
          </Link>
        ) : (
          <Button variant="outline" size="sm" disabled aria-disabled="true">
            Next
          </Button>
        )}
      </div>
    </nav>
  );
}

