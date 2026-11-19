'use client';

import * as React from 'react';
import type { AdminPermission, AdminStaffUser } from '@clothing-commerce/types';
import {
  fetchAdminMe,
  loginAdminStaff,
  logoutAdminStaff,
} from '@/features/admin/api/adminApi';

export interface AdminNavItem {
  key: string;
  label: string;
  href: string;
  group: 'Overview' | 'Catalog & Stock' | 'Commerce & Fulfilment' | 'Engagement' | 'Governance';
  requiredPermissions: AdminPermission[];
  managePermission?: AdminPermission;
  description: string;
}

export const ADMIN_NAV_ITEMS: readonly AdminNavItem[] = [
  {
    key: 'dashboard',
    label: 'Dashboard',
    href: '/admin',
    group: 'Overview',
    requiredPermissions: ['reports.view', 'orders.view'],
    description: 'Real-time sales, revenue, order flow, low-stock & best-seller metrics',
  },
  {
    key: 'products',
    label: 'Products',
    href: '/admin/products',
    group: 'Catalog & Stock',
    requiredPermissions: ['catalog.view'],
    managePermission: 'catalog.manage',
    description: 'Manage apparel catalog, publication status, brands & SEO metadata',
  },
  {
    key: 'categories',
    label: 'Categories',
    href: '/admin/categories',
    group: 'Catalog & Stock',
    requiredPermissions: ['catalog.view'],
    managePermission: 'catalog.manage',
    description: 'Hierarchical category tree, slugs, sort order & visibility',
  },
  {
    key: 'variants',
    label: 'Variants',
    href: '/admin/variants',
    group: 'Catalog & Stock',
    requiredPermissions: ['catalog.view'],
    managePermission: 'catalog.manage',
    description: 'SKUs, colors, sizes, integer paisa pricing & compare-at prices',
  },
  {
    key: 'inventory',
    label: 'Inventory',
    href: '/admin/inventory',
    group: 'Catalog & Stock',
    requiredPermissions: ['inventory.view'],
    managePermission: 'inventory.manage',
    description: 'Warehouse stock levels, reservations, adjustments & movement ledger',
  },
  {
    key: 'orders',
    label: 'Orders',
    href: '/admin/orders',
    group: 'Commerce & Fulfilment',
    requiredPermissions: ['orders.view'],
    managePermission: 'orders.manage',
    description: 'Order fulfilment lifecycle, snapshots, status history & cancellations',
  },
  {
    key: 'payments',
    label: 'Payments',
    href: '/admin/payments',
    group: 'Commerce & Fulfilment',
    requiredPermissions: ['payments.view'],
    managePermission: 'payments.manage',
    description: 'bKash, Nagad, SSLCommerz & COD transactions, verification & refunds',
  },
  {
    key: 'shipping',
    label: 'Shipping',
    href: '/admin/shipping',
    group: 'Commerce & Fulfilment',
    requiredPermissions: ['shipping.view'],
    managePermission: 'shipping.manage',
    description: 'Pathao, Steadfast & REDX consignments, live tracking & COD reconciliation',
  },
  {
    key: 'customers',
    label: 'Customers',
    href: '/admin/customers',
    group: 'Engagement',
    requiredPermissions: ['customers.view'],
    managePermission: 'customers.manage',
    description: 'Customer directory, lifetime spend, address book & account status',
  },
  {
    key: 'promotions',
    label: 'Promotions',
    href: '/admin/promotions',
    group: 'Engagement',
    requiredPermissions: ['promotions.view'],
    managePermission: 'promotions.manage',
    description: 'Discount coupons, minimum order rules, usage caps & redemptions',
  },
  {
    key: 'reviews',
    label: 'Reviews',
    href: '/admin/reviews',
    group: 'Engagement',
    requiredPermissions: ['reviews.view'],
    managePermission: 'reviews.manage',
    description: 'Verified buyer ratings, review moderation queue & approval workflow',
  },
  {
    key: 'reports',
    label: 'Reports',
    href: '/admin/reports',
    group: 'Overview',
    requiredPermissions: ['reports.view'],
    description: 'Sales trends, payment method breakdown, best sellers & stock alerts',
  },
  {
    key: 'settings',
    label: 'Settings',
    href: '/admin/settings',
    group: 'Governance',
    requiredPermissions: ['settings.view'],
    managePermission: 'settings.manage',
    description: 'Store profile, BDT commerce defaults, shipping fees & maintenance mode',
  },
  {
    key: 'users',
    label: 'Users & Permissions',
    href: '/admin/users',
    group: 'Governance',
    requiredPermissions: ['users.view', 'roles.view', 'audit.view'],
    managePermission: 'users.manage',
    description: 'Staff accounts, Spatie RBAC roles & permissions matrix, and Audit Trail',
  },
];

