import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Success from '../../resources/js/pages/x-change/claim/Success.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<slot />',
    },
    router: {
        visit: vi.fn(),
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
});
