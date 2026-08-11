import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

function quickGenerateEngineeringPreview(
    wrapper: VueWrapper,
): Record<string, any> {
    return JSON.parse(
        wrapper
            .find(
                '[data-testid="cockpit-quick-generate-engineering-preview-json"]',
            )
            .text(),
    );
}

describe('Cockpit Quick Generate Order card help glyphs and resting-state cleanup', () => {
    it('renders no removed resting helper sentences at rest', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        expect(orderCard.text()).not.toContain('Used as the Rider Message.');
        expect(orderCard.text()).not.toContain(
            'Blank or CASH allows anyone who meets the other claim requirements.',
        );
        expect(
            orderCard
                .find(
                    '[data-testid="cockpit-quick-generate-settlement-rail-description"]',
                )
                .exists(),
        ).toBe(false);
    });

    it('exposes no visible documentation placeholders on Amount, Pay To, Purpose, or Status Updates', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-primary-amount"]')
                .attributes('placeholder'),
        ).toBeUndefined();
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-primary-recipient"]',
                )
                .attributes('placeholder'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-primary-purpose"]')
                .attributes('placeholder'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[data-testid="cockpit-feedback-destination-editor"]')
                .attributes('placeholder'),
        ).toBeUndefined();
        // Accessible names are preserved even without placeholders.
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-primary-amount"]')
                .attributes('aria-label'),
        ).toBeTruthy();
        expect(
            wrapper
                .get('[data-testid="cockpit-feedback-destination-editor"]')
                .attributes('aria-label'),
        ).toBeTruthy();
    });

    it('gives each Order-card help glyph a keyboard-focusable trigger with an accessible name and a focus-reachable tooltip', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );
        const glyphs = orderCard.findAll(
            '[data-testid="cockpit-field-help"]',
        );

        // Amount, Pay To, Purpose, Status Updates, Claim Requirements,
        // Transfer Network.
        expect(glyphs.length).toBe(6);

        glyphs.forEach((glyph) => {
            const trigger = glyph.get(
                '[data-testid="cockpit-field-help-trigger"]',
            );
            const tooltip = glyph.get(
                '[data-testid="cockpit-field-help-tooltip"]',
            );

            expect(trigger.element.tagName.toLowerCase()).toBe('button');
            expect(trigger.attributes('disabled')).toBeUndefined();
            expect(trigger.attributes('aria-label')).toBeTruthy();
            expect(tooltip.attributes('role')).toBe('tooltip');
            expect(trigger.attributes('aria-describedby')).toBe(
                tooltip.attributes('id'),
            );
            // Available on focus, not only hover.
            expect(tooltip.classes()).toContain(
                'group-focus-within/field-help:opacity-100',
            );
            expect(tooltip.classes()).toContain(
                'group-hover/field-help:opacity-100',
            );
            expect(tooltip.text().length).toBeGreaterThan(0);
        });
    });

    it('moves the ordinary Transfer Network description into its tooltip while keeping validation/unavailable errors visible', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                settlementRailCapabilities: {
                    schema: 'x-change.cockpit.settlement-rail-capabilities.v1',
                    provider: {
                        code: 'netbank',
                        label: 'NetBank',
                        enabled: true,
                        binding_provider: 'netbank',
                        binding_coherent: true,
                    },
                    connection_reference: 'netbank-primary',
                    default_mode: 'automatic',
                    automatic_policy: {
                        instapay_below_amount_minor: 5_000_000,
                        resolved_per_payout: true,
                    },
                    rails: [
                        {
                            code: 'INSTAPAY',
                            label: 'InstaPay',
                            enabled: false,
                            currency: 'PHP',
                            minimum_amount_minor: 1,
                            maximum_amount_minor: 5_000_000,
                            provider_fee_minor: 1_000,
                            availability_reason: 'InstaPay is disabled.',
                        },
                    ],
                    source: 'configured-provider-capabilities',
                    live_provider_call: false,
                },
            },
        });
        const railControl = wrapper.get(
            '[data-testid="cockpit-quick-generate-primary-settlement-rail"]',
        );

        expect(
            railControl
                .get('[data-testid="cockpit-quick-generate-settlement-rail-error"]')
                .text(),
        ).toContain('InstaPay is disabled');
        expect(
            railControl.get('[data-testid="cockpit-field-help-tooltip"]').text()
                .length,
        ).toBeGreaterThan(0);
        expect(
            railControl
                .find(
                    '[data-testid="cockpit-quick-generate-settlement-rail-description"]',
                )
                .exists(),
        ).toBe(false);
    });

    it('keeps the Amount calculator and immediate numeric keyboard entry working without a placeholder', async () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: host,
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await flushPromises();

        const amountInput = wrapper.get(
            '[data-testid="cockpit-quick-generate-primary-amount"]',
        );

        expect(amountInput.element).toBe(document.activeElement);

        await amountInput.trigger('keydown', { key: '5' });
        await flushPromises();

        expect(
            wrapper.get('[data-testid="numeric-keypad-display"]').text(),
        ).toContain('₱5');

        wrapper.unmount();
        host.remove();
    });

    it('keeps Pay To inference locking Mobile and OTP in the Claim Requirements chips', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('09173011987');

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-mobile"]')
                .attributes('data-locked'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-otp"]')
                .attributes('data-locked'),
        ).toBe('true');
        expect(
            quickGenerateEngineeringPreview(wrapper).inputs.fields,
        ).toEqual(expect.arrayContaining(['mobile', 'otp']));
    });

    it('keeps Status Updates parsing and saved-destination shortcuts unchanged', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                feedbackDefaults: {
                    email: 'saved@example.com',
                    mobile: null,
                    webhook: null,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-feedback-destination-editor"]')
            .setValue('custom@example.com');
        await wrapper
            .get('[data-testid="cockpit-feedback-destination-editor"]')
            .trigger('keydown', { key: 'Enter' });

        expect(
            wrapper
                .get('[data-testid="cockpit-feedback-destination-email"]')
                .text(),
        ).toContain('custom@example.com');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-feedback-destination-suggestion-email"]',
                )
                .exists(),
        ).toBe(false);
    });

    it('keeps the Claim Requirements compact/detailed synchronization unchanged', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-trigger"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-claim-requirement-option-kyc"]')
            .get('input[type="checkbox"]')
            .setValue(true);

        expect(
            wrapper.get('input[value="kyc"]').element as HTMLInputElement,
        ).toMatchObject({ checked: true });
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-kyc"]')
                .exists(),
        ).toBe(true);
    });

    it('has no forced horizontal overflow at the ~304px Order-card width and no secondary control using the primary Issue Pay Code styling', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        const oversizedMinWidths = orderCard
            .html()
            .match(/min-w-(\d|\[)/g)
            ?.filter((match) => match !== 'min-w-0');

        expect(oversizedMinWidths ?? []).toHaveLength(0);

        const submitButton = orderCard.get(
            '[data-testid="cockpit-quick-generate-submit-button"]',
        );

        expect(submitButton.classes()).toContain('bg-emerald-600');

        const secondaryButtons = orderCard
            .findAll('button')
            .filter(
                (button) =>
                    button.attributes('data-testid') !==
                    'cockpit-quick-generate-submit-button',
            );

        secondaryButtons.forEach((button) => {
            expect(button.classes()).not.toContain('bg-emerald-600');
        });
    });
});
