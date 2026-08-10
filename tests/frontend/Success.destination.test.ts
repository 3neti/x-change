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
});
