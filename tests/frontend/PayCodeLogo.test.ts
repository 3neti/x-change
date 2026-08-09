import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayCodeLogo from '../../resources/js/components/x-change/PayCodeLogo.vue';

describe('PayCodeLogo', () => {
    it('renders the horizontal logo by default at brand size', () => {
        const wrapper = mount(PayCodeLogo);

        expect(wrapper.get('img').attributes('src')).toBe(
            '/vendor/x-change/images/pay-code/pay-code-logo.svg',
        );
        expect(wrapper.get('img').attributes('alt')).toBe('Pay Code');
        expect(wrapper.get('img').attributes('style')).toContain(
            'height: 4rem',
        );
        expect(wrapper.get('img').attributes('style')).toContain(
            'max-height: 4rem',
        );
    });

    it('renders the mark variant', () => {
        const wrapper = mount(PayCodeLogo, {
            props: {
                variant: 'mark',
            },
        });

        expect(wrapper.get('img').attributes('src')).toBe(
            '/vendor/x-change/images/pay-code/pay-code-mark.svg',
        );
        expect(wrapper.get('img').attributes('alt')).toBe('Pay Code mark');
        expect(wrapper.get('img').attributes('style')).toContain(
            'height: 4rem',
        );
    });

    it('renders the lockup variant', () => {
        const wrapper = mount(PayCodeLogo, {
            props: {
                variant: 'lockup',
            },
        });

        expect(wrapper.get('img').attributes('src')).toBe(
            '/vendor/x-change/images/pay-code/pay-code-lockup.svg',
        );
        expect(wrapper.get('img').attributes('alt')).toBe('Pay Code');
        expect(wrapper.get('img').attributes('style')).toContain(
            'max-width: 8rem',
        );
    });

    it('supports the compact claim header size', () => {
        const wrapper = mount(PayCodeLogo, {
            props: {
                size: 'header',
            },
        });

        expect(wrapper.get('img').attributes('style')).toContain(
            'height: 2rem',
        );
        expect(wrapper.get('img').attributes('style')).toContain(
            'max-height: 2rem',
        );
    });

    it('supports the display size for larger preview surfaces', () => {
        const wrapper = mount(PayCodeLogo, {
            props: {
                size: 'display',
            },
        });

        expect(wrapper.get('img').attributes('style')).toContain(
            'height: 5rem',
        );
        expect(wrapper.get('img').attributes('style')).toContain(
            'max-height: 5rem',
        );
    });
});
