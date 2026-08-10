import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayoutRouteDisplay from '../../resources/js/components/x-change/PayoutRouteDisplay.vue';

describe('PayoutRouteDisplay', () => {
    it('shows the GCash icon alongside its text label', () => {
        const wrapper = mount(PayoutRouteDisplay, {
            props: {
                bankCode: 'GXCHPHM2XXX',
                accountNumber: '09173011987',
                settlementRail: 'INSTAPAY',
            },
        });

        const text = wrapper.text();
        expect(text).toContain('GCash');
        expect(text).toContain('InstaPay');
        expect(text).toContain('x-change');
        expect(text).toContain('NetBank');

        const images = wrapper.findAll('img');
        const gcashIcon = images.find((img) =>
            img.attributes('src')?.includes('gcash'),
        );
        expect(gcashIcon).toBeDefined();
    });

    it('keeps Maya Wallet and Maya Bank textually distinct even though they may share an icon', () => {
        const wallet = mount(PayoutRouteDisplay, {
            props: { bankCode: 'PAPHPHM1XXX', accountNumber: '09173011987' },
        });
        const bank = mount(PayoutRouteDisplay, {
            props: { bankCode: 'MYDBPHM2XXX', accountNumber: '09173011987' },
        });

        expect(wallet.text()).toContain('Maya Wallet');
        expect(wallet.text()).not.toContain('Maya Bank');

        expect(bank.text()).toContain('Maya Bank');
        expect(bank.text()).not.toContain('Maya Wallet');
    });

    it('keeps InstaPay and PESONet textually distinct even though they share an operator icon', () => {
        const instapay = mount(PayoutRouteDisplay, {
            props: { bankCode: 'GXCHPHM2XXX', settlementRail: 'INSTAPAY' },
        });
        const pesonet = mount(PayoutRouteDisplay, {
            props: { bankCode: 'GXCHPHM2XXX', settlementRail: 'PESONET' },
        });

        expect(instapay.text()).toContain('InstaPay');
        expect(instapay.text()).not.toContain('PESONet');

        expect(pesonet.text()).toContain('PESONet');
        expect(pesonet.text()).not.toContain('InstaPay');
    });

    it('renders cleanly with the generic fallback glyph when a destination has no icon', () => {
        const wrapper = mount(PayoutRouteDisplay, {
            props: {
                // A code intentionally absent from the icon library (see
                // docs/claim-ux/payout-destination-icon-library-report.md).
                bankCode: 'AIIPPHM1XXX',
                accountNumber: '09173011987',
            },
        });

        // The text label is still shown even without an icon asset.
        expect(wrapper.text()).toContain('AIIPPHM1XXX');

        // No broken <img> is rendered for the destination pill; the
        // component falls back to the lucide glyph instead.
        const destinationImages = wrapper
            .findAll('img')
            .filter((img) => img.attributes('src')?.includes('aiip'));
        expect(destinationImages).toHaveLength(0);
    });

    it('never references remote image URLs', () => {
        const wrapper = mount(PayoutRouteDisplay, {
            props: {
                bankCode: 'GXCHPHM2XXX',
                accountNumber: '09173011987',
            },
        });

        for (const img of wrapper.findAll('img')) {
            const src = img.attributes('src') ?? '';
            expect(src.startsWith('http://')).toBe(false);
            expect(src.startsWith('https://')).toBe(false);
            if (src !== '') {
                expect(src.startsWith('/vendor/x-change/images/payout-destinations/')).toBe(true);
            }
        }
    });
});
