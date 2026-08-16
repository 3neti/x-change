import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import CockpitWorkspaceNavigationItem from '../../../resources/js/cockpit/components/CockpitWorkspaceNavigationItem.vue';
import { cockpitWorkspaceGuides } from '../../../resources/js/cockpit/workspaceNavigationGuides';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    }),
}));

const icon = defineComponent({ template: '<span data-testid="icon" />' });

function mountItem() {
    return mount(CockpitWorkspaceNavigationItem, {
        props: {
            title: 'Funding',
            description: 'Add and confirm funds',
            href: '/x/cockpit/funding',
            icon,
            active: false,
            guide: cockpitWorkspaceGuides.funding,
            guideHref: '/x/cockpit/documentation',
            step: '1',
        },
        global: {
            stubs: {
                Teleport: true,
            },
        },
    });
}

async function dispatchPointerDown(
    element: Element,
    pointerType: 'mouse' | 'touch',
): Promise<void> {
    const event = new MouseEvent('pointerdown', {
        bubbles: true,
        cancelable: true,
        button: 0,
        clientX: 10,
        clientY: 10,
    });
    Object.defineProperty(event, 'pointerType', { value: pointerType });
    element.dispatchEvent(event);
    await Promise.resolve();
}

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('Cockpit workspace navigation guide', () => {
    it('opens from the explicit accessible help button', async () => {
        const wrapper = mountItem();

        await wrapper
            .get('button[aria-label="How to use Funding"]')
            .trigger('click');

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            'Add money to Client Funds',
        );
        expect(wrapper.get('[role="dialog"]').text()).toContain(
            'Before you begin',
        );
        expect(wrapper.get('[role="dialog"]').text()).toContain('Open Funding');
    });

    it('opens after a 650 millisecond fine-pointer hold and suppresses navigation', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('matchMedia', () => ({ matches: true }));
        const wrapper = mountItem();
        const link = wrapper.get('a[href="/x/cockpit/funding"]');

        await dispatchPointerDown(link.element, 'mouse');
        await vi.advanceTimersByTimeAsync(649);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await vi.advanceTimersByTimeAsync(1);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);

        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });
        link.element.dispatchEvent(click);
        expect(click.defaultPrevented).toBe(true);
    });

    it('does not turn a touch hold into hidden navigation behavior', async () => {
        vi.useFakeTimers();
        vi.stubGlobal('matchMedia', () => ({ matches: true }));
        const wrapper = mountItem();
        const link = wrapper.get('a[href="/x/cockpit/funding"]');

        await dispatchPointerDown(link.element, 'touch');
        await vi.advanceTimersByTimeAsync(700);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(
            wrapper.get('button[aria-label="How to use Funding"]').classes(),
        ).toContain('h-11');
    });

    it('offers Shift+F1 as the keyboard equivalent', async () => {
        const wrapper = mountItem();
        const link = wrapper.get('a[href="/x/cockpit/funding"]');

        await link.trigger('keydown', { key: 'F1', shiftKey: true });

        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
    });
});
