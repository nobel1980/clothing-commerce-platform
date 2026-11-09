import * as React from 'react';
import { cn } from '@/lib/utils/format';

export function Separator({
  className,
  style,
  ...props
}: React.HTMLAttributes<HTMLHRElement>) {
  return (
    <hr
      className={cn('border-t', className)}
      style={{
        border: 0,
        borderTop: '1px solid #e5e2dc',
        margin: '1rem 0',
        ...style,
      }}
      {...props}
    />
  );
}

