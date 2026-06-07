import { describe, expect, it } from 'vitest';
import { resolveClaimWidgetPreviewMode } from '../../resources/js/components/x-change/claimWidgetPreviewMode';

describe('claim widget preview mode', () => {
    it('resolves loading first', () => {
        expect(resolveClaimWidgetPreviewMode({
            loading: true,
            error: 'Error',
            voucherData: { preview: { enabled: false } },
            isNonActive: true,
        })).toBe('loading');
    });

    it('resolves error after loading', () => {
        expect(resolveClaimWidgetPreviewMode({
            loading: false,
            error: 'Invalid Pay Code.',
            voucherData: { preview: { enabled: false } },
            isNonActive: true,
        })).toBe('error');
    });

    it('resolves empty when voucher data is missing', () => {
        expect(resolveClaimWidgetPreviewMode({
            loading: false,
            error: null,
            voucherData: null,
            isNonActive: false,
        })).toBe('empty');
    });

    it('resolves preview disabled before active/non-active voucher rendering', () => {
        expect(resolveClaimWidgetPreviewMode({
            loading: false,
            error: null,
            voucherData: {
                preview: {
                    enabled: false,
                },
                status: 'redeemed',
            },
            isNonActive: true,
        })).toBe('preview-disabled');
    });

    it('resolves non-active voucher mode', () => {
        expect(resolveClaimWidgetPreviewMode({
            loading: false,
            error: null,
            voucherData: {
                status: 'redeemed',
            },
            isNonActive: true,
        })).toBe('non-active');
    });

    it('resolves active voucher mode', () => {
        expect(resolveClaimWidgetPreviewMode({
            loading: false,
            error: null,
            voucherData: {
                status: 'active',
            },
            isNonActive: false,
        })).toBe('active');
    });
});
