import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitQuickGenerateOrderPresentation from '../../../resources/js/cockpit/components/CockpitQuickGenerateOrderPresentation.vue';

describe('Cockpit quick generate order presentation', () => {
    it('presents the essential issuance order without mutation controls', () => {
        const wrapper = mount(CockpitQuickGenerateOrderPresentation, {
            props: {
                amount: '₱500.00',
                recipient: 'Lester Hurtado · 0917 301 1987',
                payCodeType: 'Redeemable',
                estimatedCost: '₱506.90',
                purpose: 'Field allowance',
            },
        });

        expect(wrapper.text()).toContain('Order');
        expect(wrapper.text()).toContain('₱500.00');
        expect(wrapper.text()).toContain('Lester Hurtado · 0917 301 1987');
        expect(wrapper.text()).toContain('Redeemable');
        expect(wrapper.text()).toContain('₱506.90');
        expect(wrapper.text()).toContain('Field allowance');
        expect(wrapper.get('img[alt="Pay Code"]').attributes('src')).toContain(
            '/pay-code/pay-code-logo.png',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.find('input').exists()).toBe(false);
    });
});
