import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PayCodeLogo from '../../resources/js/components/x-change/PayCodeLogo.vue';

describe('PayCodeLogo', () => {
    it('renders the horizontal logo by default', () => {
        const wrapper = mount(PayCodeLogo);

        expect(wrapper.get('img').attributes('src')).toBe(
            '/vendor/x-change/images/pay-code/pay-code-logo.svg',
        );
        expect(wrapper.get('img').attributes('alt')).toBe('Pay Code');
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
    });
});