const ROLE_DEFAULT_PERMISSIONS: Record<string, readonly AdminPermission[]> = {
  super_admin: [
    'users.view',
    'users.manage',
    'roles.view',
    'roles.manage',
    'audit.view',
    'catalog.view',
    'catalog.manage',
    'inventory.view',
    'inventory.manage',
    'orders.view',
    'orders.manage',
    'customers.view',
    'customers.manage',
    'payments.view',
    'payments.manage',
    'shipping.view',
    'shipping.manage',
    'promotions.view',
    'promotions.manage',
    'reviews.view',
    'reviews.manage',
    'reports.view',
    'settings.view',
    'settings.manage',
  ],
  product_manager: [
    'catalog.view',
    'catalog.manage',
    'inventory.view',
    'reviews.view',
    'reviews.manage',
  ],
  inventory_manager: ['inventory.view', 'inventory.manage', 'catalog.view'],
  order_manager: [
    'orders.view',
    'orders.manage',
    'shipping.view',
    'shipping.manage',
    'customers.view',
    'payments.view',
  ],
  customer_support: ['orders.view', 'customers.view', 'reviews.view'],
  marketing_manager: [
    'promotions.view',
    'promotions.manage',
    'catalog.view',
    'reviews.view',
  ],
  report_viewer: ['reports.view', 'orders.view', 'inventory.view', 'catalog.view'],
};

export function userHasPermission(
  user: AdminStaffUser | null | undefined,
  permission: AdminPermission
): boolean {
  if (!user || user.status !== 'active') {
    return false;
  }

  if (
    user.role === 'super_admin' ||
    (Array.isArray(user.roles) && user.roles.includes('super_admin'))
  ) {
    return true;
  }

  if (Array.isArray(user.permissions) && user.permissions.length > 0) {
    return user.permissions.includes(permission);
  }

  const fallback = ROLE_DEFAULT_PERMISSIONS[user.role] ?? [];
  return fallback.includes(permission);
}

export function userHasAnyPermission(
  user: AdminStaffUser | null | undefined,
  permissions: readonly AdminPermission[]
): boolean {
  if (permissions.length === 0) {
    return true;
  }
  return permissions.some((perm) => userHasPermission(user, perm));
}

export function getPermittedNavItems(
  user: AdminStaffUser | null | undefined
): AdminNavItem[] {
  if (!user) {
    return [];
  }
  return ADMIN_NAV_ITEMS.filter((item) =>
    userHasAnyPermission(user, item.requiredPermissions)
  );
}

export interface AdminAuthContextValue {
  user: AdminStaffUser | null;
  isLoading: boolean;
  error: string | null;
  permittedNavItems: AdminNavItem[];
  hasPermission: (permission: AdminPermission) => boolean;
  hasAnyPermission: (permissions: readonly AdminPermission[]) => boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshSession: () => Promise<void>;
  setDemoStaffRole: (role: string) => void;
}

const AdminAuthContext = React.createContext<AdminAuthContextValue | null>(null);

export function AdminAuthProvider({
  children,
  initialUser = null,
}: {
  children: React.ReactNode;
  initialUser?: AdminStaffUser | null;
}) {
  const [user, setUser] = React.useState<AdminStaffUser | null>(initialUser);
  const [isLoading, setIsLoading] = React.useState<boolean>(initialUser === null);
  const [error, setError] = React.useState<string | null>(null);

  const refreshSession = React.useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await fetchAdminMe();
      setUser(response.data);
    } catch {
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  React.useEffect(() => {
    if (initialUser === null) {
      void refreshSession();
    }
  }, [initialUser, refreshSession]);

  const login = React.useCallback(async (email: string, password: string) => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await loginAdminStaff({ email, password });
      if (response.token && typeof window !== 'undefined') {
        try {
          window.localStorage.setItem('ccp_admin_token', response.token);
        } catch {
          // Ignore storage errors
        }
      }
      setUser(response.data);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Staff authentication failed.';
      setError(msg);
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, []);

  const logout = React.useCallback(async () => {
    try {
      await logoutAdminStaff();
    } catch {
      // Ignore logout error if token already expired
    } finally {
      if (typeof window !== 'undefined') {
        try {
          window.localStorage.removeItem('ccp_admin_token');
        } catch {
          // Ignore
        }
      }
      setUser(null);
    }
  }, []);

  const setDemoStaffRole = React.useCallback((role: string) => {
    const perms = ROLE_DEFAULT_PERMISSIONS[role] ?? [];
    setUser((prev) =>
      prev
        ? {
            ...prev,
            role,
            roles: [role],
            permissions: [...perms],
          }
        : null
    );
  }, []);

  const hasPermission = React.useCallback(
    (permission: AdminPermission) => userHasPermission(user, permission),
    [user]
  );

  const hasAnyPermission = React.useCallback(
    (permissions: readonly AdminPermission[]) => userHasAnyPermission(user, permissions),
    [user]
  );

  const permittedNavItems = React.useMemo(() => getPermittedNavItems(user), [user]);

  const value = React.useMemo<AdminAuthContextValue>(
    () => ({
      user,
      isLoading,
      error,
      permittedNavItems,
      hasPermission,
      hasAnyPermission,
      login,
      logout,
      refreshSession,
      setDemoStaffRole,
    }),
    [
      user,
      isLoading,
      error,
      permittedNavItems,
      hasPermission,
      hasAnyPermission,
      login,
      logout,
      refreshSession,
      setDemoStaffRole,
    ]
  );

  return (
    <AdminAuthContext.Provider value={value}>{children}</AdminAuthContext.Provider>
  );
}

export function useAdminAuth(): AdminAuthContextValue {
  const ctx = React.useContext(AdminAuthContext);
  if (!ctx) {
    throw new Error('useAdminAuth must be used within an AdminAuthProvider');
  }
  return ctx;
}

