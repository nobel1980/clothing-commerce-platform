import * as React from 'react';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  title?: string;
  subtitle?: string;
}

export const Card: React.FC<CardProps> = ({
  title,
  subtitle,
  children,
  style,
  ...props
}) => {
  return (
    <div
      style={{
        border: '1px solid #e4e4e7',
        borderRadius: '12px',
        padding: '20px',
        backgroundColor: '#ffffff',
        boxShadow: '0 1px 3px 0 rgba(0, 0, 0, 0.05)',
        ...style,
      }}
      {...props}
    >
      {(title || subtitle) && (
        <div style={{ marginBottom: '16px' }}>
          {title && <h3 style={{ fontSize: '18px', fontWeight: 600, margin: 0 }}>{title}</h3>}
          {subtitle && <p style={{ fontSize: '14px', color: '#71717a', marginTop: '4px', margin: 0 }}>{subtitle}</p>}
        </div>
      )}
      {children}
    </div>
  );
};
