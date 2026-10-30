import * as React from 'react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
  size?: 'sm' | 'md' | 'lg';
}

export const Button: React.FC<ButtonProps> = ({
  children,
  variant = 'primary',
  size = 'md',
  style,
  disabled,
  ...props
}) => {
  const baseStyles: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: '8px',
    fontWeight: 600,
    cursor: disabled ? 'not-allowed' : 'pointer',
    transition: 'all 0.15s ease-in-out',
    border: '1px solid transparent',
    opacity: disabled ? 0.6 : 1,
    ...style,
  };

  const sizeStyles: Record<string, React.CSSProperties> = {
    sm: { padding: '6px 12px', fontSize: '13px' },
    md: { padding: '10px 18px', fontSize: '14px' },
    lg: { padding: '14px 24px', fontSize: '16px' },
  };

  const variantStyles: Record<string, React.CSSProperties> = {
    primary: {
      backgroundColor: '#18181b',
      color: '#fafafa',
      borderColor: '#18181b',
    },
    secondary: {
      backgroundColor: '#f4f4f5',
      color: '#18181b',
      borderColor: '#e4e4e7',
    },
    outline: {
      backgroundColor: 'transparent',
      color: '#18181b',
      borderColor: '#e4e4e7',
    },
    ghost: {
      backgroundColor: 'transparent',
      color: '#18181b',
    },
    danger: {
      backgroundColor: '#ef4444',
      color: '#ffffff',
      borderColor: '#ef4444',
    },
  };

  const computedStyle: React.CSSProperties = {
    ...baseStyles,
    ...(sizeStyles[size] || sizeStyles.md),
    ...(variantStyles[variant] || variantStyles.primary),
  };

  return (
    <button style={computedStyle} disabled={disabled} {...props}>
      {children}
    </button>
  );
};
