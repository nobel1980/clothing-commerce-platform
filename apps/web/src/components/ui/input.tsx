import * as React from 'react';
import { cn } from '@/lib/utils/format';

export type InputProps = React.InputHTMLAttributes<HTMLInputElement>;

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, style, ...props }, ref) => {
    return (
      <input
        ref={ref}
        className={cn('w-full rounded-md border bg-surface px-3 py-2 text-sm', className)}
        style={{
          height: '2.625rem',
          padding: '0.5rem 0.875rem',
          border: '1px solid #d6d3cd',
          borderRadius: '0.5rem',
          backgroundColor: '#ffffff',
          color: '#18181b',
          width: '100%',
          ...style,
        }}
        {...props}
      />
    );
  }
);
Input.displayName = 'Input';

