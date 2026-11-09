import * as React from 'react';
import { cn } from '@/lib/utils/format';

export interface UiBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'default' | 'secondary' | 'outline' | 'success' | 'warning' | 'destructive';
}

export function Badge({
  className,
  variant = 'default',
  style,
  ...props
}: UiBadgeProps) {
  const variantStyles: Record<string, React.CSSProperties> = {
    default: { backgroundColor: '#18181b', color: '#fafafa' },
    secondary: { backgroundColor: '#f4f3ef', color: '#18181b' },
    outline: { backgroundColor: 'transparent', border: '1px solid #d6d3cd', color: '#57534e' },
    success: { backgroundColor: '#dcfce7', color: '#15803d' },
    warning: { backgroundColor: '#fef3c7', color: '#b45309' },
    destructive: { backgroundColor: '#fee2e2', color: '#b91c1c' },
  };

  return (
    <span
      className={cn('inline-flex items-center rounded-full text-xs font-medium', className)}
      style={{
        padding: '0.1875rem 0.625rem',
        letterSpacing: '0.02em',
        ...variantStyles[variant],
        ...style,
      }}
      {...props}
    />
  );
}

