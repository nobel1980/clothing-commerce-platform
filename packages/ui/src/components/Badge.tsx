import * as React from 'react';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'default' | 'success' | 'warning' | 'error' | 'outline';
}

export const Badge: React.FC<BadgeProps> = ({
  children,
  variant = 'default',
  style,
  ...props
}) => {
  const baseStyles: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '4px',
    borderRadius: '9999px',
    padding: '2px 8px',
    fontSize: '12px',
    fontWeight: 500,
    lineHeight: 1.25,
    ...style,
  };

  const variantStyles: Record<string, React.CSSProperties> = {
    default: { backgroundColor: '#f4f4f5', color: '#18181b' },
    success: { backgroundColor: '#dcfce7', color: '#15803d' },
    warning: { backgroundColor: '#fef3c7', color: '#b45309' },
    error: { backgroundColor: '#fee2e2', color: '#b91c1c' },
    outline: { backgroundColor: 'transparent', border: '1px solid #e4e4e7', color: '#71717a' },
  };

  return (
    <span style={{ ...baseStyles, ...variantStyles[variant] }} {...props}>
      {children}
    </span>
  );
};
