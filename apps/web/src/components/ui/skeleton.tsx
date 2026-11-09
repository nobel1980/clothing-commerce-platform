import * as React from 'react';
import { cn } from '@/lib/utils/format';

export function Skeleton({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('animate-pulse rounded-md bg-muted', className)}
      style={{
        backgroundColor: '#ece9e2',
        borderRadius: '0.5rem',
        ...style,
      }}
      {...props}
    />
  );
}

