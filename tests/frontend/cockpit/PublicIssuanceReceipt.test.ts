import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PublicIssuanceReceipt from '../../../resources/js/pages/x-change/public/IssuanceReceipt.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div><slot /></div>' },
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
}));

describe('Public issuance receipt', () => {
    it('shows only the safe order, Pay Code, and activity projection', () => {
        const wrapper = mount(PublicIssuanceReceipt, {
            props: {
                receipt: {
                    schema: 'x-change.public-issuance-receipt.v1',
                    order_reference: 'ORDER-1',
                    status: 'issued',
                    amount_minor: 4300,
                    currency: 'PHP',
                    created_at: '2026-10-02T00:00:00Z',
                    verified_at: '2026-10-02T00:01:00Z',
                    issued_at: '2026-10-02T00:02:00Z',
                    pay_code: {
                        code: 'SAFE-1',
                        claim_url: 'https://example.test/x/claim/SAFE-1',
                    },
                    activity: [
                        {
                            sequence: 1,
                            status: 'awaiting_payment',
                            occurred_at: '2026-10-02T00:00:00Z',
                        },
                        {
                            sequence: 2,
                            status: 'issued',
                            occurred_at: '2026-10-02T00:02:00Z',
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Issuance receipt');
        expect(wrapper.text()).toContain('ORDER-1');
        expect(wrapper.text()).toContain('SAFE-1');
        expect(wrapper.text()).toContain('₱43.00');
        expect(wrapper.text()).toContain('excludes payer identity');
    });
});
