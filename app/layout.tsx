import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Reside Ke | Find a place to call home',
  description: 'Discover homes, apartments, land and commercial property across Kenya.',
  metadataBase: new URL('https://residekenya.com'),
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body>{children}</body></html>;
}
