import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Success from '../../resources/js/pages/x-change/claim/Success.vue';
import { router } from '@inertiajs/vue3';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<slot />',
    },
    router: {
        visit: vi.fn(),
        reload: vi.fn(),
    },
}));

vi.mock('@/components/ui/card', () => ({
    Card: {
        name: 'Card',
        template: '<div data-testid="card"><slot /></div>',
    },
    CardContent: {
        name: 'CardContent',
        template: '<div data-testid="card-content"><slot /></div>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        name: 'Button',
        template: '<button data-testid="button"><slot /></button>',
    },
}));

const baseProps = {
    voucher: {
        code: 'TEST123',
        amount: 2000,
        currency: 'PHP',
        formatted_amount: '₱2,000.00',
    },
    claimOutcome: 'accepted_success',
    rider: null,
    redirectEndpoint: null,
    claim_experience: null,
    redirect: null,
};

describe('claim Success destination route rendering', () => {
    it('checks prepaid processing then replaces it with the authorized ready action', async () => {
        vi.useFakeTimers();
        vi.mocked(router.reload).mockClear();
        const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
        const presentation = { suppress_legacy_rider: true, state: 'processing', title: 'Details submitted', body: 'Payment received.' };
        const wrapper = mount(Success, { props: {
            ...baseProps,
            claimWorkflowKey: 'campaign.coverage-completion.v1',
            success_presentation: presentation,
        } });
        try {
            expect(wrapper.text()).toContain('update automatically');
            await vi.advanceTimersByTimeAsync(5000);
            expect(router.reload).toHaveBeenCalledOnce();
            await wrapper.setProps({
                success_presentation: { ...presentation, state: 'ready', title: 'Policy result ready' },
                success_action: { intent: 'demo_policy_summary', label: 'View demo policy', enabled: true, target: { url: '/private-demo' } },
            });
            expect(wrapper.text()).toContain('Policy result ready');
            expect(wrapper.find('[data-testid="completion-status-check"]').exists()).toBe(false);
            expect(wrapper.get('[data-testid="claim-success-primary-action"]').attributes('href')).toBe('/private-demo');
            await vi.advanceTimersByTimeAsync(10000);
            expect(router.reload).toHaveBeenCalledOnce();
            await wrapper.setProps({ success_action: null });
            expect(wrapper.find('[data-testid="claim-success-primary-action"]').exists()).toBe(false);
        } finally {
            wrapper.unmount();
            visibility.mockRestore();
            vi.useRealTimers();
        }
    });

    it('offers a bounded manual check after the automatic window expires', async () => {
        vi.useFakeTimers();
        const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
        const wrapper = mount(Success, { props: {
            ...baseProps, claimWorkflowKey: 'campaign.coverage-completion.v1',
            success_presentation: { suppress_legacy_rider: true, state: 'processing', title: 'Details submitted' },
        } });
        try {
            await vi.advanceTimersByTimeAsync(120000);
            expect(wrapper.text()).toContain('Check status');
            expect(wrapper.text()).toContain('SMS');
        } finally {
            wrapper.unmount(); visibility.mockRestore(); vi.useRealTimers();
        }
    });
    it('renders the payout route with an icon and label when a destination snapshot is present', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                destination: {
                    bank_code: 'GXCHPHM2XXX',
                    bank_name: 'GCash',
                    bank_label: 'GCash',
                    icon_asset: '/vendor/x-change/images/payout-destinations/gcash-128.png',
                    settlement_rail: 'INSTAPAY',
                    account_number_masked: '*******1987',
                    route: ['x-change', 'NetBank', 'InstaPay', 'GCash', '*******1987'],
                    route_icons: [null, null, null, '/vendor/x-change/images/payout-destinations/gcash-128.png'],
                },
            },
        });

        const text = wrapper.text();
        expect(text).toContain('GCash');
        expect(text).toContain('*******1987');

        const gcashIcon = wrapper
            .findAll('img')
            .find((img) => img.attributes('src')?.includes('gcash'));
        expect(gcashIcon).toBeDefined();
    });

    it('renders cleanly without a payout route when no destination snapshot is available', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                destination: null,
            },
        });

        expect(wrapper.findAll('img').filter((img) => img.attributes('src')?.includes('payout-destinations'))).toHaveLength(0);
    });

    it('renders onboarding success as a centered invitation completion page', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    success: {
                        type: 'text',
                        body: 'x-PayOut Maker onboarding invitation',
                    },
                },
                destination: null,
                success_presentation: {
                    intent: 'commissioning_invitation',
                    eyebrow: 'Welcome',
                    title: 'Welcome to x-PayOut',
                    account_message: 'Your Maker account is ready.',
                    body: 'You can now prepare Pay Codes and submit payout work for checker approval.',
                    receipt_label: 'Invitation accepted',
                    receipt_code: 'MAKE-TEST',
                    funds: {
                        label: 'Client Funds',
                        text: '₱1,000.00 available for instructions',
                    },
                },
                success_action: {
                    key: 'x-change.onboarding-success.enter-workspace',
                    label: 'Go to my workspace',
                    enabled: true,
                    target: {
                        url: '/x/cockpit/quick-generate',
                        method: 'GET',
                        redirectable: true,
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Welcome');
        expect(wrapper.text()).toContain('Welcome to x-PayOut');
        expect(wrapper.text()).toContain('Your Maker account is ready.');
        expect(wrapper.text()).toContain('₱1,000.00 available for instructions');
        expect(wrapper.text()).toContain('Client Funds');
        expect(wrapper.text()).toContain('Go to my workspace');
        expect(wrapper.text()).toContain('Invitation accepted');
        expect(wrapper.text()).toContain('MAKE-TEST');
        expect(wrapper.text()).not.toContain('x-PayOut Maker onboarding invitation');
        expect(wrapper.get('[data-testid="claim-brand-header"]').classes()).toContain('justify-center');
        expect(wrapper.find('[data-testid="claim-theme-picker"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="claim-success-primary-action"]').attributes('href')).toBe('/x/cockpit/quick-generate');
    });

    it('renders a payment action even when success uses the generic claim presentation', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    success: {
                        type: 'text',
                        body: 'AUI insurance payment intake',
                    },
                },
                destination: null,
                success_presentation: null,
                success_action: {
                    key: 'x-change.claim-success.continue-to-payment',
                    label: 'Continue to payment',
                    enabled: true,
                    target: {
                        url: '/x/pay/AUI-J2ZG',
                        method: 'GET',
                        redirectable: true,
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Disbursed to your account');
        expect(wrapper.text()).toContain('Continue to payment');
        expect(wrapper.get('[data-testid="claim-success-primary-action"]').attributes('href')).toBe('/x/pay/AUI-J2ZG');
    });
});
