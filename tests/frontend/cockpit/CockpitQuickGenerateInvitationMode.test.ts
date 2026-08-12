import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
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

describe('Cockpit Quick Generate Invitation mode', () => {
    it('is visible near the Order header without expanding any collapsed section', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );
        const modeControl = orderCard.get(
            '[data-testid="cockpit-quick-generate-mode-control"]',
        );

        expect(
            wrapper
                .findAll('details')
                .every((details) => details.attributes('open') === undefined),
        ).toBe(true);
        expect(modeControl.text()).toContain('Pay Code');
        expect(modeControl.text()).toContain('Invitation');
    });

    it('defaults to Pay Code for an ordinary entry and to Invitation when onboardingPreset is set', () => {
        const ordinary = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        expect(
            ordinary
                .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            ordinary
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('false');
        expect(
            ordinary
                .get('[data-testid="cockpit-quick-generate-voucher-kind"]')
                .text(),
        ).toBe('Disburseable');
        expect(
            ordinary
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .text(),
        ).toContain('Issue Pay Code');

        const invitation = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                onboardingPreset: true,
            },
        });

        expect(
            invitation
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            invitation
                .get('[data-testid="cockpit-quick-generate-voucher-kind"]')
                .text(),
        ).toBe('Account Invitation');
        expect(
            invitation
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .text(),
        ).toContain('Issue Invitation');
    });

    it('keeps the ordinary Issuance entry in Pay Code mode when the last instructions were an invitation', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-12T00:00:00Z',
                    instructions: {
                        onboarding: true,
                        cash: { amount: 50, currency: 'PHP' },
                        inputs: {
                            fields: ['name', 'email', 'mobile', 'otp'],
                        },
                    },
                },
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .text(),
        ).toContain('Issue Pay Code');
        expect(quickGenerateEngineeringPreview(wrapper).onboarding).toBeUndefined();
    });

    it('keeps the explicit invitation entry in Invitation mode when the last instructions were an ordinary Pay Code', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                onboardingPreset: true,
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-12T00:00:00Z',
                    instructions: {
                        cash: { amount: 50, currency: 'PHP' },
                        inputs: { fields: ['mobile'] },
                    },
                },
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .text(),
        ).toContain('Issue Invitation');
        expect(quickGenerateEngineeringPreview(wrapper).onboarding).toBe(true);
    });

    it('emits onboarding:true and locks name/email/mobile/otp only when Invitation mode is selected', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        let preview = quickGenerateEngineeringPreview(wrapper);
        expect(preview.onboarding).toBeUndefined();

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
            .trigger('click');

        preview = quickGenerateEngineeringPreview(wrapper);
        expect(preview.onboarding).toBe(true);
        expect(preview.inputs.fields).toEqual(
            expect.arrayContaining(['mobile', 'email', 'name', 'otp']),
        );
        expect(
            wrapper.findAll('[data-onboarding-locked="true"]'),
        ).toHaveLength(4);
        expect(
            wrapper.get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('unlocks fields and clears the onboarding flag only on an explicit switch back to Pay Code', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                onboardingPreset: true,
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
            .trigger('click');

        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.onboarding).toBeUndefined();
        expect(
            wrapper.findAll('[data-onboarding-locked="true"]'),
        ).toHaveLength(0);
        expect(
            wrapper.get('[data-testid="cockpit-quick-generate-mode-paycode"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('keeps Invitation mode durable across Blank, Repeat Last, and template selection', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-10T00:00:00Z',
                    instructions: {
                        cash: { amount: 75, currency: 'PHP' },
                        inputs: { fields: [] },
                    },
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
            .trigger('click');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-repeat-last"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-choose-template"]')
            .trigger('click');
        await wrapper
            .findAll(
                '[data-testid="cockpit-quick-generate-template-option"]',
            )[0]
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('lets an onboarding-enabled saved template enable Invitation mode from an ordinary entry', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                savedTemplates: [
                    {
                        reference: '01KYSAVEDTEMPLATE',
                        name: 'Invite Template',
                        description: null,
                        base_template_key: 'blank-pay-code',
                        instructions: {
                            onboarding: true,
                            inputs: { fields: [] },
                        },
                        include_amount: false,
                        include_purpose: true,
                    },
                ],
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-choose-template"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-saved-template-option"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('preserves Name, Email, Mobile, and OTP as selected-but-unlocked after Invitation → Blank → Pay Code', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
            .trigger('click');

        // omits onboarding from the payload.
        const preview = quickGenerateEngineeringPreview(wrapper);
        expect(preview.onboarding).toBeUndefined();

        // retains the previously onboarding-imposed fields in inputs.fields.
        expect(preview.inputs.fields).toEqual(
            expect.arrayContaining(['mobile', 'email', 'name', 'otp']),
        );

        // no field remains locked once Pay Code mode is explicit.
        expect(
            wrapper.findAll('[data-onboarding-locked="true"]'),
        ).toHaveLength(0);

        for (const field of ['name', 'email', 'mobile', 'otp']) {
            const chip = wrapper.get(
                `[data-testid="cockpit-claim-requirement-chip-${field}"]`,
            );

            expect(chip.attributes('data-locked')).toBe('false');
        }

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('lets the user remove each formerly onboarding-imposed field after switching back to Pay Code', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
            .trigger('click');

        for (const field of ['name', 'email', 'mobile', 'otp']) {
            await wrapper
                .get(`[data-testid="cockpit-claim-requirement-chip-${field}"]`)
                .get('[data-testid="cockpit-claim-requirement-chip-remove"]')
                .trigger('click');

            expect(
                wrapper
                    .find(
                        `[data-testid="cockpit-claim-requirement-chip-${field}"]`,
                    )
                    .exists(),
            ).toBe(false);
        }

        const preview = quickGenerateEngineeringPreview(wrapper);
        expect(preview.inputs.fields).not.toEqual(
            expect.arrayContaining(['name', 'email', 'mobile', 'otp']),
        );
    });

    it('preserves only the fields actually required under an OTP-disabled onboarding policy', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                onboardingOtpRequired: false,
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
            .trigger('click');

        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.inputs.fields).toEqual(
            expect.arrayContaining(['mobile', 'email', 'name']),
        );
        expect(preview.inputs.fields).not.toContain('otp');
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-requirement-chip-otp"]')
                .exists(),
        ).toBe(false);
    });

    it('retains unrelated manually selected requirements across the Invitation → Pay Code transition', async () => {
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

        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-invitation"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
            .trigger('click');

        const preview = quickGenerateEngineeringPreview(wrapper);
        expect(preview.inputs.fields).toContain('kyc');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-kyc"]')
                .exists(),
        ).toBe(true);
    });

    it('leaves ordinary Pay Code entry (never switched to Invitation) unaffected', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.onboarding).toBeUndefined();
        expect(preview.inputs.fields).not.toContain('name');
        expect(preview.inputs.fields).not.toContain('email');
        expect(preview.inputs.fields).not.toContain('otp');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-mode-paycode"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('exposes the mode toggle as a keyboard-operable aria-pressed group with no legacy checkbox remaining', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const modeControl = wrapper.get(
            '[data-testid="cockpit-quick-generate-mode-control"]',
        );
        const payCodeButton = modeControl.get(
            '[data-testid="cockpit-quick-generate-mode-paycode"]',
        );
        const invitationButton = modeControl.get(
            '[data-testid="cockpit-quick-generate-mode-invitation"]',
        );

        expect(payCodeButton.element.tagName.toLowerCase()).toBe('button');
        expect(invitationButton.element.tagName.toLowerCase()).toBe('button');
        expect(payCodeButton.attributes('type')).toBe('button');
        expect(invitationButton.attributes('type')).toBe('button');
        expect(payCodeButton.attributes('aria-pressed')).toBeDefined();
        expect(invitationButton.attributes('aria-pressed')).toBeDefined();
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-onboarding-toggle"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-onboarding"]')
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('Set Up Recipient Account');
    });
});
