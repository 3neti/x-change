import { describe, expect, it } from 'vitest';
import {
    formatSuccessVoucherAmount,
    hasNonZeroVoucherAmount,
    isPendingClaimOutcome,
    numericVoucherAmount,
    resolveSuccessFallbackTitle,
    resolveSuccessRiderMessage,
    resolveSuccessRiderStage,
    shouldRenderSuccessVoucherCodeBadge,
    shouldRenderSuccessRiderMessage,
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

    it('renders voucher code badge when there are no success stages or rider message', () => {
        expect(shouldRenderSuccessVoucherCodeBadge(false, false)).toBe(true);
    });

    it('does not render voucher code badge when success stages exist', () => {
        expect(shouldRenderSuccessVoucherCodeBadge(true, false)).toBe(false);
    });

    it('does not render voucher code badge when rider message exists', () => {
        expect(shouldRenderSuccessVoucherCodeBadge(false, true)).toBe(false);
    });

    it('renders rider message when enabled with content', () => {
        expect(shouldRenderSuccessRiderMessage({
            enabled: true,
            content: 'Claim successful.',
        })).toBe(true);
    });

    it('does not render rider message when disabled', () => {
        expect(shouldRenderSuccessRiderMessage({
            enabled: false,
            content: 'Claim successful.',
        })).toBe(false);
    });

    it('does not render rider message without content', () => {
        expect(shouldRenderSuccessRiderMessage({
            enabled: true,
            content: '',
        })).toBe(false);
    });

    it('does not render rider message when missing', () => {
        expect(shouldRenderSuccessRiderMessage(null)).toBe(false);
        expect(shouldRenderSuccessRiderMessage(undefined)).toBe(false);
    });

    it('replaces generic ready rider messages while provider payout is pending', () => {
        expect(resolveSuccessRiderMessage(
            {
                enabled: true,
                type: 'text',
                content: 'Your Pay Code is ready.',
            },
            { claimOutcome: 'accepted_pending' },
        )?.content).toBe('Your claim is being processed.');
    });

    it('replaces generic ready stage payloads while provider payout is pending', () => {
        const stage = resolveSuccessRiderStage(
            {
                type: 'message',
                phase: 'success',
                payload: {
                    content: 'Your Pay Code is ready.',
                },
            },
            { claimOutcome: 'accepted_pending' },
        );

        expect(stage.payload?.content).toBe('Your claim is being processed.');
    });

    it('preserves custom success rider stages while provider payout is pending', () => {
        const stage = resolveSuccessRiderStage(
            {
                type: 'message',
                phase: 'success',
                payload: {
                    content: 'Keep this custom issuer message.',
                },
            },
            { claimOutcome: 'accepted_pending' },
        );

        expect(stage.payload?.content).toBe('Keep this custom issuer message.');
    });
});
