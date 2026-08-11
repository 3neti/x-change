import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitFieldHelp from '../../../resources/js/cockpit/components/CockpitFieldHelp.vue';

type Rect = {
    top: number;
    left: number;
    right: number;
    bottom: number;
    width: number;
    height: number;
};

function rect(partial: Rect): DOMRect {
    return {
        ...partial,
        x: partial.left,
        y: partial.top,
        toJSON: () => partial,
    } as DOMRect;
}

function stubRect(element: Element, value: Rect): void {
    element.getBoundingClientRect = vi.fn(() => rect(value));
}

function setViewport(width: number, height: number): void {
    Object.defineProperty(window, 'innerWidth', {
        value: width,
        configurable: true,
    });
    Object.defineProperty(window, 'innerHeight', {
        value: height,
        configurable: true,
    });
}

const DEFAULT_VIEWPORT = { width: 1024, height: 768 };
// Each CockpitFieldHelp instance teleports its tooltip into document.body.
// Every mounted wrapper must be unmounted after its test, or a stale
// tooltip element from an earlier test would remain the first DOM match
// for later `document.body.querySelector` lookups.
const activeWrappers: VueWrapper[] = [];

afterEach(() => {
    activeWrappers.forEach((wrapper) => wrapper.unmount());
    activeWrappers.length = 0;
    setViewport(DEFAULT_VIEWPORT.width, DEFAULT_VIEWPORT.height);
});

function mountHelp(tooltip = 'Value the recipient can claim. Select the field to open the calculator.') {
    const wrapper = mount(CockpitFieldHelp, {
        attachTo: document.body,
        props: {
            label: 'About Amount',
            tooltip,
        },
    });

    activeWrappers.push(wrapper);

    const trigger = wrapper.get(
        '[data-testid="cockpit-field-help-trigger"]',
    );
    // useId() produces ids such as ":r0:", which are awkward to use in a
    // CSS id selector (and CSS.escape isn't available in every test
    // environment). The tooltip is teleported to document.body, so match
    // it there directly by comparing element ids instead of selecting by
    // a possibly-unescaped `#id`.
    const describedBy = trigger.attributes('aria-describedby') as string;
    const tooltipEl = Array.from(
        document.body.querySelectorAll(
            '[data-testid="cockpit-field-help-tooltip"]',
        ),
    ).find((candidate) => candidate.id === describedBy) as HTMLElement;

    return { wrapper, trigger, tooltipEl };
}

