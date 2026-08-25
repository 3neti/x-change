import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

describe('Cockpit Quick Generate always-visible Order sizing', () => {
    it('establishes a min-w-0 boundary on the Order card at every width', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        expect(orderCard.classes()).toContain('min-w-0');
        expect(orderCard.classes()).toContain('rounded-2xl');
        expect(orderCard.classes()).not.toContain('max-md:hidden');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-essentials-canvas"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-order-composer-trigger"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="cockpit-pay-code-canvas"]').exists(),
        ).toBe(false);
    });

    it('does not introduce fixed positioning or a whole-card overflow workaround', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        expect(
            orderCard.classes().some((className) => /^w-\[/.test(className)),
        ).toBe(false);
        expect(orderCard.classes()).not.toContain('overflow-hidden');
        expect(orderCard.classes()).not.toContain('absolute');
        expect(orderCard.classes()).not.toContain('fixed');
        expect(orderCard.attributes('role')).toBeUndefined();
        expect(orderCard.attributes('aria-modal')).toBeUndefined();
    });

    it('keeps Order controls and inline preview disclosures usable', async () => {
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
        await orderCard
            .get('[data-testid="cockpit-quick-generate-order-options-toggle"]')
            .trigger('click');

        expect(
            orderCard
                .find(
                    '[data-testid="cockpit-quick-generate-order-option-design"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            orderCard
                .find(
                    '[data-testid="cockpit-quick-generate-order-option-claim-preview"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-submit-button"]')
                .exists(),
        ).toBe(true);
    });
});
