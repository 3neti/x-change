import { describe, expect, it } from 'vitest';
import { resolveClaimWidgetPreviewViewModel } from '../../resources/js/components/x-change/claimWidgetPreviewViewModel';

describe('claim widget preview view model', () => {
    it('marks redeemed voucher as non-active and exposes redeemed date', () => {
        expect(resolveClaimWidgetPreviewViewModel({
            voucherData: {
                status: 'redeemed',
                redeemed_at: '2026-06-07T10:00:00Z',
            },
            preClaimVisualStages: [],
        })).toEqual({
            isNonActive: true,
            statusDate: '2026-06-07T10:00:00Z',
            hasPreClaimContent: false,
        });
    });

    it('marks expired voucher as non-active and exposes expired date', () => {
        expect(resolveClaimWidgetPreviewViewModel({
            voucherData: {
                status: 'expired',
                expired_at: '2026-06-08T10:00:00Z',
            },
            preClaimVisualStages: [],
        })).toEqual({
            isNonActive: true,
            statusDate: '2026-06-08T10:00:00Z',
            hasPreClaimContent: false,
        });
    });

    it('marks active voucher as active without status date', () => {
        expect(resolveClaimWidgetPreviewViewModel({
            voucherData: {
                status: 'active',
            },
            preClaimVisualStages: [],
        })).toEqual({
            isNonActive: false,
            statusDate: null,
            hasPreClaimContent: false,
        });
    });

    it('detects pre-claim content from visual stages', () => {
        expect(resolveClaimWidgetPreviewViewModel({
            voucherData: {
                status: 'active',
            },
            preClaimVisualStages: [
                {
                    key: 'intro-message',
                    type: 'message',
                    phase: 'pre_claim',
                },
            ],
        })).toMatchObject({
            isNonActive: false,
            statusDate: null,
            hasPreClaimContent: true,
        });
    });

    it('handles missing voucher data', () => {
        expect(resolveClaimWidgetPreviewViewModel({
            voucherData: null,
            preClaimVisualStages: [],
        })).toEqual({
            isNonActive: false,
            statusDate: null,
            hasPreClaimContent: false,
        });
    });
});
