import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Approval from '../../resources/js/pages/x-change/claim/Approval.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    usePage: () => ({
        props: { errors: {} },
    }),
    router: {
        post: vi.fn(),
    },
}));

vi.mock('@/components/x-change/approvalOtpSubmitAdapter', () => ({
    submitApprovalOtp: vi.fn(),
}));

describe('claim Approval destination route rendering', () => {
    it('renders the payout route with an icon and label when a destination snapshot is present', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: { code: 'TEST123' },
                compiled_claim_result: null,
                message: null,
                destination: {
                    bank_code: 'MYDBPHM2XXX',
                    bank_name: 'Maya Bank',
                    bank_label: 'Maya Bank',
                    icon_asset: '/vendor/x-change/images/payout-destinations/maya-128.png',
                    settlement_rail: 'INSTAPAY',
                    account_number_masked: '*******1987',
                    route: ['x-change', 'NetBank', 'InstaPay', 'Maya Bank', '*******1987'],
                    route_icons: [null, null, null, '/vendor/x-change/images/payout-destinations/maya-128.png'],
                },
            },
        });

        const text = wrapper.text();
        expect(text).toContain('Maya Bank');
        expect(text).not.toContain('Maya Wallet');

        const mayaIcon = wrapper
            .findAll('img')
            .find((img) => img.attributes('src')?.includes('maya'));
        expect(mayaIcon).toBeDefined();
    });

    it('renders cleanly without a payout route when no destination snapshot is available', () => {
        const wrapper = mount(Approval, {
            props: {
                voucher: { code: 'TEST123' },
                compiled_claim_result: null,
                message: null,
                destination: null,
            },
        });

        expect(
            wrapper
                .findAll('img')
                .filter((img) => img.attributes('src')?.includes('payout-destinations')),
        ).toHaveLength(0);
    });
});
