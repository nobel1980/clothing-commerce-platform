'use client';

import * as React from 'react';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

export interface ErrorStateProps {
  title?: string;
  message: string;
  onRetry?: () => void;
}

export function ErrorState({
  title = 'Unable to load storefront data',
  message,
  onRetry,
}: ErrorStateProps) {
  return (
    <Alert variant="destructive" className="my-4">
      <div className="flex flex-col gap-3">
        <div>
          <h3 className="text-base font-semibold" style={{ marginBottom: '0.25rem' }}>
            {title}
          </h3>
          <p className="text-sm">{message}</p>
        </div>
        {onRetry && (
          <div>
            <Button variant="outline" size="sm" onClick={onRetry}>
              Try Again
            </Button>
          </div>
        )}
      </div>
    </Alert>
  );
}

