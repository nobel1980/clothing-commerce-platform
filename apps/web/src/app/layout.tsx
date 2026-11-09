import type { Metadata } from 'next';
import './globals.css';
import { StoreProviders } from '@/components/layout/StoreProviders';
import { getAppBaseUrl } from '@/lib/utils/format';

export const metadata: Metadata = {
  metadataBase: new URL(getAppBaseUrl()),
  title: {
    default: 'Atelier Véra | Contemporary Ready-to-Wear & Essentials',
    template: '%s | Atelier Véra',
  },
  description:
    'Contemporary ready-to-wear essentials crafted from heavyweight organic cotton, structured wool, and breathable linen.',
  alternates: {
    canonical: getAppBaseUrl(),
  },
  openGraph: {
    type: 'website',
    siteName: 'Atelier Véra',
    title: 'Atelier Véra | Contemporary Ready-to-Wear & Essentials',
    description:
      'Discover modern silhouettes, tailored staples, and heavyweight essentials.',
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>
        <StoreProviders>{children}</StoreProviders>
      </body>
    </html>
  );
}
