import { afterEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
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

// Each CockpitFieldHelp instance teleports its tooltip to document.body.
// Unmounting between tests keeps that shared DOM clean so a later test's
// id-based tooltip lookup can't accidentally resolve a stale element.
const activeWrappers: VueWrapper[] = [];

function mountPanel(
    props: InstanceType<typeof CockpitQuickGenerateSubmitPanel>['$props'],
): VueWrapper {
    const wrapper = mount(CockpitQuickGenerateSubmitPanel, { props });

    activeWrappers.push(wrapper);

    return wrapper;
}

function tooltipFor(
    trigger: ReturnType<VueWrapper['get']>,
): Element | undefined {
    const describedBy = trigger.attributes('aria-describedby');

    return Array.from(
        document.body.querySelectorAll(
            '[data-testid="cockpit-field-help-tooltip"]',
        ),
    ).find((candidate) => candidate.id === describedBy);
}

afterEach(() => {
    activeWrappers.forEach((wrapper) => wrapper.unmount());
    activeWrappers.length = 0;
});

describe('Cockpit Quick Generate Order card help glyphs and resting-state cleanup', () => {
    it('renders no removed resting helper sentences at rest', () => {
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });
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
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });

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

    it('keeps the workspace controls together and Value Flow beside the Issue CTA', () => {
        const wrapper = mountPanel({
            templates: cockpitQuickGenerateTemplates,
            onboardingPreset: true,
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );
        const title = orderCard.get('h4');
        const submitButton = orderCard.get(
            '[data-testid="cockpit-quick-generate-submit-button"]',
        );
        const valueFlow = orderCard.get(
            '[data-testid="cockpit-quick-generate-voucher-kind"]',
        );
        const surfaceControl = orderCard.get(
            '[data-testid="cockpit-quick-generate-surface-toggle"]',
        );
        const modeControl = orderCard.get(
            '[data-testid="cockpit-quick-generate-mode-control"]',
        );
        const titleRow = title.element.parentElement?.parentElement;
        const amountActionRow = orderCard.get(
            '[data-testid="cockpit-quick-generate-amount-action-row"]',
        );

        expect(titleRow?.contains(submitButton.element)).toBe(false);
        expect(amountActionRow.element.contains(submitButton.element)).toBe(
            true,
        );
        expect(amountActionRow.classes()).toContain(
            'grid-cols-[minmax(0,1fr)_auto]',
        );

        const workspaceRow = orderCard.get(
            '[data-testid="cockpit-quick-generate-order-mode-row"]',
        );
        expect(workspaceRow.element.contains(surfaceControl.element)).toBe(
            true,
        );
        expect(workspaceRow.text()).toContain('Workspace');
        expect(workspaceRow.element.contains(valueFlow.element)).toBe(false);
        expect(workspaceRow.classes()).toContain('text-sm');
        expect(orderCard.text()).not.toContain(
            'Set the value, payee, and purpose.',
        );
        expect(workspaceRow.element.contains(submitButton.element)).toBe(
            false,
        );
        expect(amountActionRow.element.contains(modeControl.element)).toBe(
            true,
        );
        expect(amountActionRow.element.contains(valueFlow.element)).toBe(true);
        expect(valueFlow.element.contains(modeControl.element)).toBe(false);
        expect(valueFlow.classes()).toContain('row-start-3');
        expect(modeControl.classes()).toContain('row-start-3');
    });

    it('gives the Issue CTA a shrink-resistant, non-clippable structure independent of the badge', () => {
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );
        const submitButton = orderCard.get(
            '[data-testid="cockpit-quick-generate-submit-button"]',
        );
        const badge = orderCard.get(
            '[data-testid="cockpit-quick-generate-voucher-kind"]',
        );

        expect(submitButton.classes()).toContain('shrink-0');
        expect(submitButton.classes()).not.toContain('absolute');
        expect(badge.classes()).not.toContain('absolute');
        // The grid gives Value Flow flexible room while the primary action
        // keeps its full, shrink-resistant width on the same row.
        expect(submitButton.element.parentElement).not.toBe(
            badge.element.parentElement,
        );
        expect(badge.classes()).toContain('col-start-1');
        expect(submitButton.element.parentElement?.classList).toContain(
            'col-start-2',
        );
    });

    it('gives each Order-card help glyph a keyboard-focusable trigger with an accessible name and a focus-reachable tooltip', async () => {
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );
        const glyphs = orderCard.findAll(
            '[data-testid="cockpit-field-help"]',
        );

        // Mode, Amount, Pay To, Purpose, Status Updates, Claim
        // Requirements, Value Use, Transfer Network.
        expect(glyphs.length).toBe(8);

        for (const glyph of glyphs) {
            const trigger = glyph.get(
                '[data-testid="cockpit-field-help-trigger"]',
            );
            // The tooltip is teleported to document.body, so it is no
            // longer a DOM descendant of the glyph; resolve it by the
            // aria-describedby id it shares with the trigger instead.
            const tooltip = tooltipFor(trigger);

            expect(trigger.element.tagName.toLowerCase()).toBe('button');
            expect(trigger.attributes('disabled')).toBeUndefined();
            expect(trigger.attributes('aria-label')).toBeTruthy();
            expect(tooltip).toBeDefined();
            expect(tooltip?.getAttribute('role')).toBe('tooltip');
            expect(trigger.attributes('aria-describedby')).toBe(
                tooltip?.getAttribute('id'),
            );
            expect(tooltip?.textContent?.length).toBeGreaterThan(0);
            expect(tooltip?.classList.contains('opacity-100')).toBe(false);

            // Available on focus, not only hover.
            await trigger.trigger('focus');
            expect(tooltip?.classList.contains('opacity-100')).toBe(true);
            await trigger.trigger('blur');
            expect(tooltip?.classList.contains('opacity-100')).toBe(false);

            await trigger.trigger('mouseenter');
            expect(tooltip?.classList.contains('opacity-100')).toBe(true);
            await trigger.trigger('mouseleave');
            expect(tooltip?.classList.contains('opacity-100')).toBe(false);
        }
    });

    it('moves the ordinary Transfer Network description into its tooltip while keeping validation/unavailable errors visible', async () => {
        const wrapper = mountPanel({
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
        });
        const railControl = wrapper.get(
            '[data-testid="cockpit-quick-generate-primary-settlement-rail"]',
        );
        const railTrigger = railControl.get(
            '[data-testid="cockpit-field-help-trigger"]',
        );

        expect(
            railControl
                .get('[data-testid="cockpit-quick-generate-settlement-rail-error"]')
                .text(),
        ).toContain('InstaPay is disabled');
        expect(tooltipFor(railTrigger)?.textContent?.length).toBeGreaterThan(
            0,
        );
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
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });

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
        const wrapper = mountPanel({
            templates: cockpitQuickGenerateTemplates,
            feedbackDefaults: {
                email: 'saved@example.com',
                mobile: null,
                webhook: null,
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
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });

        await wrapper
            .get(
                '[data-testid="cockpit-quick-generate-order-options-toggle"]',
            )
            .trigger('click');
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
        const wrapper = mountPanel({ templates: cockpitQuickGenerateTemplates });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        const issueActionOptions = orderCard.get(
            '[data-testid="cockpit-quick-generate-issue-action-options"]',
        );
        const inFlowOrderHtml = orderCard
            .html()
            .replace(issueActionOptions.html(), '');
        const oversizedMinWidths = inFlowOrderHtml
            .match(/min-w-(\d|\[)/g)
            ?.filter(
                (match) => match !== 'min-w-0' && match !== 'min-w-4',
            );

        expect(oversizedMinWidths ?? []).toHaveLength(0);
        expect(issueActionOptions.classes()).toContain('absolute');
        expect(issueActionOptions.classes()).toContain('min-w-48');

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
