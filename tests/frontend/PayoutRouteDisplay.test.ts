import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayoutRouteDisplay from '../../resources/js/components/x-change/PayoutRouteDisplay.vue';

describe('PayoutRouteDisplay', () => {
    it('shows a compact redeemer route by default', () => {
        const wrapper = mount(PayoutRouteDisplay, {
            props: {
                amount: '₱50.00',
                bankCode: 'GXCHPHM2XXX',
                accountNumber: '09173011987',
                settlementRail: 'INSTAPAY',
            },
        });

        const text = wrapper.text();
        expect(text).toContain('₱50.00');
        expect(text).toContain('09173011987');
        expect(text).not.toContain('Send ₱50.00 to GCash account');
        expect(text).not.toContain('GCash');
        expect(text).not.toContain('InstaPay');
        expect(text).not.toContain('x-change');
        expect(text).not.toContain('NetBank');

        const images = wrapper.findAll('img');
        const gcashIcon = images.find((img) =>
            img.attributes('src')?.includes('gcash'),
        );
        expect(gcashIcon).toBeDefined();
    });

    it('can show the full operational route for issuer surfaces', () => {
        const wrapper = mount(PayoutRouteDisplay, {
            props: {
                mode: 'operational',
                amount: '₱50.00',
                bankCode: 'GXCHPHM2XXX',
                accountNumber: '09173011987',
                settlementRail: 'INSTAPAY',
            },
        });

        const text = wrapper.text();
        expect(text).toContain('x-change');
        expect(text).toContain('NetBank');
        expect(text).toContain('InstaPay');
        expect(text).toContain('GCash');
        expect(text).toContain('09173011987');
    });

    it('keeps the visible route on one line', () => {
        const wrapper = mount(PayoutRouteDisplay, {
            props: {
                amount: '₱2,000.00',
                bankCode: 'GXCHPHM2XXX',
                accountNumber: '09703812037',
                settlementRail: 'INSTAPAY',
            },
        });

        const route = wrapper.find('[data-testid="payout-route-segments"]');
        expect(route.classes()).toContain('whitespace-nowrap');
        expect(route.classes()).toContain('overflow-hidden');
    });

    it('keeps Maya Wallet and Maya Bank textually distinct even though they may share an icon', () => {
        const wallet = mount(PayoutRouteDisplay, {
            props: {
                mode: 'operational',
                bankCode: 'PAPHPHM1XXX',
                accountNumber: '09173011987',
            },
        });
        const bank = mount(PayoutRouteDisplay, {
            props: {
                mode: 'operational',
                bankCode: 'MYDBPHM2XXX',
                accountNumber: '09173011987',
            },
        });

        expect(wallet.text()).toContain('Maya Wallet');
        expect(wallet.text()).not.toContain('Maya Bank');

        expect(bank.text()).toContain('Maya Bank');
        expect(bank.text()).not.toContain('Maya Wallet');
    });

    it('keeps InstaPay and PESONet textually distinct even though they share an operator icon', () => {
        const instapay = mount(PayoutRouteDisplay, {
            props: {
                mode: 'operational',
                bankCode: 'GXCHPHM2XXX',
                settlementRail: 'INSTAPAY',
            },
        });
        const pesonet = mount(PayoutRouteDisplay, {
            props: {
                mode: 'operational',
                bankCode: 'GXCHPHM2XXX',
                settlementRail: 'PESONET',
            },
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

        // The readable anchors are still shown even without a destination
        // icon asset.
        expect(wrapper.text()).toContain('09173011987');
        expect(wrapper.text()).not.toContain('AL-AMANAH ISLAMIC BANK');

        // No broken <img> is rendered for the destination segment; the
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
