import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CockpitMobileTabBar from '../../../resources/js/cockpit/components/CockpitMobileTabBar.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    }),
}));

describe('Cockpit mobile tab bar', () => {
    it('renders the five primary workspaces in their governed order', () => {
        const wrapper = mount(CockpitMobileTabBar);
        const tabs = wrapper.findAll('a, button');

        expect(tabs).toHaveLength(5);
        expect(tabs.map((tab) => tab.text())).toEqual([
            'Funding',
            'Issuance',
            'Claim',
            'Pay Codes',
            'Overview',
        ]);
        expect(tabs.map((tab) => tab.attributes('href') ?? null)).toEqual([
            '/x/cockpit/funding',
            '/x/cockpit/quick-generate',
            null,
            '/x/cockpit/pay-codes',
            '/x/cockpit/overview',
        ]);
        expect(
            wrapper.get('[data-testid="cockpit-mobile-tab-bar"]').classes(),
        ).toEqual(expect.arrayContaining(['fixed', 'bottom-0', 'md:hidden']));
        expect(tabs.every((tab) => tab.classes().includes('min-h-14'))).toBe(
            true,
        );
    });

    it('opens the Claim launcher without navigating away', async () => {
        const listener = vi.fn();
        window.addEventListener('x-change:open-cockpit-claim-entry', listener);
        const wrapper = mount(CockpitMobileTabBar);

        await wrapper
            .get('[data-testid="cockpit-mobile-tab-claim"]')
            .trigger('click');

        expect(listener).toHaveBeenCalledOnce();
        expect(
            wrapper.get('[data-testid="cockpit-mobile-tab-claim"]').element
                .tagName,
        ).toBe('BUTTON');
        expect(wrapper.text()).not.toContain('Campaigns');

        window.removeEventListener(
            'x-change:open-cockpit-claim-entry',
            listener,
        );
    });

    it('marks only the active workspace as the current page', () => {
        const wrapper = mount(CockpitMobileTabBar, {
            props: {
                activeKey: 'dashboard',
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-dashboard"]')
                .attributes('aria-current'),
        ).toBe('page');
        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-funding"]')
                .attributes('aria-current'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-dashboard"]')
                .classes(),
        ).toEqual(expect.arrayContaining(['bg-primary/10', 'text-primary']));
    });
});
