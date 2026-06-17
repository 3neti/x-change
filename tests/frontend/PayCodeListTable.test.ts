import { mount } from '@vue/test-utils';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';

const { visit } = vi.hoisted(() => ({
    visit: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit,
    },
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: {
        props: ['variant'],
        template: '<span><slot /></span>',
    },
}), { virtual: true });

vi.mock('../../resources/js/components/x-change/pay-codes/PayCodeStatusBadge.vue', () => ({
    default: {
        props: ['status'],
        template: '<span data-testid="pay-code-status-badge">{{ status }}</span>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        props: ['size', 'variant'],
        template: '<button type="button"><slot /></button>',
    },
}), { virtual: true });

vi.mock('@/components/ui/card', () => ({
    Card: {
        template: '<div><slot /></div>',
    },
    CardContent: {
        template: '<div><slot /></div>',
    },
}), { virtual: true });

vi.mock('lucide-vue-next', () => ({
    Copy: {
        template: '<span />',
    },
    ExternalLink: {
        template: '<span />',
    },
    Eye: {
        template: '<span />',
    },
    KeyRound: {
        template: '<span />',
    },
}));

beforeEach(() => {
    visit.mockClear();
});

describe('PayCodeListTable', () => {
    let PayCodeListTable: Component;

    beforeAll(async () => {
        PayCodeListTable = (await import('../../resources/js/components/x-change/pay-codes/PayCodeListTable.vue')).default;
    });

    it('renders awaiting approval status and action when voucher requires OTP approval', async () => {
        const wrapper = mount(PayCodeListTable, {
            props: {
                vouchers: [
                    {
                        code: 'TEST123',
                        amount: 50,
                        currency: 'PHP',
                        status: 'redeemed',
                        display_status: 'awaiting_approval',
                        approval: {
                            required: true,
                            type: 'otp',
                            provider: 'paynamics',
                            reference_id: 'TEST123-09173011987',
                            message: 'Paynamics payout OTP is pending.',
                            action_url: '/x/pay-codes/TEST123/approval',
                        },
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="pay-code-status-badge"]').text()).toBe('awaiting_approval');
        expect(wrapper.text()).not.toContain('Needs OTP approval');
        expect(wrapper.text()).not.toContain('redeemed');
        expect(wrapper.find('[data-testid="pay-code-approval-helper"]').text()).toBe(
            'Issuer OTP approval required before payout can complete.',
        );
        expect(wrapper.find('[data-testid="pay-code-approval-action"]').text()).toContain('Approve');

        await wrapper.find('[data-testid="pay-code-approval-action"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/x/pay-codes/TEST123/approval');
    });

    it('uses approval URL resolver when approval action URL is missing', async () => {
        const wrapper = mount(PayCodeListTable, {
            props: {
                approvalUrl: (code: string) => `/operator/pay-codes/${code}/approval`,
                vouchers: [
                    {
                        code: 'TEST456',
                        amount: 75,
                        currency: 'PHP',
                        status: 'pending',
                        approval: {
                            required: true,
                            type: 'otp',
                            provider: 'paynamics',
                            reference_id: 'TEST456-09173011987',
                            message: 'Paynamics payout OTP is pending.',
                            action_url: null,
                        },
                    },
                ],
            },
        });

        await wrapper.find('[data-testid="pay-code-approval-action"]').trigger('click');

        expect(visit).toHaveBeenCalledWith('/operator/pay-codes/TEST456/approval');
    });

    it('hides approval badge and action when voucher does not require approval', () => {
        const wrapper = mount(PayCodeListTable, {
            props: {
                vouchers: [
                    {
                        code: 'TEST789',
                        amount: 25,
                        currency: 'PHP',
                        status: 'active',
                        approval: null,
                    },
                ],
            },
        });

        expect(wrapper.text()).not.toContain('Needs OTP approval');
        expect(wrapper.find('[data-testid="pay-code-approval-helper"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pay-code-approval-action"]').exists()).toBe(false);
    });
});
