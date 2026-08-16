import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
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

async function openClaimRequirementsPopover(
    wrapper: VueWrapper,
): Promise<void> {
    await wrapper
        .get('[data-testid="cockpit-claim-requirements-trigger"]')
        .trigger('click');
}

describe('Cockpit Quick Generate Claim Requirements synchronization', () => {
    it('renders the compact control inside the Order card', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });
        const orderCard = wrapper.get(
            '[data-testid="cockpit-quick-generate-order-card"]',
        );

        expect(
            orderCard
                .find('[data-testid="cockpit-claim-requirements-control"]')
                .exists(),
        ).toBe(true);
    });

    it('groups Evidence before Verification and Details without changing the requirement catalog', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await openClaimRequirementsPopover(wrapper);

        expect(
            wrapper
                .findAll('[data-testid^="cockpit-claim-requirements-group-"]')
                .map((group) => group.attributes('data-testid')),
        ).toEqual([
            'cockpit-claim-requirements-group-evidence',
            'cockpit-claim-requirements-group-verification',
            'cockpit-claim-requirements-group-details',
        ]);
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-claim-requirements-group-evidence"]',
                )
                .findAll('label')
                .map((option) => option.attributes('data-testid')),
        ).toEqual([
            'cockpit-claim-requirement-option-selfie',
            'cockpit-claim-requirement-option-signature',
            'cockpit-claim-requirement-option-location',
        ]);
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-claim-requirements-group-verification"]',
                )
                .findAll('label')
                .map((option) => option.attributes('data-testid')),
        ).toEqual([
            'cockpit-claim-requirement-option-kyc',
            'cockpit-claim-requirement-option-otp',
        ]);
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirements-group-details"]')
                .findAll('label'),
        ).toHaveLength(7);
        expect(wrapper.text()).not.toContain('Common');
    });

    it('selecting a requirement in the Order card selects it in the detailed controls', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        expect(
            wrapper.get('input[value="kyc"]').element as HTMLInputElement,
        ).toMatchObject({ checked: false });

        await openClaimRequirementsPopover(wrapper);
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

    it('removing an optional requirement chip updates the detailed controls', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await openClaimRequirementsPopover(wrapper);
        await wrapper
            .get('[data-testid="cockpit-claim-requirement-option-kyc"]')
            .get('input[type="checkbox"]')
            .setValue(true);

        expect(
            wrapper.get('input[value="kyc"]').element as HTMLInputElement,
        ).toMatchObject({ checked: true });

        await wrapper
            .get('[data-testid="cockpit-claim-requirement-chip-kyc"]')
            .get('[data-testid="cockpit-claim-requirement-chip-remove"]')
            .trigger('click');

        expect(
            wrapper.get('input[value="kyc"]').element as HTMLInputElement,
        ).toMatchObject({ checked: false });
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-requirement-chip-kyc"]')
                .exists(),
        ).toBe(false);
    });

    it('changing the detailed controls updates the Order chips', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-claim-requirement-chip-signature"]',
                )
                .exists(),
        ).toBe(false);

        await wrapper.get('input[value="signature"]').setValue(true);

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-signature"]')
                .exists(),
        ).toBe(true);
    });

    it('locks Mobile and OTP as unremovable chips with a visible reason as soon as Pay To is a mobile number, and unlocks them when Pay To changes', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('09173011987');

        const mobileChip = wrapper.get(
            '[data-testid="cockpit-claim-requirement-chip-mobile"]',
        );
        const otpChip = wrapper.get(
            '[data-testid="cockpit-claim-requirement-chip-otp"]',
        );

        expect(mobileChip.attributes('data-locked')).toBe('true');
        expect(otpChip.attributes('data-locked')).toBe('true');
        expect(
            mobileChip
                .find('[data-testid="cockpit-claim-requirement-chip-remove"]')
                .exists(),
        ).toBe(false);
        expect(mobileChip.attributes('aria-label')).toContain(
            'Pay To is a mobile number',
        );

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('');

        expect(
            wrapper
                .find('[data-testid="cockpit-claim-requirement-chip-otp"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-mobile"]')
                .attributes('data-locked'),
        ).toBe('false');
    });

    it('Blank Pay Code clears manually selected optional requirements, and inference still locks Mobile/OTP immediately afterwards', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: { templates: cockpitQuickGenerateTemplates },
        });

        await openClaimRequirementsPopover(wrapper);
        await wrapper
            .get('[data-testid="cockpit-claim-requirement-option-signature"]')
            .get('input[type="checkbox"]')
            .setValue(true);

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-signature"]')
                .exists(),
        ).toBe(true);

        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-claim-requirement-chip-signature"]',
                )
                .exists(),
        ).toBe(false);

        // The inference rule itself (not a stale lock) is preserved: setting
        // a mobile Pay To right after Blank still locks Mobile + OTP.
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
    });

    it('Repeat Last preloads the correct chips from the last successful instructions', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-11T00:00:00Z',
                    instructions: {
                        cash: { amount: 75, currency: 'PHP' },
                        inputs: { fields: ['name', 'reference_code'] },
                    },
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-repeat-last"]')
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-requirement-chip-name"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-claim-requirement-chip-reference_code"]',
                )
                .exists(),
        ).toBe(true);
    });

    it('keeps an unavailable capability disabled and never inserts it into inputs.fields, while canonical inputs.fields still reach the engineering preview', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                instructionCapabilities: {
                    location: {
                        key: 'location',
                        label: 'Location',
                        status: 'unavailable',
                        issuance_allowed: false,
                        claim_retryable: true,
                        reason: 'Location services are not configured.',
                        missing_configuration: ['MAPBOX_TOKEN'],
                        source: 'form-handler-location',
                    },
                },
            },
        });

        await openClaimRequirementsPopover(wrapper);
        const locationOption = wrapper.get(
            '[data-testid="cockpit-claim-requirement-option-location"]',
        );

        expect(
            locationOption.get('input[type="checkbox"]').attributes('disabled'),
        ).toBeDefined();
        expect(locationOption.text()).toContain(
            'Location services are not configured.',
        );

        await locationOption.get('input[type="checkbox"]').trigger('change');
        await wrapper
            .get('[data-testid="cockpit-claim-requirement-option-kyc"]')
            .get('input[type="checkbox"]')
            .setValue(true);

        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.inputs.fields).not.toContain('location');
        expect(preview.inputs.fields).toContain('kyc');
    });

    it('keeps Estimated Cost reactive through the existing pricing engine after toggling a requirement', async () => {
        vi.useFakeTimers();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                success: true,
                data: {
                    currency: 'PHP',
                    base_fee: 12,
                    charges: [
                        { label: 'Pay Code Generation', price: 12 },
                        {
                            catalog_item_reference: 'inputs.fields.kyc',
                            label: 'KYC Verification',
                            price: 8,
                        },
                    ],
                    total: 20,
                    pay_code_value: 50,
                    account_debit: 70,
                },
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                clientFundsMinor: 100_000,
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
            .get('[data-testid="cockpit-quick-generate-primary-amount"]')
            .setValue('50');
        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();
        await wrapper.vm.$nextTick();

        fetchMock.mockClear();

        await openClaimRequirementsPopover(wrapper);
        await flushPromises();
        await wrapper
            .get('[data-testid="cockpit-claim-requirement-option-kyc"]')
            .get('input[type="checkbox"]')
            .setValue(true);
        await flushPromises();
        await wrapper.vm.$nextTick();

        // Confirm the toggle actually reached the canonical inputs.fields
        // state before relying on the debounced estimate call to observe it.
        expect(
            wrapper.get('input[value="kyc"]').element as HTMLInputElement,
        ).toMatchObject({ checked: true });

        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/x/v1/pay-codes/estimate',
            expect.objectContaining({ method: 'POST' }),
        );
        const lastCallBody = JSON.parse(
            String(fetchMock.mock.calls.at(-1)?.[1]?.body),
        );

        expect(lastCallBody.inputs.fields).toContain('kyc');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-account-debit-amount"]',
                )
                .text(),
        ).toBe('₱70.00');

        wrapper.unmount();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('keeps Selfie-only claim requirements and the Cost face free of an implicit KYC charge', async () => {
        vi.useFakeTimers();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                success: true,
                data: {
                    currency: 'PHP',
                    base_fee: 12,
                    charges: [
                        { label: 'Pay Code Generation', price: 12 },
                        {
                            catalog_item_reference: 'inputs.fields.selfie',
                            label: 'Selfie Photo',
                            price: 3,
                        },
                    ],
                    total: 15,
                    pay_code_value: 50,
                    account_debit: 65,
                },
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                clientFundsMinor: 100_000,
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
            .get('[data-testid="cockpit-quick-generate-primary-amount"]')
            .setValue('50');
        await openClaimRequirementsPopover(wrapper);
        await wrapper
            .get('[data-testid="cockpit-claim-requirement-option-selfie"]')
            .get('input[type="checkbox"]')
            .setValue(true);
        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();
        await wrapper.vm.$nextTick();

        const lastCallBody = JSON.parse(
            String(fetchMock.mock.calls.at(-1)?.[1]?.body),
        );

        expect(lastCallBody.inputs.fields).toContain('selfie');
        expect(lastCallBody.inputs.fields).not.toContain('kyc');
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-requirement-chip-kyc"]')
                .exists(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        const costLedger = wrapper.get(
            '[data-testid="cockpit-pay-code-cost-ledger"]',
        );

        expect(costLedger.text()).toContain('Selfie Photo');
        expect(costLedger.text()).not.toContain('KYC Verification');

        wrapper.unmount();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });
});