describe('CockpitFieldHelp collision-safe tooltip', () => {
    it('clamps the left position inside a 320px viewport for a left-edge trigger', async () => {
        setViewport(320, 640);
        const { trigger, tooltipEl } = mountHelp();

        stubRect(trigger.element, {
            top: 100,
            left: 4,
            right: 24,
            bottom: 120,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        await trigger.trigger('mouseenter');

        const left = Number.parseFloat(tooltipEl.style.left);

        expect(left).toBeGreaterThanOrEqual(8);
        expect(left + 240).toBeLessThanOrEqual(320 - 8 + 0.001);
    });

    it('does not overflow the right viewport edge for a right-edge trigger', async () => {
        setViewport(375, 640);
        const { trigger, tooltipEl } = mountHelp();

        stubRect(trigger.element, {
            top: 100,
            left: 355,
            right: 375,
            bottom: 120,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        await trigger.trigger('mouseenter');

        const left = Number.parseFloat(tooltipEl.style.left);

        expect(left + 240).toBeLessThanOrEqual(375 - 8 + 0.001);
        expect(left).toBeGreaterThanOrEqual(8);
    });

    it('does not overflow the left viewport edge for a left-edge trigger', async () => {
        setViewport(375, 640);
        const { trigger, tooltipEl } = mountHelp();

        stubRect(trigger.element, {
            top: 100,
            left: 0,
            right: 20,
            bottom: 120,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        await trigger.trigger('mouseenter');

        const left = Number.parseFloat(tooltipEl.style.left);

        expect(left).toBeGreaterThanOrEqual(8);
    });

    it('prefers appearing above the trigger and flips below when there is insufficient room above', async () => {
        setViewport(1024, 768);
        const { trigger, tooltipEl } = mountHelp();

        // Plenty of room above: expect the tooltip bottom edge (top + height)
        // to sit above the trigger's top edge.
        stubRect(trigger.element, {
            top: 300,
            left: 100,
            right: 120,
            bottom: 320,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        await trigger.trigger('mouseenter');

        const aboveTop = Number.parseFloat(tooltipEl.style.top);

        expect(aboveTop + 40).toBeLessThanOrEqual(300);

        await trigger.trigger('mouseleave');

        // Trigger near the top of the viewport: not enough room above.
        stubRect(trigger.element, {
            top: 2,
            left: 100,
            right: 120,
            bottom: 22,
            width: 20,
            height: 20,
        });

        await trigger.trigger('mouseenter');

        const belowTop = Number.parseFloat(tooltipEl.style.top);

        expect(belowTop).toBeGreaterThanOrEqual(22);
    });

    it('reveals the tooltip on both hover and keyboard focus', async () => {
        const { trigger, tooltipEl } = mountHelp();

        stubRect(trigger.element, {
            top: 100,
            left: 100,
            right: 120,
            bottom: 120,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        expect(tooltipEl.classList.contains('opacity-100')).toBe(false);

        await trigger.trigger('mouseenter');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(true);

        await trigger.trigger('mouseleave');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(false);

        await trigger.trigger('focus');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(true);
    });

    it('dismisses on blur, mouse leave, and Escape', async () => {
        const { trigger, tooltipEl } = mountHelp();

        stubRect(trigger.element, {
            top: 100,
            left: 100,
            right: 120,
            bottom: 120,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        await trigger.trigger('mouseenter');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(true);
        await trigger.trigger('mouseleave');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(false);

        await trigger.trigger('focus');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(true);
        await trigger.trigger('blur');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(false);

        await trigger.trigger('focus');
        expect(tooltipEl.classList.contains('opacity-100')).toBe(true);
        await trigger.trigger('keydown', { key: 'Escape' });
        expect(tooltipEl.classList.contains('opacity-100')).toBe(false);
    });

    it('repositions on window resize and scroll while visible', async () => {
        const { trigger, tooltipEl } = mountHelp();

        stubRect(trigger.element, {
            top: 300,
            left: 100,
            right: 120,
            bottom: 320,
            width: 20,
            height: 20,
        });
        stubRect(tooltipEl, {
            top: 0,
            left: 0,
            right: 240,
            bottom: 40,
            width: 240,
            height: 40,
        });

        await trigger.trigger('mouseenter');

        const initialLeft = tooltipEl.style.left;

        stubRect(trigger.element, {
            top: 300,
            left: 500,
            right: 520,
            bottom: 320,
            width: 20,
            height: 20,
        });
        window.dispatchEvent(new Event('resize'));
        await Promise.resolve();

        expect(tooltipEl.style.left).not.toBe(initialLeft);

        const afterResizeLeft = tooltipEl.style.left;

        stubRect(trigger.element, {
            top: 300,
            left: 700,
            right: 720,
            bottom: 320,
            width: 20,
            height: 20,
        });
        window.dispatchEvent(new Event('scroll'));
        await Promise.resolve();

        expect(tooltipEl.style.left).not.toBe(afterResizeLeft);
    });

    it('removes every window listener on unmount', () => {
        const addSpy = vi.spyOn(window, 'addEventListener');
        const removeSpy = vi.spyOn(window, 'removeEventListener');
        const { wrapper } = mountHelp();

        const addedEvents = addSpy.mock.calls.map((call) => call[0]);

        expect(addedEvents).toContain('resize');
        expect(addedEvents).toContain('scroll');

        wrapper.unmount();

        const removedEvents = removeSpy.mock.calls.map((call) => call[0]);

        expect(removedEvents).toContain('resize');
        expect(removedEvents).toContain('scroll');
        expect(
            removeSpy.mock.calls.filter((call) => call[0] === 'resize'),
        ).toHaveLength(
            addSpy.mock.calls.filter((call) => call[0] === 'resize').length,
        );

        addSpy.mockRestore();
        removeSpy.mockRestore();
    });

    it('keeps role="tooltip" and aria-describedby correctly associated', () => {
        const { trigger, tooltipEl } = mountHelp();

        expect(tooltipEl.getAttribute('role')).toBe('tooltip');
        expect(trigger.attributes('aria-describedby')).toBe(
            tooltipEl.getAttribute('id'),
        );
        expect(trigger.attributes('aria-label')).toBe('About Amount');
        expect(tooltipEl.textContent).toContain(
            'Value the recipient can claim.',
        );
    });

    it('renders plain text tooltip content without interpreting markup', () => {
        const { tooltipEl } = mountHelp('<strong>not bold</strong>');

        expect(tooltipEl.innerHTML).not.toContain('<strong>');
        expect(tooltipEl.textContent).toContain('<strong>not bold</strong>');
    });
});
