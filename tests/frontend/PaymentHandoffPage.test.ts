import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PaymentHandoff from '../../resources/js/pages/x-change/claim/PaymentHandoff.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    CheckCircle2: { template: '<span data-testid="check-circle-icon" />' },
    CreditCard: { template: '<span data-testid="credit-card-icon" />' },
}));

vi.mock('@/components/x-change/ClaimStepShell.vue', () => ({
    default: {
        props: ['tone', 'width'],
        template:
            '<main data-testid="claim-step-shell" :data-tone="tone" :data-width="width"><slot /></main>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        props: ['asChild'],
        template: '<div data-testid="button"><slot /></div>',
    },
}));

describe('PaymentHandoff page', () => {
    it('points an unpaid collectible Pay Code to the payment page', () => {
        const wrapper = mount(PaymentHandoff, {
            props: {
                code: 'PAY1',
                payment_url: '/x/pay/PAY1',
                is_fully_collected: false,
            },
        });

        expect(
            wrapper
                .get('[data-testid="claim-step-shell"]')
                .attributes('data-tone'),
        ).toBe('neutral');
        expect(wrapper.get('[data-testid="credit-card-icon"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.get('[data-testid="payment-handoff-title"]').text(),
        ).toBe('This Pay Code is for payment');
        expect(
            wrapper.get('[data-testid="payment-handoff-page"]').text(),
        ).toContain('PAY1');
        expect(
            wrapper
                .get('[data-testid="payment-handoff-open-payment"] a')
                .attributes('href'),
        ).toBe('/x/pay/PAY1');
    });

    it('hides the payment action for a fully paid collectible Pay Code', () => {
        const wrapper = mount(PaymentHandoff, {
            props: {
                code: 'PAID1',
                payment_url: null,
                is_fully_collected: true,
                receipt_summary: {
                    amount_paid_minor: 10000,
                    currency: 'PHP',
                    completed_at: '2026-08-26T08:30:00+08:00',
                },
            },
        });

        expect(
            wrapper
                .get('[data-testid="claim-step-shell"]')
                .attributes('data-tone'),
        ).toBe('success');
        expect(wrapper.get('[data-testid="check-circle-icon"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.get('[data-testid="payment-handoff-title"]').text(),
        ).toBe('This Pay Code has already been fully paid');
        expect(
            wrapper
                .find('[data-testid="payment-handoff-open-payment"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="payment-handoff-receipt-summary"]')
                .text(),
        ).toContain('₱100.00');
        expect(
            wrapper
                .get('[data-testid="payment-handoff-receipt-completed"]')
                .text(),
        ).toContain('Completed');
    });

    it('does not invent receipt details when no summary is available', () => {
        const wrapper = mount(PaymentHandoff, {
            props: {
                code: 'PAID2',
                payment_url: null,
                is_fully_collected: true,
                receipt_summary: null,
            },
        });

        expect(
            wrapper
                .find('[data-testid="payment-handoff-receipt-summary"]')
                .exists(),
        ).toBe(false);
    });
});
