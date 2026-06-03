import { describe, expect, it } from 'vitest';
import {
    formatSuccessVoucherAmount,
    hasNonZeroVoucherAmount,
    isPendingClaimOutcome,
    numericVoucherAmount,
    resolveSuccessFallbackTitle,
} from '../../resources/js/components/x-change/successFallback';

describe('success fallback', () => {
    it('normalizes voucher amount to a number', () => {
        expect(numericVoucherAmount({ amount: '100' })).toBe(100);
        expect(numericVoucherAmount({ amount: null })).toBe(0);
    });

    it('detects non-zero amount', () => {
        expect(hasNonZeroVoucherAmount({ amount: 1 })).toBe(true);
        expect(hasNonZeroVoucherAmount({ amount: 0 })).toBe(false);
    });

    it('uses formatted_amount when present', () => {
        expect(formatSuccessVoucherAmount({
            amount: 100,
            formatted_amount: 'PHP 100.00',
            formattedAmount: 'SHOULD NOT USE',
            currency: 'PHP',
        })).toBe('PHP 100.00');
    });

    it('uses formattedAmount when formatted_amount is absent', () => {
        expect(formatSuccessVoucherAmount({
            amount: 100,
            formattedAmount: 'PHP 100.00',
            currency: 'PHP',
        })).toBe('PHP 100.00');
    });

    it('formats non-zero amount when no formatted value exists', () => {
        expect(formatSuccessVoucherAmount({
            amount: 1000,
            currency: 'PHP',
        })).toBe('PHP 1,000');
    });

    it('returns empty amount when amount is zero', () => {
        expect(formatSuccessVoucherAmount({
            amount: 0,
            currency: 'PHP',
        })).toBe('');
    });

    it('detects pending claim outcome', () => {
        expect(isPendingClaimOutcome({ claimOutcome: 'accepted_pending' })).toBe(true);
        expect(isPendingClaimOutcome({ riderState: 'accepted_pending' })).toBe(true);
        expect(isPendingClaimOutcome({ claimOutcome: 'completed' })).toBe(false);
    });

    it('resolves pending fallback title', () => {
        expect(resolveSuccessFallbackTitle(
            { amount: 100 },
            { claimOutcome: 'accepted_pending' },
        )).toBe('Your claim is being processed');
    });

    it('resolves disbursed fallback title for non-zero amount', () => {
        expect(resolveSuccessFallbackTitle(
            { amount: 100 },
            {},
        )).toBe('Disbursed to your account');
    });

    it('resolves generic claimed fallback title for zero amount', () => {
        expect(resolveSuccessFallbackTitle(
            { amount: 0 },
            {},
        )).toBe('Pay Code claimed');
    });
});
