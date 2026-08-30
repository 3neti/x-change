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
        const tabs = wrapper.findAll('a');

        expect(tabs).toHaveLength(5);
        expect(tabs.map((tab) => tab.text())).toEqual([
            'Funding',
            'Issuance',
            'Overview',
            'Pay Codes',
            'Campaigns',
        ]);
        expect(tabs.map((tab) => tab.attributes('href'))).toEqual([
            '/x/cockpit/funding',
            '/x/cockpit/quick-generate',
            '/x/cockpit/overview',
            '/x/cockpit/pay-codes',
            '/x/cockpit/campaigns',
        ]);
        expect(
            wrapper.get('[data-testid="cockpit-mobile-tab-bar"]').classes(),
        ).toEqual(expect.arrayContaining(['fixed', 'bottom-0', 'md:hidden']));
        expect(tabs.every((tab) => tab.classes().includes('min-h-14'))).toBe(
            true,
        );
    });

    it('marks only the active workspace as the current page', () => {
        const wrapper = mount(CockpitMobileTabBar, {
            props: {
                activeKey: 'campaigns',
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-campaigns"]')
                .attributes('aria-current'),
        ).toBe('page');
        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-funding"]')
                .attributes('aria-current'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="cockpit-mobile-tab-campaigns"]')
                .classes(),
        ).toEqual(expect.arrayContaining(['bg-primary/10', 'text-primary']));
    });
});
