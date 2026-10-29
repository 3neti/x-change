export function isVerifiedClaimCode(result: unknown, code: string): boolean {
    if (!result || typeof result !== 'object' || Array.isArray(result)) {
        return false;
    }

    const xray = result as Record<string, unknown>;
    if (xray.visible !== true || !['claimable', 'partially_claimable'].includes(String(xray.status))) {
        return false;
    }

    if (!Array.isArray(xray.disclosures)) {
        return false;
    }

    const reference = xray.disclosures.find((item) => item?.key === 'reference')?.value;

    return code.trim() !== ''
        && typeof reference === 'string'
        && reference.trim() !== ''
        && reference.trim().toUpperCase() === code.trim().toUpperCase();
}
