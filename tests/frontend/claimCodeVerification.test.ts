import { describe, expect, it } from 'vitest';
import { isVerifiedClaimCode } from '../../resources/js/components/x-change/claimCodeVerification';

describe('claim code verification', () => {
    const disclosedCode = {
        visible: true,
        status: 'claimable',
        disclosures: [{ key: 'reference', value: 'ABCD' }],
    };

    it('brands the exact server-disclosed code regardless of letter case', () => {
        expect(isVerifiedClaimCode(disclosedCode, 'abcd')).toBe(true);
        expect(isVerifiedClaimCode({ ...disclosedCode, status: 'partially_claimable' }, 'ABCD')).toBe(true);
    });

    it('does not brand a changed, undisclosed, or unclaimable code', () => {
        expect(isVerifiedClaimCode(disclosedCode, 'WXYZ')).toBe(false);
        expect(isVerifiedClaimCode({ ...disclosedCode, disclosures: [] }, 'ABCD')).toBe(false);
        expect(isVerifiedClaimCode({ ...disclosedCode, status: 'not_found' }, 'ABCD')).toBe(false);
    });
});
