import * as React from 'react';
import { cn } from '@/lib/utils/format';

export function Card({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('rounded-xl border bg-surface shadow-sm', className)}
      style={{
        backgroundColor: '#ffffff',
        border: '1px solid #e5e2dc',
        borderRadius: '0.75rem',
        ...style,
      }}
      {...props}
    />
  );
}

export function CardHeader({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('flex flex-col gap-1 p-6', className)}
      style={{ padding: '1.5rem 1.5rem 0.75rem', ...style }}
      {...props}
    />
  );
}

export function CardTitle({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h3
      className={cn('text-lg font-semibold tracking-tight', className)}
      style={{ margin: 0, ...style }}
      {...props}
    />
  );
}

export function CardDescription({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLParagraphElement>) {
  return (
    <p
      className={cn('text-sm text-muted', className)}
      style={{ color: '#6b6860', margin: 0, ...style }}
      {...props}
    />
  );
}

export function CardContent({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('p-6', className)}
      style={{ padding: '1.25rem 1.5rem 1.5rem', ...style }}
      {...props}
    />
  );
}

