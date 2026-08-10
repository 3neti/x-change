const brandLibraryBase = '/vendor/x-change/images/brand-library';

/**
 * Base public path for the payout-destination icon library (rails,
 * providers, x-change, banks, and EMIs). See
 * resources/documents/payout-destination-icons.json for the metadata that
 * resolves specific filenames under this base path.
 */
export const payoutDestinationsBase = '/vendor/x-change/images/payout-destinations';

export const xChangeBrandAssets = {
    logo: `${brandLibraryBase}/x-change/svg/x-change-logo.svg`,
    mark: `${brandLibraryBase}/x-change/svg/x-change-mark.svg`,
    lockup: `${brandLibraryBase}/x-change/svg/x-change-lockup.svg`,
    light: `${brandLibraryBase}/x-change/svg/x-change-light.svg`,
    dark: `${brandLibraryBase}/x-change/svg/x-change-dark.svg`,
    monochrome: `${brandLibraryBase}/x-change/svg/x-change-monochrome.svg`,
    favicon: `${brandLibraryBase}/x-change/svg/x-change-favicon.svg`,
    rasterLogo: `${brandLibraryBase}/x-change/png/x-change-logo.png`,
} as const;

export const gClefPulleyBrandAssets = {
    logo: `${brandLibraryBase}/g-clef-pulley/svg/g-clef-pulley-logo.svg`,
    mark: `${brandLibraryBase}/g-clef-pulley/svg/g-clef-pulley-mark.svg`,
    lockup: `${brandLibraryBase}/g-clef-pulley/svg/g-clef-pulley-lockup.svg`,
    light: `${brandLibraryBase}/g-clef-pulley/svg/g-clef-pulley-light.svg`,
    dark: `${brandLibraryBase}/g-clef-pulley/svg/g-clef-pulley-dark.svg`,
    monochrome: `${brandLibraryBase}/g-clef-pulley/svg/g-clef-pulley-monochrome.svg`,
    favicon: '/vendor/x-change/favicon.svg',
} as const;
