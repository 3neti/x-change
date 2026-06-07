import { describe, expect, it } from 'vitest';
import {
    isNonActiveVoucher,
    isReturningRedeemerFromStorage,
    resolveVoucherStatusDate,
} from '../../resources/js/components/x-change/claimWidgetVoucherState';

describe('claim widget voucher state helpers', () => {
    it('identifies redeemed and expired vouchers as non-active', () => {
        expect(isNonActiveVoucher({ status: 'redeemed' })).toBe(true);
        expect(isNonActiveVoucher({ status: 'expired' })).toBe(true);
    });

    it('does not treat active, missing, or unknown vouchers as non-active', () => {
        expect(isNonActiveVoucher({ status: 'active' })).toBe(false);
        expect(isNonActiveVoucher({ status: 'pending' })).toBe(false);
        expect(isNonActiveVoucher({})).toBe(false);
        expect(isNonActiveVoucher(null)).toBe(false);
        expect(isNonActiveVoucher(undefined)).toBe(false);
    });

    it('resolves redeemed status date', () => {
        expect(resolveVoucherStatusDate({
            status: 'redeemed',
            redeemed_at: '2026-06-07T10:00:00Z',
            expired_at: '2026-06-08T10:00:00Z',
        })).toBe('2026-06-07T10:00:00Z');
    });

    it('resolves expired status date', () => {
        expect(resolveVoucherStatusDate({
            status: 'expired',
            redeemed_at: '2026-06-07T10:00:00Z',
            expired_at: '2026-06-08T10:00:00Z',
        })).toBe('2026-06-08T10:00:00Z');
    });

    it('returns null when status date is not applicable or missing', () => {
        expect(resolveVoucherStatusDate({ status: 'active' })).toBeNull();
        expect(resolveVoucherStatusDate({ status: 'redeemed' })).toBeNull();
        expect(resolveVoucherStatusDate({ status: 'expired' })).toBeNull();
        expect(resolveVoucherStatusDate(null)).toBeNull();
        expect(resolveVoucherStatusDate(undefined)).toBeNull();
    });

    it('detects returning redeemer when persisted wallet info has mobile', () => {
        expect(isReturningRedeemerFromStorage({
            getItem: () => JSON.stringify({
                mobile: '+639173011987',
            }),
        })).toBe(true);
    });

    it('does not detect returning redeemer when persisted wallet info has no mobile', () => {
        expect(isReturningRedeemerFromStorage({
            getItem: () => JSON.stringify({
                email: 'lester@example.com',
            }),
        })).toBe(false);
    });

    it('does not detect returning redeemer when storage is empty or invalid', () => {
        expect(isReturningRedeemerFromStorage({
            getItem: () => null,
        })).toBe(false);

        expect(isReturningRedeemerFromStorage({
            getItem: () => 'not-json',
        })).toBe(false);

        expect(isReturningRedeemerFromStorage(null)).toBe(false);
        expect(isReturningRedeemerFromStorage(undefined)).toBe(false);
    });

    it('does not detect returning redeemer when storage access throws', () => {
        expect(isReturningRedeemerFromStorage({
            getItem: () => {
                throw new Error('Storage blocked');
            },
        })).toBe(false);
    });
});
