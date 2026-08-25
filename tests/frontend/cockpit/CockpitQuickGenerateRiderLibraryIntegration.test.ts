import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick } from 'vue';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import CockpitRiderLibrary from '../../../resources/js/cockpit/components/CockpitRiderLibrary.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    }),
    router: {
        delete: vi.fn(),
        patch: vi.fn(),
        post: vi.fn(),
        reload: vi.fn(),
    },
}));

describe('Quick Generate Rider Library integration', () => {
    it('passes one owner library to both Rider editors', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                riderLibrary: [
                    {
                        reference: '01LINK',
                        kind: 'url',
                        label: 'Portal',
                        payload: { url: 'https://example.test/portal' },
                        saved: true,
                        pinned: true,
                        use_count: 1,
                    },
                    {
                        reference: '01SPLASH',
                        kind: 'splash',
                        label: 'Greeting',
                        payload: { splash: 'Welcome', format: 'plain' },
                        saved: false,
                        pinned: false,
                        use_count: 1,
                    },
                ],
            },
        });

        await nextTick();
        await wrapper
            .get('[data-testid="cockpit-quick-generate-order-options-toggle"]')
            .trigger('click');
        const designOption = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-option-design"]',
        );
        (designOption.element as HTMLDetailsElement).open = true;
        await designOption.trigger('toggle');
        await nextTick();

        const libraries = wrapper.findAllComponents(CockpitRiderLibrary);

        expect(libraries).toHaveLength(2);
        expect(libraries[0]?.props('kind')).toBe('url');
        expect(libraries[1]?.props('kind')).toBe('splash');
        expect(libraries[0]?.props('entries')).toHaveLength(2);
        expect(libraries[1]?.props('entries')).toHaveLength(2);
    });

    it('applies library payloads to the existing reactive Rider editors', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
            },
        });

        await nextTick();
        await wrapper
            .get('[data-testid="cockpit-quick-generate-order-options-toggle"]')
            .trigger('click');
        const designOption = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-option-design"]',
        );
        (designOption.element as HTMLDetailsElement).open = true;
        await designOption.trigger('toggle');
        await nextTick();
        const libraries = wrapper.findAllComponents(CockpitRiderLibrary);

        libraries[0]?.vm.$emit('apply', {
            url: 'https://example.test/after-claim',
        });
        libraries[1]?.vm.$emit('apply', {
            splash: '**Welcome**',
            format: 'markdown',
        });
        await wrapper.vm.$nextTick();

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-rider-url"]',
            ).element.value,
        ).toBe('https://example.test/after-claim');
        expect(
            wrapper.get<HTMLTextAreaElement>(
                '[data-testid="cockpit-quick-generate-rider-splash-body"]',
            ).element.value,
        ).toBe('**Welcome**');
        expect(
            wrapper.get<HTMLInputElement>(
                '[name="rider-splash-format"][value="markdown"]',
            ).element.checked,
        ).toBe(true);
    });
});
