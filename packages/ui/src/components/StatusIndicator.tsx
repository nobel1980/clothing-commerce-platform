import * as React from 'react';

export interface StatusIndicatorProps {
  status: 'healthy' | 'degraded' | 'unhealthy' | 'pending';
  label?: string;
}

export const StatusIndicator: React.FC<StatusIndicatorProps> = ({ status, label }) => {
  const colors: Record<string, string> = {
    healthy: '#22c55e',
    degraded: '#eab308',
    unhealthy: '#ef4444',
    pending: '#9ca3af',
  };

  const color = colors[status] || colors.pending;

  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', fontSize: '13px' }}>
      <span
        style={{
          width: '8px',
          height: '8px',
          borderRadius: '50%',
          backgroundColor: color,
          boxShadow: `0 0 6px ${color}`,
        }}
      />
      {label && <span>{label}</span>}
    </div>
  );
};
