import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Clothing Commerce Platform',
  description: 'Next-generation modular clothing commerce experience',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en">
      <body>
        <main>{children}</main>
      </body>
    </html>
  );
}
