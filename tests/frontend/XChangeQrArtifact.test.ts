import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import XChangeQrArtifact from '../../resources/js/components/x-change/XChangeQrArtifact.vue';

describe('x-change QR artifact', () => {
    it('identifies an issued Pay Code without changing its QR image', () => {
        const wrapper = mount(XChangeQrArtifact, {
            props: {
                kind: 'pay_code',
                src: 'data:image/png;base64,PAYCODE',
                alt: 'Pay Code ABCD',
                title: 'Scan to claim',
                description: 'Scan to open this Pay Code directly.',
                identifier: 'ABCD',
            },
        });

        expect(wrapper.get('img[alt="Pay Code ABCD"]').attributes('src')).toBe(
            'data:image/png;base64,PAYCODE',
        );
        expect(wrapper.text()).toContain('|| ABCD ||');
        expect(
            wrapper
                .find('[data-testid="x-change-qr-artifact-pay-code-mark"]')
                .exists(),
        ).toBe(true);
    });

    it('frames QR Ph as payment without adding a Pay Code mark', () => {
        const wrapper = mount(XChangeQrArtifact, {
            props: {
                kind: 'qrph_payment',
                src: 'data:image/png;base64,QRPH',
                alt: 'QR Ph payment',
                title: 'Pay ₱50.00',
            },
        });

        expect(wrapper.attributes('data-kind')).toBe('qrph_payment');
        expect(wrapper.attributes('class')).toContain('border-double');
        expect(wrapper.text()).toContain('QR Ph · Scan to pay');
        expect(
            wrapper
                .find('[data-testid="x-change-qr-artifact-pay-code-mark"]')
                .exists(),
        ).toBe(false);
    });
});
