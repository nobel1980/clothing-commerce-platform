import * as React from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';

export interface EmptyStateProps {
  title: string;
  description: string;
  actionLabel?: string;
  actionHref?: string;
  onAction?: () => void;
  icon?: React.ReactNode;
}

export function EmptyState({
  title,
  description,
  actionLabel,
  actionHref,
  onAction,
  icon,
}: EmptyStateProps) {
  return (
    <section
      aria-label={title}
      className="flex flex-col items-center justify-center rounded-xl border bg-surface p-8 text-center"
      style={{
        padding: '3.5rem 1.5rem',
        backgroundColor: '#ffffff',
        border: '1px dashed #d6d3cd',
        borderRadius: '0.875rem',
      }}
    >
      {icon && (
        <div
          style={{
            marginBottom: '1rem',
            color: '#6b6860',
            display: 'inline-flex',
            padding: '0.875rem',
            borderRadius: '9999px',
            backgroundColor: '#f4f3ef',
          }}
        >
          {icon}
        </div>
      )}
      <h2 className="text-xl font-semibold tracking-tight" style={{ marginBottom: '0.5rem' }}>
        {title}
      </h2>
      <p
        className="text-sm text-muted"
        style={{ maxWidth: '28rem', color: '#6b6860', marginBottom: '1.5rem' }}
      >
        {description}
      </p>
      {actionLabel && actionHref && (
        <Link href={actionHref}>
          <Button variant="default">{actionLabel}</Button>
        </Link>
      )}
      {actionLabel && !actionHref && onAction && (
        <Button variant="default" onClick={onAction}>
          {actionLabel}
        </Button>
      )}
    </section>
  );
}

