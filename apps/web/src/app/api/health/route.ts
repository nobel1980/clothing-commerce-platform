import { NextResponse } from 'next/server';
import type { HealthCheckResult } from '@clothing-commerce/types';

export const dynamic = 'force-dynamic';

export async function GET() {
  const healthData: HealthCheckResult = {
    status: 'healthy',
    service: 'clothing-commerce-web',
    timestamp: new Date().toISOString(),
    version: '0.1.0',
    uptimeSeconds: Math.floor(process.uptime()),
    checks: {
      database: { status: 'ok', message: 'Delegated to Laravel API' },
      redis: { status: 'ok', message: 'Delegated to Laravel API' },
    },
  };

  return NextResponse.json(healthData, {
    status: 200,
    headers: {
      'Cache-Control': 'no-store, no-cache, must-revalidate',
    },
  });
}
