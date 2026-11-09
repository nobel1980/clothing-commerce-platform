'use client';

import * as React from 'react';
import Link from 'next/link';
import { MapPin, Package, User as UserIcon, Plus, Trash2, CheckCircle2 } from 'lucide-react';
import type { CustomerAddress, CustomerProfile } from '@clothing-commerce/types';
import { useQuery, useMutation, useQueryClient } from '@/lib/query';
import {
  fetchCustomerProfile,
  updateCustomerProfile,
  fetchCustomerAddresses,
  createCustomerAddress,
  setDefaultCustomerAddress,
  deleteCustomerAddress,
  logoutCustomer,
} from '@/features/customers/api/customerApi';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert } from '@/components/ui/alert';

export function AccountDashboard() {
  const queryClient = useQueryClient();

  const {
    data: profile,
    isLoading: isProfileLoading,
    error: profileError,
    refetch: refetchProfile,
  } = useQuery<CustomerProfile>({
    queryKey: ['customer-profile'],
    queryFn: ({ signal }) => fetchCustomerProfile(signal),
    staleTime: 0,
  });

  const {
    data: addresses = [],
    isLoading: isAddressesLoading,
    refetch: refetchAddresses,
  } = useQuery<CustomerAddress[]>({
    queryKey: ['customer-addresses'],
    queryFn: ({ signal }) => fetchCustomerAddresses(signal),
    enabled: Boolean(profile),
    staleTime: 0,
  });

  const [name, setName] = React.useState('');
  const [phone, setPhone] = React.useState('');
  const [profileSaved, setProfileSaved] = React.useState(false);

  // New Address Form State
  const [showAddressForm, setShowAddressForm] = React.useState(false);
  const [recipientName, setRecipientName] = React.useState('');
  const [recipientPhone, setRecipientPhone] = React.useState('');
  const [division, setDivision] = React.useState('Dhaka');
  const [district, setDistrict] = React.useState('Dhaka');
  const [upazilaThana, setUpazilaThana] = React.useState('Gulshan');
  const [streetAddress, setStreetAddress] = React.useState('');
  const [postalCode, setPostalCode] = React.useState('1212');
  const [addressType, setAddressType] = React.useState<'home' | 'office' | 'other'>('home');

  React.useEffect(() => {
    if (profile) {
      setName(profile.name);
      setPhone(profile.phone ?? '');
    }
  }, [profile]);

  const updateProfileMutation = useMutation({
    mutationFn: () => updateCustomerProfile({ name, phone }),
    onSuccess: (updated) => {
      queryClient.setQueryData(['customer-profile'], updated);
      setProfileSaved(true);
    },
  });

  const addAddressMutation = useMutation({
    mutationFn: () =>
      createCustomerAddress({
        name: recipientName,
        phone: recipientPhone,
        division,
        district,
        upazila_thana: upazilaThana,
        street_address: streetAddress,
        postal_code: postalCode,
        type: addressType,
        is_default: addresses.length === 0,
      }),
    onSuccess: async () => {
      setShowAddressForm(false);
      setStreetAddress('');
      await refetchAddresses();
    },
  });

  const setDefaultMutation = useMutation({
    mutationFn: (id: number) => setDefaultCustomerAddress(id),
    onSuccess: async () => {
      await refetchAddresses();
    },
  });

  const deleteAddressMutation = useMutation({
    mutationFn: (id: number) => deleteCustomerAddress(id),
    onSuccess: async () => {
      await refetchAddresses();
    },
  });

  const logoutMutation = useMutation({
    mutationFn: () => logoutCustomer(),
    onSuccess: () => {
      queryClient.invalidateQueries();
      void refetchProfile();
    },
  });

  if (isProfileLoading) {
    return (
      <div aria-busy="true" aria-label="Loading customer account" className="two-col-layout">
        <Skeleton style={{ width: '100%', height: '16rem' }} />
        <Skeleton style={{ width: '100%', height: '16rem' }} />
      </div>
    );
  }

  if (profileError || !profile) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Sign In to View Your Account</CardTitle>
          <CardDescription>
            Your session is not currently active. Sign in with your customer credentials or create
            a new account to manage your profile, saved addresses, and orders.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-3">
          <Link href="/login">
            <Button variant="default">Sign In</Button>
          </Link>
          <Link href="/register">
            <Button variant="outline">Create Account</Button>
          </Link>
          <Link href="/account/orders">
            <Button variant="secondary">
              <Package size={15} />
              View Order History
            </Button>
          </Link>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-8">
      {/* Account Header Banner */}
      <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-surface p-6">
        <div className="flex items-center gap-4">
          <div
            style={{
              width: '3.25rem',
              height: '3.25rem',
              borderRadius: '9999px',
              backgroundColor: '#18181b',
              color: '#fafafa',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <UserIcon size={20} />
          </div>
          <div>
            <h2 className="text-xl font-bold">{profile.name}</h2>
            <p className="text-sm text-muted">{profile.email}</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Link href="/account/orders">
            <Button variant="secondary">
              <Package size={16} />
              Order History
            </Button>
          </Link>
          <Button
            type="button"
            variant="outline"
            onClick={() => logoutMutation.mutate()}
            disabled={logoutMutation.isPending}
          >
            Sign Out
          </Button>
        </div>
      </div>

      <div className="two-col-layout">
        {/* Profile Details */}
        <Card>
          <CardHeader>
            <CardTitle>Personal Details</CardTitle>
            <CardDescription>
              Keep your contact information updated for delivery notifications.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                setProfileSaved(false);
                updateProfileMutation.mutate();
              }}
              className="flex flex-col gap-4"
            >
              {profileSaved && (
                <Alert variant="success">Profile updated successfully.</Alert>
              )}

              <div>
                <label htmlFor="acc-name" className="mb-1 block text-xs font-medium">
                  Full Name
                </label>
                <Input
                  id="acc-name"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                />
              </div>

              <div>
                <label htmlFor="acc-email" className="mb-1 block text-xs font-medium">
                  Email Address
                </label>
                <Input id="acc-email" value={profile.email} disabled />
              </div>

              <div>
                <label htmlFor="acc-phone" className="mb-1 block text-xs font-medium">
                  Phone Number
                </label>
                <Input
                  id="acc-phone"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+8801700000000"
                />
              </div>

              <Button type="submit" disabled={updateProfileMutation.isPending}>
                {updateProfileMutation.isPending ? 'Saving…' : 'Save Changes'}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Address Book */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>Saved Shipping Addresses</CardTitle>
                <CardDescription>Manage delivery destinations for fast checkout.</CardDescription>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setShowAddressForm((v) => !v)}
              >
                <Plus size={14} />
                {showAddressForm ? 'Cancel' : 'Add New'}
              </Button>
            </div>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            {showAddressForm && (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  addAddressMutation.mutate();
                }}
                className="flex flex-col gap-3 rounded-lg border bg-muted p-4"
              >
                <div>
                  <label htmlFor="addr-name" className="mb-1 block text-xs font-medium">
                    Recipient Name
                  </label>
                  <Input
                    id="addr-name"
                    required
                    value={recipientName}
                    onChange={(e) => setRecipientName(e.target.value)}
                  />
                </div>
                <div>
                  <label htmlFor="addr-phone" className="mb-1 block text-xs font-medium">
                    Recipient Phone
                  </label>
                  <Input
                    id="addr-phone"
                    required
                    value={recipientPhone}
                    onChange={(e) => setRecipientPhone(e.target.value)}
                  />
                </div>
                <div className="flex gap-2">
                  <div className="flex-1">
                    <label htmlFor="addr-division" className="mb-1 block text-xs font-medium">
                      Division
                    </label>
                    <Input
                      id="addr-division"
                      required
                      value={division}
                      onChange={(e) => setDivision(e.target.value)}
                    />
                  </div>
                  <div className="flex-1">
                    <label htmlFor="addr-district" className="mb-1 block text-xs font-medium">
                      District
                    </label>
                    <Input
                      id="addr-district"
                      required
                      value={district}
                      onChange={(e) => setDistrict(e.target.value)}
                    />
                  </div>
                </div>
                <div className="flex gap-2">
                  <div className="flex-1">
                    <label htmlFor="addr-thana" className="mb-1 block text-xs font-medium">
                      Upazila / Thana
                    </label>
                    <Input
                      id="addr-thana"
                      required
                      value={upazilaThana}
                      onChange={(e) => setUpazilaThana(e.target.value)}
                    />
                  </div>
                  <div className="flex-1">
                    <label htmlFor="addr-type" className="mb-1 block text-xs font-medium">
                      Label
                    </label>
                    <Select
                      id="addr-type"
                      value={addressType}
                      onChange={(e) =>
                        setAddressType(e.target.value as 'home' | 'office' | 'other')
                      }
                    >
                      <option value="home">Home</option>
                      <option value="office">Office</option>
                      <option value="other">Other</option>
                    </Select>
                  </div>
                </div>
                <div>
                  <label htmlFor="addr-street" className="mb-1 block text-xs font-medium">
                    Street Address
                  </label>
                  <Input
                    id="addr-street"
                    required
                    value={streetAddress}
                    onChange={(e) => setStreetAddress(e.target.value)}
                    placeholder="House 14, Road 55, Block B"
                  />
                </div>
                <div>
                  <label htmlFor="addr-postal" className="mb-1 block text-xs font-medium">
                    Postal Code
                  </label>
                  <Input
                    id="addr-postal"
                    value={postalCode}
                    onChange={(e) => setPostalCode(e.target.value)}
                  />
                </div>
                <Button type="submit" size="sm" disabled={addAddressMutation.isPending}>
                  {addAddressMutation.isPending ? 'Saving…' : 'Save Address'}
                </Button>
              </form>
            )}

            {isAddressesLoading ? (
              <Skeleton style={{ width: '100%', height: '6rem' }} />
            ) : addresses.length === 0 ? (
              <p className="text-sm text-muted">No saved shipping addresses yet.</p>
            ) : (
              <div className="flex flex-col gap-3">
                {addresses.map((addr) => (
                  <div
                    key={addr.id}
                    className="flex items-start justify-between gap-3 rounded-lg border p-3"
                  >
                    <div className="text-sm">
                      <div className="flex items-center gap-2">
                        <MapPin size={14} />
                        <strong>{addr.name}</strong>
                        <Badge variant="outline">{addr.type}</Badge>
                        {addr.is_default && <Badge variant="success">Default</Badge>}
                      </div>
                      <p className="mt-1 text-xs text-muted">
                        {addr.formatted_address ||
                          `${addr.street_address}, ${addr.upazila_thana}, ${addr.district}`}
                      </p>
                      <p className="text-xs text-muted">{addr.phone}</p>
                    </div>

                    <div className="flex items-center gap-2">
                      {!addr.is_default && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => setDefaultMutation.mutate(addr.id)}
                          aria-label={`Set address for ${addr.name} as default`}
                        >
                          <CheckCircle2 size={14} />
                          Default
                        </Button>
                      )}
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => deleteAddressMutation.mutate(addr.id)}
                        aria-label={`Delete address for ${addr.name}`}
                      >
                        <Trash2 size={14} />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

