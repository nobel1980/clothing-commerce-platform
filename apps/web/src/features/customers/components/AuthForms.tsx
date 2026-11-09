'use client';

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useMutation } from '@/lib/query';
import { loginCustomer, registerCustomer } from '@/features/customers/api/customerApi';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert } from '@/components/ui/alert';

export function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = React.useState('');
  const [password, setPassword] = React.useState('');

  const loginMutation = useMutation({
    mutationFn: () => loginCustomer({ email, password }),
    onSuccess: () => {
      router.push('/account');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    loginMutation.mutate();
  };

  return (
    <Card style={{ maxWidth: '28rem', margin: '0 auto' }}>
      <CardHeader>
        <CardTitle>Sign In to Atelier Véra</CardTitle>
        <CardDescription>
          Access your saved shipping addresses, order history, and account preferences.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {loginMutation.error && (
            <Alert variant="destructive">{loginMutation.error.message}</Alert>
          )}

          <div>
            <label htmlFor="login-email" className="mb-1 block text-xs font-medium">
              Email Address
            </label>
            <Input
              id="login-email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
            />
          </div>

          <div>
            <label htmlFor="login-password" className="mb-1 block text-xs font-medium">
              Password
            </label>
            <Input
              id="login-password"
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
            />
          </div>

          <Button type="submit" disabled={loginMutation.isPending} className="w-full">
            {loginMutation.isPending ? 'Signing In…' : 'Sign In'}
          </Button>

          <p className="text-center text-xs text-muted">
            New to Atelier Véra?{' '}
            <Link href="/register" className="font-semibold" style={{ textDecoration: 'underline' }}>
              Create an account
            </Link>
          </p>
        </form>
      </CardContent>
    </Card>
  );
}

export function RegisterForm() {
  const router = useRouter();
  const [name, setName] = React.useState('');
  const [email, setEmail] = React.useState('');
  const [phone, setPhone] = React.useState('');
  const [password, setPassword] = React.useState('');
  const [passwordConfirmation, setPasswordConfirmation] = React.useState('');

  const registerMutation = useMutation({
    mutationFn: () =>
      registerCustomer({
        name,
        email,
        phone: phone || undefined,
        password,
        password_confirmation: passwordConfirmation,
      }),
    onSuccess: () => {
      router.push('/account');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    registerMutation.mutate();
  };

  return (
    <Card style={{ maxWidth: '30rem', margin: '0 auto' }}>
      <CardHeader>
        <CardTitle>Create Your Account</CardTitle>
        <CardDescription>
          Join Atelier Véra for seamless checkout, address management, and order tracking.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          {registerMutation.error && (
            <Alert variant="destructive">{registerMutation.error.message}</Alert>
          )}

          <div>
            <label htmlFor="reg-name" className="mb-1 block text-xs font-medium">
              Full Name
            </label>
            <Input
              id="reg-name"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Ariana Rahman"
            />
          </div>

          <div>
            <label htmlFor="reg-email" className="mb-1 block text-xs font-medium">
              Email Address
            </label>
            <Input
              id="reg-email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="ariana@example.com"
            />
          </div>

          <div>
            <label htmlFor="reg-phone" className="mb-1 block text-xs font-medium">
              Phone Number
            </label>
            <Input
              id="reg-phone"
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="+8801700000000"
            />
          </div>

          <div>
            <label htmlFor="reg-password" className="mb-1 block text-xs font-medium">
              Password
            </label>
            <Input
              id="reg-password"
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>

          <div>
            <label htmlFor="reg-password-confirm" className="mb-1 block text-xs font-medium">
              Confirm Password
            </label>
            <Input
              id="reg-password-confirm"
              type="password"
              required
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
            />
          </div>

          <Button type="submit" disabled={registerMutation.isPending} className="w-full">
            {registerMutation.isPending ? 'Creating Account…' : 'Create Account'}
          </Button>

          <p className="text-center text-xs text-muted">
            Already have an account?{' '}
            <Link href="/login" className="font-semibold" style={{ textDecoration: 'underline' }}>
              Sign In
            </Link>
          </p>
        </form>
      </CardContent>
    </Card>
  );
}

