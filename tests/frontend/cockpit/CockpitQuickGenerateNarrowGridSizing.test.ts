import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

describe('Cockpit Quick Generate narrow essentials grid sizing', () => {
    it('establishes a min-w-0 boundary on the responsive grid and both direct grid children', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const grid = wrapper.get(
            '[data-testid="cockpit-quick-generate-essentials-canvas"]',
        );

        // The grid parent itself is a defensive min-w-0 boundary.
        expect(grid.classes()).toContain('grid');
        expect(grid.classes()).toContain('min-w-0');

        // Below 2xl this is a single implicit-column grid; the two elements
        // that are its *direct* children must each opt out of the CSS Grid
        // default min-width: auto so their intrinsic content can shrink
        // inside the narrow single-column track instead of forcing it wide.
        const directChildren = Array.from(grid.element.children);
        expect(directChildren).toHaveLength(2);

        const [orderCard, canvasWrapper] = directChildren;
        expect(orderCard.getAttribute('data-testid')).toBe(
            'cockpit-quick-generate-order-card',
        );
        expect(orderCard.classList.contains('min-w-0')).toBe(true);

        expect(canvasWrapper.classList.contains('min-w-0')).toBe(true);
        // The right canvas becomes sticky only when the two-column 2xl
        // layout is active. It must remain ordinary document flow at xl.
        expect(canvasWrapper.classList.contains('2xl:sticky')).toBe(true);
        expect(canvasWrapper.classList.contains('2xl:top-4')).toBe(true);
        expect(canvasWrapper.classList.contains('2xl:self-start')).toBe(true);
        expect(canvasWrapper.classList.contains('xl:sticky')).toBe(false);
        expect(canvasWrapper.classList.contains('xl:top-4')).toBe(false);
        expect(canvasWrapper.classList.contains('xl:self-start')).toBe(false);

        // The 2xl two-column tracks keep both existing floors and share an
        // equal compact cap so neither card overwhelms the desktop canvas.
        expect(grid.classes()).toContain(
            '2xl:grid-cols-[minmax(19rem,40rem)_minmax(28rem,40rem)]',
        );
        expect(grid.classes()).toContain('2xl:justify-center');
        expect(grid.classes()).not.toContain(
            'xl:grid-cols-[minmax(19rem,1fr)_minmax(28rem,1fr)]',
        );
    });

    it('does not introduce a fixed width or a whole-card overflow workaround to solve narrow sizing', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const grid = wrapper.get(
            '[data-testid="cockpit-quick-generate-essentials-canvas"]',
        );
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        // No fixed-width utility classes were added to the grid or its two
        // direct children themselves to force the narrow layout to fit.
        // (Pre-existing, unrelated descendants such as popovers or canvas
        // artwork percentage caps are out of scope for this boundary fix.)
        const canvasWrapper = wrapper.get(
            '[data-testid="cockpit-quick-generate-essentials-canvas"] > :nth-child(2)',
        );

        [grid, orderCard, canvasWrapper].forEach((node) => {
            expect(
                node.classes().some((className) => /^w-\[/.test(className)),
            ).toBe(false);
            expect(
                node
                    .classes()
                    .some(
                        (className) =>
                            /^min-w-(\d|\[)/.test(className) &&
                            className !== 'min-w-0',
                    ),
            ).toBe(false);
        });

        // The card itself must not resort to hiding its own overflow to
        // conceal an oversized layout; internal controls (e.g. the
        // Templates action toolbar) may still scroll locally.
        expect(orderCard.classes()).not.toContain('overflow-hidden');
        expect(grid.classes()).not.toContain('overflow-hidden');

        // No absolute positioning was added directly to the grid or its two
        // direct children by this fix.
        expect(grid.classes()).not.toContain('absolute');
        expect(orderCard.classes()).not.toContain('absolute');
        expect(canvasWrapper.classes()).not.toContain('absolute');
    });

    it('keeps the ordinary Pay Code canvas and Order-card controls usable inside the grid', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        expect(
            orderCard
                .find('[data-testid="cockpit-quick-generate-amount-field"]')
                .exists(),
        ).toBe(true);
        expect(
            orderCard
                .find(
                    '[data-testid="cockpit-quick-generate-primary-recipient"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            orderCard
                .find('[data-testid="cockpit-quick-generate-primary-purpose"]')
                .exists(),
        ).toBe(true);
        expect(
            orderCard
                .find('[data-testid="cockpit-claim-requirements-control"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-submit-button"]')
                .exists(),
        ).toBe(true);
    });
});
