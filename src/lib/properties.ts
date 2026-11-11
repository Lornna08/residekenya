import rawProperties from '@/data/properties.json';

export type PropertyRecord = {
  ref_code: string;
  title: string;
  purpose: string;
  status: string;
  currency: string;
  price: string;
  bedrooms: string;
  bathrooms: string;
  area_sqm: string;
  address: string;
  is_featured: string;
  description: string;
  slug: string;
};

export const properties = rawProperties as PropertyRecord[];

export function formatPrice(property: PropertyRecord) {
  const value = Number(property.price || 0);
  if (!value) return 'Price on request';
  return `${property.currency || 'KES'} ${new Intl.NumberFormat('en-KE', { maximumFractionDigits: 0 }).format(value)}`;
}

export function findProperty(slug: string) {
  return properties.find((property) => property.slug === slug);
}
