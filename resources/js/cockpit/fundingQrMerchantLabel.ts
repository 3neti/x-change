export type FundingQrMerchantLabelInput = {
    name: string;
    city: string;
    applicationName: string;
    template: string;
    maximumLength: number;
    uppercase: boolean;
};

export type FundingQrMerchantLabelPreview = {
    candidate: string;
    label: string;
    characterCount: number;
    maximumLength: number;
    overflow: number;
    fits: boolean;
    shortened: boolean;
};

function normalizeLabel(value: string): string {
    return value
        .replace(/\s+/gu, ' ')
        .replace(/^\s*[•|,\-]+\s*|\s*[•|,\-]+\s*$/gu, '')
        .trim();
}

function characters(value: string): string[] {
    return Array.from(value);
}

export function renderFundingQrMerchantLabel(
    input: FundingQrMerchantLabelInput,
): FundingQrMerchantLabelPreview {
    const maximumLength = Math.max(1, input.maximumLength);
    const name = input.name.trim();
    const rendered = normalizeLabel(
        input.template
            .replaceAll('{name}', name)
            .replaceAll('{city}', input.city.trim())
            .replaceAll('{app_name}', input.applicationName.trim()),
    );
    const fallback = name || 'Account Holder';
    const candidate = rendered || fallback;
    const characterCount = characters(candidate).length;
    const fits = characterCount <= maximumLength;
    const providerSafeLabel = fits ? candidate : name || candidate;
    const label = characters(providerSafeLabel)
        .slice(0, maximumLength)
        .join('');
    const transform = (value: string): string =>
        input.uppercase ? value.toLocaleUpperCase('en-PH') : value;

    return {
        candidate: transform(candidate),
        label: transform(label),
        characterCount,
        maximumLength,
        overflow: Math.max(0, characterCount - maximumLength),
        fits,
        shortened: label !== candidate,
    };
}
