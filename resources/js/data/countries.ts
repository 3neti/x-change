export interface Country {
    code: string; // ISO 3166-1 alpha-2
    name: string;
}

/**
 * Common countries for financial transactions
 */
export const COUNTRIES: Country[] = [
    { code: 'PH', name: 'Philippines' },
    { code: 'US', name: 'United States' },
    { code: 'SG', name: 'Singapore' },
    { code: 'MY', name: 'Malaysia' },
    { code: 'TH', name: 'Thailand' },
    { code: 'ID', name: 'Indonesia' },
    { code: 'VN', name: 'Vietnam' },
    { code: 'HK', name: 'Hong Kong' },
    { code: 'AU', name: 'Australia' },
    { code: 'CA', name: 'Canada' },
    { code: 'GB', name: 'United Kingdom' },
    { code: 'JP', name: 'Japan' },
    { code: 'KR', name: 'South Korea' },
    { code: 'CN', name: 'China' },
    { code: 'AE', name: 'United Arab Emirates' },
    { code: 'SA', name: 'Saudi Arabia' },
];

/**
 * Default country
 */
export const DEFAULT_COUNTRY = 'PH';

/**
 * Get country by code
 */
export function getCountry(code: string | null | undefined): Country | undefined {
    if (!code) return undefined;
    return COUNTRIES.find(c => c.code === code);
}
