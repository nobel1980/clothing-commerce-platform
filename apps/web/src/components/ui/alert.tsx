import * as React from 'react';
import { cn } from '@/lib/utils/format';

export interface AlertProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'default' | 'destructive' | 'success';
}

export function Alert({
  className,
  variant = 'default',
  style,
  ...props
}: AlertProps) {
  const variantStyles: Record<string, React.CSSProperties> = {
    default: {
      backgroundColor: '#f4f3ef',
      borderColor: '#e5e2dc',
      color: '#18181b',
    },
    destructive: {
      backgroundColor: '#fef2f2',
      borderColor: '#fecaca',
      color: '#991b1b',
    },
    success: {
      backgroundColor: '#f0fdf4',
      borderColor: '#bbf7d0',
      color: '#166534',
    },
  };

  return (
    <div
      role="alert"
      className={cn('rounded-lg border p-4 text-sm', className)}
      style={{
        padding: '1rem',
        borderRadius: '0.625rem',
        border: '1px solid',
        ...variantStyles[variant],
        ...style,
      }}
      {...props}
    />
  );
}

