import * as React from 'react';
import { cn } from '@/lib/utils/format';

export interface UiButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive';
  size?: 'sm' | 'default' | 'lg' | 'icon';
}

export const Button = React.forwardRef<HTMLButtonElement, UiButtonProps>(
  ({ className, variant = 'default', size = 'default', style, disabled, ...props }, ref) => {
    const variantStyles: Record<string, React.CSSProperties> = {
      default: {
        backgroundColor: '#18181b',
        color: '#fafafa',
        border: '1px solid #18181b',
      },
      secondary: {
        backgroundColor: '#f4f3ef',
        color: '#18181b',
        border: '1px solid #e5e2dc',
      },
      outline: {
        backgroundColor: 'transparent',
        color: '#18181b',
        border: '1px solid #d6d3cd',
      },
      ghost: {
        backgroundColor: 'transparent',
        color: '#18181b',
        border: '1px solid transparent',
      },
      destructive: {
        backgroundColor: '#dc2626',
        color: '#ffffff',
        border: '1px solid #dc2626',
      },
    };

    const sizeStyles: Record<string, React.CSSProperties> = {
      sm: { height: '2.125rem', padding: '0 0.75rem', fontSize: '0.8125rem' },
      default: { height: '2.625rem', padding: '0 1.25rem', fontSize: '0.875rem' },
      lg: { height: '3.125rem', padding: '0 1.75rem', fontSize: '0.9375rem' },
      icon: { height: '2.5rem', width: '2.5rem', padding: 0 },
    };

    return (
      <button
        ref={ref}
        disabled={disabled}
        className={cn('inline-flex items-center justify-center rounded-md font-medium', className)}
        style={{
          cursor: disabled ? 'not-allowed' : 'pointer',
          opacity: disabled ? 0.55 : 1,
          transition: 'all 0.15s ease',
          letterSpacing: '0.01em',
          gap: '0.5rem',
          ...sizeStyles[size],
          ...variantStyles[variant],
          ...style,
        }}
        {...props}
      />
    );
  }
);
Button.displayName = 'Button';

