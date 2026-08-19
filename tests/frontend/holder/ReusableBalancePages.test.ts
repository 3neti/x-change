import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ReusableBalanceCards from '../../../resources/js/components/x-change/ReusableBalanceCards.vue';
import StoredValueShow from '../../../resources/js/pages/x-change/balances/StoredValueShow.vue';

vi.mock('@/layouts/x-change/XChangeLayout.vue', () => ({
    default: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

const reference = '01M08A1B2C3D4E5F6G7H8J9KLM';
const summary = {
    schema: 'x-change.holder-stored-value-summary.v1',
    reference,
    status: 'active' as const,
    currency: 'PHP',
    available_minor: 84_200,
    total_loaded_minor: 100_000,
    total_spent_minor: 15_800,
    maximum_minor: 100_000,
    replenishable: false,
    activated_at: '2026-08-19T01:00:00+00:00',
    expires_at: '2027-08-19T01:00:00+00:00',
};

const global = {
    stubs: {
        Head: true,
        Link: {
            props: ['href'],
            template: '<a :href="href"><slot /></a>',
        },
    },
};

describe('holder reusable balances', () => {
    it('shows a compact private balance card with its holder-facing route', () => {
        const wrapper = mount(ReusableBalanceCards, {
            props: { balances: [summary] },
            global,
        });

        expect(wrapper.get('[data-testid="reusable-balances"]').text()).toContain('₱842.00');
        expect(wrapper.get(`[data-testid="reusable-balance-${reference}"]`).attributes('href'))
            .toBe(`/x/balances/reusable/${reference}`);
        expect(wrapper.text()).not.toContain('allocation');
        expect(wrapper.text()).not.toContain('idempotency');
    });

    it('renders signed transaction movement and paginated holder history', () => {
        const wrapper = mount(StoredValueShow, {
            props: {
                instrument: {
                    ...summary,
                    schema: 'x-change.holder-stored-value-detail.v1',
                    activity_available: true,
                    transactions: [
                        {
                            type: 'draw',
                            label: 'Purchase',
                            amount_minor: -2_500,
                            balance_after_minor: 84_200,
                            currency: 'PHP',
                            occurred_at: '2026-08-19T02:00:00+00:00',
                        },
                    ],
                    pagination: {
                        current_page: 1,
                        per_page: 25,
                        total: 26,
                        last_page: 2,
                    },
                },
            },
            global,
        });

        expect(wrapper.get('[data-testid="stored-value-detail"]').text()).toContain('₱842.00');
        expect(wrapper.text()).toContain('Purchase');
        expect(wrapper.text()).toContain('-₱25.00');
        expect(wrapper.get('nav a').attributes('href'))
            .toBe(`/x/balances/reusable/${reference}?page=2`);
        expect(wrapper.text()).not.toContain('position');
        expect(wrapper.text()).not.toContain('operation');
    });

    it('states that a missing Treasury projection is unavailable rather than zero', () => {
        const wrapper = mount(ReusableBalanceCards, {
            props: {
                balances: [{
                    ...summary,
                    status: 'unavailable',
                    available_minor: null,
                }],
            },
            global,
        });

        expect(wrapper.text()).toContain('Balance unavailable');
        expect(wrapper.text()).not.toContain('₱0.00');
    });
});
