'use client';

import * as React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { AdminPermission } from '@clothing-commerce/types';
import {
  ADMIN_NAV_ITEMS,
  useAdminAuth,
} from '@/features/admin/hooks/useAdminAuth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';

export interface AdminShellProps {
  children: React.ReactNode;
  requiredPermissions?: readonly AdminPermission[];
  title?: string;
  subtitle?: string;
}

export function AdminShell({
  children,
  requiredPermissions,
  title,
  subtitle,
}: AdminShellProps) {
  const pathname = usePathname() || '/admin';
  const {
    user,
    isLoading,
    error,
    permittedNavItems,
    hasAnyPermission,
    login,
    logout,
  } = useAdminAuth();

  const [mobileMenuOpen, setMobileMenuOpen] = React.useState(false);
  const [loginEmail, setLoginEmail] = React.useState('admin@clothing-commerce.test');
  const [loginPassword, setLoginPassword] = React.useState('');
  const [loginError, setLoginError] = React.useState<string | null>(null);
  const [isLoggingIn, setIsLoggingIn] = React.useState(false);

  const activeNav = React.useMemo(() => {
    const defaultItem = ADMIN_NAV_ITEMS[0]!;
    if (pathname === '/admin') {
      return defaultItem;
    }
    return (
      ADMIN_NAV_ITEMS.find(
        (item) => item.href !== '/admin' && pathname.startsWith(item.href)
      ) ?? defaultItem
    );
  }, [pathname]);

  const effectivePermissions =
    requiredPermissions ?? activeNav.requiredPermissions;

  const handleLoginSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoginError(null);
    setIsLoggingIn(true);
    try {
      await login(loginEmail.trim(), loginPassword);
      setLoginPassword('');
    } catch (err) {
      setLoginError(
        err instanceof Error ? err.message : 'Unable to sign in to Admin Portal.'
      );
    } finally {
      setIsLoggingIn(false);
    }
  };

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-neutral-100 p-6">
        <div className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
          <p className="text-xs font-semibold uppercase tracking-widest text-neutral-500">
            Clothing Commerce Platform
          </p>
          <h2 className="mt-2 text-lg font-semibold text-neutral-900">
            Verifying Staff Credentials & RBAC Permissions...
          </h2>
        </div>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-neutral-950 p-4">
        <div className="w-full max-w-md rounded-2xl border border-neutral-800 bg-white p-8 shadow-2xl">
          <div className="mb-6">
            <Badge variant="default">Staff Backoffice Portal</Badge>
            <h1 className="mt-3 text-2xl font-bold tracking-tight text-neutral-900">
              Admin Sign In
            </h1>
            <p className="mt-1 text-sm text-neutral-600">
              Authenticate with your staff credentials. All actions are recorded in the Audit Trail.
            </p>
          </div>

          {(loginError || error) && (
            <div
              role="alert"
              className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-700"
            >
              {loginError || error}
            </div>
          )}

          <form onSubmit={handleLoginSubmit} className="space-y-4">
            <div>
              <label
                htmlFor="admin-login-email"
                className="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-700"
              >
                Staff Email
              </label>
              <Input
                id="admin-login-email"
                type="email"
                required
                value={loginEmail}
                onChange={(e) => setLoginEmail(e.target.value)}
                placeholder="admin@clothing-commerce.test"
              />
            </div>

            <div>
              <label
                htmlFor="admin-login-password"
                className="mb-1 block text-xs font-semibold uppercase tracking-wider text-neutral-700"
              >
                Password
              </label>
              <Input
                id="admin-login-password"
                type="password"
                required
                value={loginPassword}
                onChange={(e) => setLoginPassword(e.target.value)}
                placeholder="••••••••••••"
              />
            </div>

            <Button type="submit" className="w-full" disabled={isLoggingIn}>
              {isLoggingIn ? 'Signing In...' : 'Sign In to Admin Portal'}
            </Button>
          </form>

          <div className="mt-6 border-t border-neutral-100 pt-4 text-center">
            <Link
              href="/"
              className="text-xs font-medium text-neutral-500 hover:text-neutral-900"
            >
              ← Return to Public Storefront
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const isAuthorizedForCurrentModule = hasAnyPermission(effectivePermissions);

  const groups: Array<
    'Overview' | 'Catalog & Stock' | 'Commerce & Fulfilment' | 'Engagement' | 'Governance'
  > = [
    'Overview',
    'Catalog & Stock',
    'Commerce & Fulfilment',
    'Engagement',
    'Governance',
  ];

  return (
    <div className="flex min-h-screen bg-neutral-100 text-neutral-900">
      {/* Desktop Sidebar Navigation */}
      <aside
        aria-label="Admin Sidebar Navigation"
        className="hidden w-68 shrink-0 flex-col border-r border-neutral-800 bg-neutral-950 text-neutral-100 lg:flex"
      >
        <div className="flex h-16 items-center justify-between border-b border-neutral-800 px-5">
          <div>
            <Link href="/admin" className="text-base font-bold tracking-tight text-white">
              ATELIER ADMIN
            </Link>
            <p className="text-[11px] text-neutral-400">
              Centralized Commerce Backoffice
            </p>
          </div>
          <Badge variant="outline" className="border-neutral-700 text-[10px] text-neutral-300">
            BDT
          </Badge>
        </div>

        <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
          {groups.map((groupName) => {
            const groupItems = permittedNavItems.filter((i) => i.group === groupName);
            if (groupItems.length === 0) {
              return null;
            }

            return (
              <div key={groupName}>
                <p className="px-3 text-[10px] font-semibold uppercase tracking-widest text-neutral-500">
                  {groupName}
                </p>
                <ul className="mt-1.5 space-y-1">
                  {groupItems.map((item) => {
                    const isActive =
                      item.href === '/admin'
                        ? pathname === '/admin'
                        : pathname.startsWith(item.href);

                    return (
                      <li key={item.key}>
                        <Link
                          href={item.href}
                          className={`flex items-center justify-between rounded-lg px-3 py-2 text-xs font-medium transition ${
                            isActive
                              ? 'bg-white text-neutral-950 shadow-xs'
                              : 'text-neutral-300 hover:bg-neutral-900 hover:text-white'
                          }`}
                        >
                          <span>{item.label}</span>
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              </div>
            );
          })}
        </nav>

        <div className="border-t border-neutral-800 p-4 text-xs">
          <div className="flex items-center justify-between">
            <span className="font-semibold text-white">{user.name}</span>
            <span className="rounded bg-neutral-800 px-2 py-0.5 text-[10px] font-medium text-neutral-300">
              {user.role}
            </span>
          </div>
          <p className="mt-0.5 truncate text-[11px] text-neutral-400">{user.email}</p>
          <div className="mt-3 flex items-center justify-between gap-2">
            <Link
              href="/"
              className="text-[11px] text-neutral-400 hover:text-white"
            >
              Storefront ↗
            </Link>
            <button
              type="button"
              onClick={() => void logout()}
              className="text-[11px] font-medium text-red-400 hover:text-red-300"
            >
              Sign Out
            </button>
          </div>
        </div>
      </aside>

      {/* Mobile Drawer Sidebar */}
      {mobileMenuOpen && (
        <div className="fixed inset-0 z-50 flex lg:hidden">
          <div
            className="fixed inset-0 bg-neutral-950/60"
            onClick={() => setMobileMenuOpen(false)}
          />
          <aside className="relative z-10 flex w-72 flex-col bg-neutral-950 text-neutral-100">
            <div className="flex h-16 items-center justify-between border-b border-neutral-800 px-5">
              <span className="text-base font-bold text-white">ATELIER ADMIN</span>
              <button
                type="button"
                onClick={() => setMobileMenuOpen(false)}
                className="rounded p-1 text-xs text-neutral-400 hover:text-white"
              >
                Close ✕
              </button>
            </div>
            <nav className="flex-1 overflow-y-auto px-3 py-4">
              {permittedNavItems.map((item) => (
                <Link
                  key={item.key}
                  href={item.href}
                  onClick={() => setMobileMenuOpen(false)}
                  className="block rounded-lg px-3 py-2 text-xs font-medium text-neutral-200 hover:bg-neutral-900"
                >
                  {item.label}
                </Link>
              ))}
            </nav>
          </aside>
        </div>
      )}

      {/* Main Content Area */}
      <div className="flex min-w-0 flex-1 flex-col">
        {/* Top Bar */}
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-neutral-200 bg-white px-4 sm:px-6">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setMobileMenuOpen(true)}
              className="rounded-lg border border-neutral-300 px-2.5 py-1.5 text-xs font-semibold text-neutral-700 lg:hidden"
              aria-label="Open navigation menu"
            >
              Menu ☰
            </button>
            <div>
              <h1 className="text-base font-bold text-neutral-900 sm:text-lg">
                {title || activeNav.label}
              </h1>
              <p className="hidden text-xs text-neutral-500 sm:block">
                {subtitle || activeNav.description}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <Badge variant="secondary" className="hidden sm:inline-flex">
              Role: {user.role}
            </Badge>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => void logout()}
            >
              Sign Out
            </Button>
          </div>
        </header>

        {/* Page Body */}
        <main className="flex-1 p-4 sm:p-6">
          {!isAuthorizedForCurrentModule ? (
            <div
              role="alert"
              className="mx-auto my-12 max-w-lg rounded-2xl border border-red-200 bg-white p-8 text-center shadow-xs"
            >
              <Badge variant="destructive">403 Forbidden — Insufficient Permissions</Badge>
              <h2 className="mt-3 text-xl font-bold text-neutral-900">
                Access Restricted
              </h2>
              <p className="mt-2 text-sm text-neutral-600">
                Your staff role (<strong>{user.role}</strong>) does not have the required
                permission ({effectivePermissions.join(' or ')}) to access this module.
              </p>
            </div>
          ) : (
            children
          )}
        </main>
      </div>
    </div>
  );
}
