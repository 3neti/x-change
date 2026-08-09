import { config, flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vitest';
import CockpitDiagnosticsDisclosure from '../../../resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue';
import CockpitGenerateActionPanel from '../../../resources/js/cockpit/components/CockpitGenerateActionPanel.vue';
import CockpitIssuanceBoundaryPanel from '../../../resources/js/cockpit/components/CockpitIssuanceBoundaryPanel.vue';
import CockpitPricingFundingSummary from '../../../resources/js/cockpit/components/CockpitPricingFundingSummary.vue';
import CockpitQuickGenerateAuthorizationGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateAuthorizationGatePanel.vue';
import CockpitQuickGenerateDiagnosticsSummary from '../../../resources/js/cockpit/components/CockpitQuickGenerateDiagnosticsSummary.vue';
import CockpitQuickGenerateDraftContractPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateDraftContractPanel.vue';
import CockpitQuickGenerateFundingGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateFundingGatePanel.vue';
import CockpitQuickGenerateIdempotencyGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateIdempotencyGatePanel.vue';
import CockpitQuickGenerateMutationHandoffPlanPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateMutationHandoffPlanPanel.vue';
import CockpitQuickGenerateMutationAuthorizationDecisionPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateMutationAuthorizationDecisionPanel.vue';
import CockpitQuickGenerateMutationPreconditionsReviewPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateMutationPreconditionsReviewPanel.vue';
import CockpitPayCodeCanvas from '../../../resources/js/cockpit/components/CockpitPayCodeCanvas.vue';
import CockpitQuickGeneratePricingGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGeneratePricingGatePanel.vue';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import CockpitQuickGenerateValidationRedactionGatePanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateValidationRedactionGatePanel.vue';
import CockpitRuntimeInputPanel from '../../../resources/js/cockpit/components/CockpitRuntimeInputPanel.vue';
import CockpitTemplateSelector from '../../../resources/js/cockpit/components/CockpitTemplateSelector.vue';
import QuickGenerate from '../../../resources/js/cockpit/pages/QuickGenerate.vue';
import {
    cockpitPricingFundingSummary,
    cockpitQuickGenerateTemplates,
    cockpitRuntimeInputs,
} from '../../../resources/js/cockpit/quickGenerateDefaults';
import { resolveRiderStampPreview } from '../../../resources/js/cockpit/riderStampPreview';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: {
        reload: vi.fn(),
    },
}));

config.global.stubs = {
    ...config.global.stubs,
    Teleport: true,
};

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

describe('Cockpit Quick Generate foundation', () => {
    it('keeps the Order inputs aligned and focuses Amount first', async () => {
        const host = document.createElement('div');
        document.body.appendChild(host);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: host,
            props: {
                templates: cockpitQuickGenerateTemplates,
            },
        });

        await flushPromises();

        const amountInput = wrapper.get<HTMLInputElement>(
            '[data-testid="cockpit-quick-generate-primary-amount"]',
        );
        const recipientInput = wrapper.get<HTMLInputElement>(
            '[data-testid="cockpit-quick-generate-primary-recipient"]',
        );
        const essentialsCanvas = wrapper.get(
            '[data-testid="cockpit-quick-generate-essentials-canvas"]',
        );

        expect(amountInput.element).toBe(document.activeElement);
        expect(amountInput.classes()).toContain('h-12');
        expect(recipientInput.classes()).toContain('h-12');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-amount-field"]')
                .classes(),
        ).toContain('gap-1.5');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-recipient-field"]')
                .classes(),
        ).toContain('gap-1.5');
        expect(essentialsCanvas.classes()).toContain(
            'xl:grid-cols-[minmax(19rem,0.74fr)_minmax(28rem,1.26fr)]',
        );

        wrapper.unmount();
        host.remove();
    });

    it('restores immediate keyboard amount entry after every template path', async () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: host,
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
                savedTemplates: [
                    {
                        reference: 'template:test:payroll',
                        name: 'Payroll Test',
                        base_template_key: 'money-changer',
                        instructions: {
                            cash: { amount: 125, currency: 'PHP' },
                            inputs: { fields: ['mobile'] },
                        },
                        include_amount: true,
                        include_purpose: false,
                    },
                ],
            },
        });

        await flushPromises();

        const assertFocusedKeyboardEntry = async (
            trigger: () => Promise<unknown>,
            key: string,
        ): Promise<void> => {
            await trigger();
            await flushPromises();

            const amountInput = wrapper.get(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            );

            expect(document.activeElement).toBe(amountInput.element);
            await amountInput.trigger('keydown', { key });
            await flushPromises();
            expect(
                wrapper.get('[data-testid="numeric-keypad-display"]').text(),
            ).toContain(`₱${key}`);

            window.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Escape' }),
            );
            await flushPromises();
        };

        await assertFocusedKeyboardEntry(
            () =>
                wrapper
                    .get('[data-testid="cockpit-quick-generate-start-blank"]')
                    .trigger('click'),
            '2',
        );
        await assertFocusedKeyboardEntry(
            () =>
                wrapper
                    .get('[data-testid="cockpit-quick-generate-repeat-last"]')
                    .trigger('click'),
            '3',
        );
        await assertFocusedKeyboardEntry(async () => {
            await wrapper
                .get('[data-testid="cockpit-quick-generate-choose-template"]')
                .trigger('click');
            await wrapper
                .findAll(
                    '[data-testid="cockpit-quick-generate-template-option"]',
                )[1]
                .trigger('click');
        }, '4');
        await assertFocusedKeyboardEntry(async () => {
            await wrapper
                .get('[data-testid="cockpit-quick-generate-choose-template"]')
                .trigger('click');
            await wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-saved-template-option"]',
                )
                .trigger('click');
        }, '5');

        wrapper.unmount();
        host.remove();
    });

    it('disables unavailable evidence while allowing an incompatible saved design to remove it', async () => {
        const capability = {
            key: 'location',
            label: 'Location',
            status: 'unavailable' as const,
            issuance_allowed: false,
            claim_retryable: true,
            reason: 'Location services are not configured.',
            missing_configuration: ['MAPBOX_TOKEN'],
            source: 'form-handler-location',
        };
        const baseProps = {
            templates: cockpitQuickGenerateTemplates,
            mutationContract: {
                runtime_enabled: true,
                route: 'x-change.cockpit.quick-generate.store',
                route_url: '/x/cockpit/quick-generate',
                allowed_methods: ['POST'],
            },
            instructionCapabilities: { location: capability },
        };
        const blank = mount(CockpitQuickGenerateSubmitPanel, {
            props: baseProps,
        });

        expect(
            blank.get('input[value="location"]').attributes('disabled'),
        ).toBeDefined();

        const saved = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                ...baseProps,
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-04T00:00:00Z',
                    instructions: {
                        cash: { amount: 25, currency: 'PHP' },
                        inputs: { fields: ['location'] },
                    },
                },
            },
        });

        await flushPromises();

        expect(saved.get('input[value="location"]').element).toMatchObject({
            checked: true,
            disabled: false,
        });
        expect(
            saved
                .get(
                    '[data-testid="cockpit-quick-generate-capability-warning"]',
                )
                .text(),
        ).toContain('Location services are not configured.');
        expect(
            saved
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('brands a blank Pay Code canvas without inventing claim details', () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '',
                currency: 'PHP',
                recipient: '',
                purpose: '',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-logo"]')
                .attributes('src'),
        ).toBe('/vendor/x-change/images/pay-code/pay-code-mark.svg');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-logo"]')
                .attributes('alt'),
        ).toBe('Pay Code mark');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-tagline"]')
                .text(),
        ).toContain('The instruction that moves money.');
        expect(wrapper.text()).toContain('PAY CODE PREVIEW');
        expect(wrapper.find('canvas').exists()).toBe(false);
    });

    it('keeps the Pay Code preview brand while the Pay Code is being designed', () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '50',
                currency: 'PHP',
                recipient: '',
                purpose: '',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-logo"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-tagline"]')
                .text(),
        ).toContain('The instruction that moves money.');
        expect(wrapper.text()).toContain('₱50.00');
        expect(wrapper.text()).not.toContain('Digital Pay Code');
        expect(wrapper.text()).not.toContain('Editable Pay Code Preview');
        expect(wrapper.text()).not.toContain(
            'Changes appear here before issuance.',
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-front-button"]')
                .text(),
        ).toBe('Stamp');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-back-button"]')
                .text(),
        ).toBe('Cost');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-header"]')
                .get('p')
                .text(),
        ).toBe(
            'Preview its Stamp, shape its design, walk through its claim, or inspect cost.',
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-view-switch"]')
                .attributes('aria-label'),
        ).toBe('Pay Code view');
        expect(
            wrapper
                .html()
                .indexOf('data-testid="cockpit-pay-code-canvas-header"'),
        ).toBeLessThan(
            wrapper
                .html()
                .indexOf('data-testid="cockpit-pay-code-canvas-front"'),
        );
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-controls"]')
                .exists(),
        ).toBe(false);
    });

    it('adds an accessible Design view only to the editable canvas', async () => {
        const props = {
            amount: '50',
            currency: 'PHP',
            recipient: '',
            purpose: '',
            claimOutcome: 'provider_disbursement' as const,
            voucherType: 'redeemable' as const,
        };
        const wrapper = mount(CockpitPayCodeCanvas, {
            props,
            slots: {
                design: '<div data-testid="rider-design-editor">Rider editor</div>',
            },
        });
        const tabList = wrapper.get(
            '[data-testid="cockpit-pay-code-canvas-view-switch"]',
        );

        expect(tabList.attributes('role')).toBe('tablist');
        expect(tabList.findAll('[role="tab"]')).toHaveLength(3);
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-front-button"]')
                .attributes('aria-selected'),
        ).toBe('true');

        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-design-button"]')
            .trigger('click');

        const designPanel = wrapper.get(
            '[data-testid="cockpit-pay-code-canvas-design"]',
        );

        expect(designPanel.attributes('role')).toBe('tabpanel');
        expect(designPanel.classes()).toContain('w-full');
        expect(designPanel.text()).toContain('Rider editor');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-design-button"]')
                .attributes('aria-selected'),
        ).toBe('true');

        const finalized = mount(CockpitPayCodeCanvas, {
            props: {
                ...props,
                presentation: 'finalized',
            },
            slots: {
                design: '<div>Rider editor</div>',
            },
        });

        expect(
            finalized
                .find('[data-testid="cockpit-pay-code-canvas-design-button"]')
                .exists(),
        ).toBe(false);
        expect(
            finalized
                .get('[data-testid="cockpit-pay-code-canvas-view-switch"]')
                .findAll('[role="tab"]'),
        ).toHaveLength(2);
    });

    it('keeps all safe Rider Splash supporting copy on the canvas', () => {
        const riderStamp = resolveRiderStampPreview({
            source: 'splash',
            splashBody: `
                <h2>i carry your heart with me</h2>
                <p>(i carry it in my heart)</p>
                <p>🤝 &nbsp; ❤️ &nbsp; ✌️ &nbsp; 🔫 &nbsp; ✈️ &nbsp; ⭐</p>
                <p>&mdash; e.e. cummings</p>
            `,
            artworkSource: 'splash',
            copySource: 'splash',
        });
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '55',
                currency: 'PHP',
                recipient: '',
                purpose: 'Snacks',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                hasRiderDesign: true,
                riderDesignSource: 'splash',
                riderStamp,
            },
        });
        const description = wrapper.get(
            '[data-testid="cockpit-pay-code-canvas-stamp-description"]',
        );

        expect(description.text()).toContain('🤝 ❤️ ✌️ 🔫 ✈️ ⭐');
        expect(description.text()).toContain('— e.e. cummings');
        expect(description.classes()).toContain('line-clamp-3');
    });

    it('shows Rider Message once and keeps the recipient on the compact audience line', async () => {
        const riderStamp = resolveRiderStampPreview({
            message: 'Snacks',
            splashBody: '<h2>A separate introduction</h2>',
            artworkSource: 'splash',
            copySource: 'automatic',
        });
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '50',
                currency: 'PHP',
                recipient: '09173011987',
                purpose: 'Snacks',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                hasRiderDesign: true,
                riderDesignSource: 'splash',
                riderStamp,
            },
        });

        expect(wrapper.text().match(/Snacks/g)).toHaveLength(1);
        expect(wrapper.text()).not.toContain(
            'Prepared with a message for the recipient.',
        );
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-purpose"]')
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('Prepared for');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-recipient"]')
                .text(),
        ).toBe('Mobile ending 1987');

        await wrapper.setProps({ recipient: 'MERALCO-BILLER' });

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-recipient"]')
                .text(),
        ).toBe('MERALCO-BILLER');
    });

    it('opens the Rider editors inside the live Design view', async () => {
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
            global: {
                stubs: {
                    Teleport: false,
                },
            },
        });

        await flushPromises();

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-rider-design-editor"]',
                )
                .find(
                    '[data-testid="cockpit-quick-generate-rider-design-tabs"]',
                )
                .exists(),
        ).toBe(true);

        await wrapper
            .get('[data-testid="cockpit-quick-generate-open-design-button"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-design-button"]')
                .attributes('aria-selected'),
        ).toBe('true');
        const designTabs = wrapper.get(
            '[data-testid="cockpit-quick-generate-rider-design-tabs"]',
        );
        const appearanceTab = designTabs.get(
            '[data-testid="cockpit-quick-generate-rider-design-appearance-tab"]',
        );

        expect(designTabs.findAll('[role="tab"]')).toHaveLength(4);
        expect(appearanceTab.attributes('aria-selected')).toBe('true');
        const appearanceLayout = wrapper.get(
            '[data-testid="cockpit-quick-generate-rider-appearance-layout"]',
        );
        const artworkInspector = appearanceLayout.get(
            '[data-testid="cockpit-quick-generate-rider-artwork-inspector"]',
        );
        const artworkSource = appearanceLayout.get(
            '[data-testid="cockpit-quick-generate-rider-stamp-source"]',
        );
        const artworkRadios = artworkSource.findAll('input[type="radio"]');

        expect(artworkInspector.classes()).toContain('static');
        expect(artworkInspector.classes()).not.toContain('lg:sticky');
        expect(artworkInspector.classes()).not.toContain('lg:top-14');
        expect(artworkInspector.classes()).not.toContain('hidden');
        expect(
            appearanceLayout.findAll(
                '[data-testid="cockpit-quick-generate-rider-artwork-inspector"]',
            ),
        ).toHaveLength(1);
        expect(
            appearanceLayout
                .html()
                .indexOf(
                    'data-testid="cockpit-quick-generate-rider-stamp-source"',
                ),
        ).toBeLessThan(
            appearanceLayout
                .html()
                .indexOf(
                    'data-testid="cockpit-quick-generate-rider-artwork-inspector"',
                ),
        );
        expect(artworkRadios).toHaveLength(4);
        expect(
            artworkRadios.map((radio) => radio.attributes('aria-label')),
        ).toEqual(['x-change', 'Rider Link', 'Rider Splash', 'None']);
        expect(
            artworkSource.findAll(
                '[data-testid="cockpit-rider-artwork-thumbnail"]',
            ),
        ).toHaveLength(4);
        expect(artworkSource.find('iframe').exists()).toBe(false);
        expect(
            artworkSource.findAll(
                '[data-testid="cockpit-quick-generate-rider-artwork-selected"]',
            ),
        ).toHaveLength(1);
        const artworkTooltips = artworkSource.findAll('[role="tooltip"]');

        expect(artworkTooltips).toHaveLength(4);
        expect(
            artworkTooltips.every(
                (tooltip) =>
                    tooltip.classes().includes('group-hover:opacity-100') &&
                    tooltip
                        .classes()
                        .includes('group-focus-within:opacity-100'),
            ),
        ).toBe(true);
        expect(artworkSource.get('legend').classes()).toContain('sr-only');
        expect(artworkSource.get('.grid').classes()).toContain('grid-cols-4');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-rider-message-editor"]',
                )
                .text(),
        ).toContain('Rider Message');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-rider-cta-section"]')
                .text(),
        ).toContain('Rider URL');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-rider-splash-builder"]',
                )
                .text(),
        ).toContain('Rider Splash');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-rider-stamp-editor"]',
                )
                .text(),
        ).toContain('Stamp Appearance');
        const designEditor = wrapper.get(
            '[data-testid="cockpit-quick-generate-rider-design-editor"]',
        );
        const riderBehavior = wrapper.get(
            '[data-testid="cockpit-quick-generate-rider-behavior"]',
        );

        expect(
            designEditor
                .find(
                    '[data-testid="cockpit-quick-generate-rider-redirect-timeout"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            designEditor
                .find(
                    '[data-testid="cockpit-quick-generate-rider-splash-timeout"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            riderBehavior
                .find(
                    '[data-testid="cockpit-quick-generate-rider-redirect-timeout"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            riderBehavior
                .find(
                    '[data-testid="cockpit-quick-generate-rider-splash-timeout"]',
                )
                .exists(),
        ).toBe(true);

        await designTabs
            .get(
                '[data-testid="cockpit-quick-generate-rider-design-message-tab"]',
            )
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-rider-design-appearance-tab"]',
                )
                .attributes('aria-selected'),
        ).toBe('false');
        expect(
            wrapper
                .get('[data-testid="cockpit-rider-message-format"]')
                .findAll('input[type="radio"]'),
        ).toHaveLength(2);
        expect(wrapper.get('#quick-generate-contract-rider').text()).toContain(
            'Optional message, link, artwork, and Stamp presentation.',
        );

        wrapper.unmount();
    });

    it('keeps the selected Pay Code kind visible in the Order', async () => {
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
        const kind = wrapper.get(
            '[data-testid="cockpit-quick-generate-voucher-kind"]',
        );
        const type = wrapper.get(
            '[data-testid="cockpit-quick-generate-voucher-type"]',
        );

        expect(kind.text()).toBe('Disburseable');
        expect(kind.classes()).toContain('font-semibold');
        expect(kind.classes()).toContain('normal-case');
        expect(kind.classes()).not.toContain('uppercase');

        await type.setValue('payable');
        expect(kind.text()).toBe('Payable');

        await type.setValue('settlement');
        expect(kind.text()).toBe('Settlement');
    });

    it('shows the authoritative estimated cost beneath the Pay Code amount', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .fn()
            .mockImplementation((_url: string, options: RequestInit) => {
                const request = JSON.parse(String(options.body));
                const payCodeValue = Number(request.cash.amount);

                return Promise.resolve({
                    ok: true,
                    json: vi.fn().mockResolvedValue({
                        success: true,
                        data: {
                            currency: 'PHP',
                            base_fee: 12,
                            components: {
                                selfie: 5,
                            },
                            charges: [
                                {
                                    label: 'Pay Code Generation',
                                    price: 12,
                                    currency: 'PHP',
                                },
                                {
                                    label: 'Selfie Verification',
                                    price: 5,
                                    currency: 'PHP',
                                },
                            ],
                            total: 17,
                            pay_code_value: payCodeValue,
                            account_debit: payCodeValue + 17,
                        },
                    }),
                });
            });
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                clientFundsMinor: 6000,
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
        });
        const debit = wrapper.get(
            '[data-testid="cockpit-quick-generate-account-debit"]',
        );

        expect(debit.text()).toContain('Estimated Cost');
        expect(debit.classes()).not.toContain('rounded-lg');
        expect(debit.classes()).not.toContain('bg-slate-50/90');
        expect(debit.classes()).not.toContain('ring-1');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-amount"]')
            .setValue('50');

        expect(
            debit
                .find(
                    '[data-testid="cockpit-quick-generate-account-debit-loading"]',
                )
                .exists(),
        ).toBe(true);

        await vi.advanceTimersByTimeAsync(501);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/x/v1/pay-codes/estimate',
            expect.objectContaining({
                method: 'POST',
            }),
        );
        expect(
            debit
                .get(
                    '[data-testid="cockpit-quick-generate-account-debit-amount"]',
                )
                .text(),
        ).toBe('₱67.00');
        expect(debit.attributes('data-affordability')).toBe(
            'insufficient-client-funds',
        );
        expect(
            debit
                .get(
                    '[data-testid="cockpit-quick-generate-account-debit-view-cost"]',
                )
                .classes(),
        ).toContain('text-rose-600');
        expect(
            debit
                .get(
                    '[data-testid="cockpit-quick-generate-account-debit-amount"]',
                )
                .classes(),
        ).toContain('text-rose-600');

        await wrapper.setProps({
            clientFundsMinor: 6700,
        });

        expect(debit.attributes('data-affordability')).toBe('affordable');
        expect(
            debit
                .get(
                    '[data-testid="cockpit-quick-generate-account-debit-amount"]',
                )
                .classes(),
        ).not.toContain('text-rose-600');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-amount"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="numeric-keypad-quick-100"]')
            .trigger('click');

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-amount-picker-estimated-cost-loading"]',
                )
                .text(),
        ).toBe('Calculating…');

        await vi.advanceTimersByTimeAsync(501);
        await flushPromises();

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-amount-picker-estimated-cost-value"]',
                )
                .text(),
        ).toBe('₱117.00');
        expect(
            JSON.parse(String(fetchMock.mock.calls.at(-1)?.[1]?.body)).cash
                .amount,
        ).toBe(100);
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('50.00');

        await wrapper
            .get('[data-testid="numeric-keypad-cancel"]')
            .trigger('click');

        await vi.advanceTimersByTimeAsync(501);
        await flushPromises();

        expect(
            debit
                .get(
                    '[data-testid="cockpit-quick-generate-account-debit-amount"]',
                )
                .text(),
        ).toBe('₱67.00');
        expect(
            debit
                .find(
                    '[data-testid="cockpit-quick-generate-account-debit-breakdown"]',
                )
                .exists(),
        ).toBe(false);
        expect(debit.text()).not.toContain('Pay Code value');
        expect(debit.text()).not.toContain('Deducted from Client Funds');

        await debit
            .get(
                '[data-testid="cockpit-quick-generate-account-debit-view-cost"]',
            )
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-back-button"]')
                .attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-subtotal"]')
                .text(),
        ).toBe('₱17.00');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-pay-code-value"]')
                .text(),
        ).toBe('₱50.00');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-cost-total"]').text(),
        ).toBe('₱67.00');

        wrapper.unmount();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('renders only the server-issued canonical claim QR on a finalized canvas', () => {
        const claimQr = 'data:image/png;base64,aXNzdWVkLWNsYWltLXFy';
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '50',
                currency: 'PHP',
                recipient: '',
                purpose: 'Family support',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                issuedCode: 'PAY-QR-1',
                presentation: 'finalized',
                riderStamp: resolveRiderStampPreview({
                    message: 'Family support',
                    claimMarker: 'both',
                    claimMarkerPosition: 'bottom_right',
                }),
                claimQr,
            },
        });

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-claim-qr"]')
                .attributes('src'),
        ).toBe(claimQr);
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-claim-qr"]')
                .attributes('alt'),
        ).toBe('Claim PAY-QR-1');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-claim-code"]')
                .text(),
        ).toBe('PAY-QR-1');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-pay-code-canvas-claim-qr-placeholder"]',
                )
                .exists(),
        ).toBe(false);
    });

    it('renders a live front and back Pay Code canvas without a fake claim QR', async () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '1250',
                currency: 'PHP',
                recipient: '09173011987',
                purpose: 'Family support',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                expiry: '1 day',
                instructionKeys: ['validation.mobile', 'validation.otp'],
                hasRiderDesign: true,
                riderDesignSource: 'message',
                riderDesignDocument:
                    '<!doctype html><html><body><h1>Family support</h1></body></html>',
                costEstimate: {
                    currency: 'PHP',
                    charges: [
                        {
                            catalog_item_reference: 'cash.amount',
                            label: 'Pay Code Generation',
                            price: 12,
                        },
                        {
                            catalog_item_reference: 'inputs.fields.selfie',
                            label: 'Selfie Verification',
                            price: 5,
                        },
                    ],
                    total: 17,
                },
            },
        });

        expect(wrapper.text()).toContain('₱1,250.00');
        expect(wrapper.text()).toContain('Mobile ending 1987');
        expect(wrapper.text()).toContain('PAY CODE PREVIEW');
        const stampIndicators = wrapper.findAll(
            '[data-testid="cockpit-pay-code-stamp-indicators"] [data-testid="cockpit-pay-code-indicator"]',
        );

        expect(stampIndicators).toHaveLength(3);
        expect(
            stampIndicators.map((indicator) =>
                indicator.attributes('data-indicator-key'),
            ),
        ).toEqual([
            'outcome.provider_disbursement',
            'validation.mobile',
            'validation.otp',
        ]);
        expect(
            stampIndicators.map((indicator) =>
                indicator.get('[role="img"]').attributes('aria-label'),
            ),
        ).toEqual(['Receive Funds', 'Mobile Restriction', 'OTP Required']);
        expect(
            stampIndicators.map((indicator) =>
                indicator.get('[role="tooltip"]').text(),
            ),
        ).toEqual([
            'The recipient can receive the Pay Code value.',
            'Only the intended mobile number can claim.',
            'A one-time passcode is required to claim.',
        ]);
        const design = wrapper.find(
            '[data-testid="cockpit-pay-code-canvas-rider-og-design"]',
        );

        expect(design.exists()).toBe(true);
        expect(design.attributes('sandbox')).toBe('');
        expect(design.classes()).toContain('opacity-60');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-scrim"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-purpose"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-tagline"]')
                .classes(),
        ).toContain('text-white/80');

        await wrapper.setProps({
            riderDesignSource: 'splash',
        });

        expect(design.classes()).toContain('opacity-100');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-scrim"]')
                .classes(),
        ).toContain('to-transparent');
        expect(design.attributes('class')).not.toContain('opacity-45');
        expect(design.attributes('srcdoc')).toContain('Family support');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-front"]')
                .classes(),
        ).not.toContain('bg-[#fffaf0]');
        expect(
            wrapper
                .find('svg[aria-label="Claim QR appears after issue"]')
                .exists(),
        ).toBe(false);

        await wrapper
            .find('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        expect(wrapper.text()).toContain('Estimated Issue Cost');
        expect(wrapper.text()).toContain('Pay Code Generation');
        expect(wrapper.text()).toContain('₱12.00');
        expect(wrapper.text()).toContain('Selfie Verification');
        expect(wrapper.text()).toContain('5.00');
        expect(wrapper.text()).toContain('Instruction Subtotal');
        expect(wrapper.text()).toContain('Pay Code Value');
        expect(wrapper.text()).toContain('Total Estimated Cost');
        expect(wrapper.text()).toContain('₱17.00');
        expect(wrapper.text()).toContain('₱1,250.00');
        expect(wrapper.text()).toContain('₱1,267.00');
        expect(wrapper.text().match(/₱/g) ?? []).toHaveLength(4);
        const costIndicators = wrapper.findAll(
            '[data-testid="cockpit-pay-code-cost-label"] [data-testid="cockpit-pay-code-indicator"]',
        );

        expect(costIndicators).toHaveLength(2);
        expect(
            costIndicators.map((indicator) =>
                indicator.attributes('data-indicator-key'),
            ),
        ).toEqual(['generation', 'input.selfie']);
        expect(
            costIndicators.map((indicator) =>
                indicator.get('[role="tooltip"]').text(),
            ),
        ).toEqual([
            'Pay Code Generation — priced instruction.',
            'Selfie Verification — priced instruction.',
        ]);
        expect(wrapper.text()).not.toContain('Back Of The Pay Code');
        expect(
            wrapper
                .find('[aria-label="Claim QR appears after issue"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-pay-code-cost-ledger-column"]',
            ),
        ).toHaveLength(1);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-cost-ledger-column"]')
                .classes(),
        ).toContain('grid-cols-[minmax(0,1fr)_auto]');
    });

    it('keeps a compact Stamp rail and explains overflowed instructions', () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '100',
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                instructionKeys: [
                    'input.mobile',
                    'input.name',
                    'input.email',
                    'validation.mobile',
                    'validation.otp',
                    'validation.identity',
                    'validation.location',
                    'claim.multiple',
                ],
            },
        });

        const indicators = wrapper.findAll(
            '[data-testid="cockpit-pay-code-stamp-indicators"] [data-testid="cockpit-pay-code-indicator"]',
        );
        const overflow = wrapper.get(
            '[data-testid="cockpit-pay-code-stamp-indicator-overflow"]',
        );

        expect(indicators).toHaveLength(6);
        expect(
            indicators.every(
                (indicator) =>
                    indicator.get('[role="img"]').attributes('tabindex') ===
                        '0' &&
                    indicator
                        .get('[role="img"]')
                        .attributes('aria-describedby') !== undefined,
            ),
        ).toBe(true);
        expect(overflow.text()).toContain('+3');
        expect(overflow.attributes('tabindex')).toBe('0');
        expect(overflow.attributes('aria-label')).toBe('3 more instructions');
        expect(overflow.get('[role="tooltip"]').text()).toBe(
            'Identity Check, Location Validation, Multiple Claims',
        );
    });

    it('splits eight priced instructions and keeps the total in the second ledger column', async () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '100',
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                costEstimate: {
                    currency: 'PHP',
                    charges: Array.from({ length: 8 }, (_, index) => ({
                        catalog_item_reference: `charge.${index + 1}`,
                        label: `Charge ${index + 1}`,
                        price: index + 1,
                    })),
                    total: 36,
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        const columns = wrapper.findAll(
            '[data-testid="cockpit-pay-code-cost-ledger-column"]',
        );

        expect(columns).toHaveLength(2);
        expect(columns[0].text()).toContain('Charge 1');
        expect(columns[0].text()).toContain('Charge 4');
        expect(columns[0].text()).not.toContain('Charge 5');
        expect(columns[0].text()).not.toContain('Instruction Subtotal');
        expect(columns[1].text()).toContain('Charge 5');
        expect(columns[1].text()).toContain('Charge 8');
        expect(columns[1].text()).not.toContain('Instruction Subtotal');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-subtotal"]')
                .text(),
        ).toBe('₱36.00');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-pay-code-value"]')
                .text(),
        ).toBe('₱100.00');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-cost-total"]').text(),
        ).toBe('₱136.00');
        expect(wrapper.text().match(/₱/g) ?? []).toHaveLength(4);
    });

    it('extends the unit issue cost when more than one Pay Code is requested', async () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '100',
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                quantity: 2,
                costEstimate: {
                    currency: 'PHP',
                    charges: [
                        {
                            label: 'Pay Code Generation',
                            price: 65.3,
                        },
                    ],
                    total: 65.3,
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        const subtotal = wrapper.get(
            '[data-testid="cockpit-pay-code-cost-subtotal"]',
        );

        expect(subtotal.text()).toBe('2 × ₱65.30 = ₱130.60');
        expect(subtotal.attributes('data-quantity')).toBe('2');
        expect(subtotal.classes()).toContain('text-[0.625rem]');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-pay-code-value"]')
                .text(),
        ).toBe('₱100.00');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-cost-total"]').text(),
        ).toBe('₱230.60');
    });

    it('splits eighteen priced instructions across three compact columns without losing the total', async () => {
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '100',
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                costEstimate: {
                    currency: 'PHP',
                    charges: Array.from({ length: 18 }, (_, index) => ({
                        catalog_item_reference: `charge.${index + 1}`,
                        label: `Charge ${index + 1}`,
                        price: index + 1,
                    })),
                    total: 171,
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        const ledger = wrapper.find(
            '[data-testid="cockpit-pay-code-cost-ledger"]',
        );
        const columns = wrapper.findAll(
            '[data-testid="cockpit-pay-code-cost-ledger-column"]',
        );

        expect(ledger.attributes('data-column-count')).toBe('3');
        expect(ledger.classes()).toContain(
            'grid-cols-[minmax(0,5fr)_minmax(0,5fr)_minmax(0,7fr)]',
        );
        expect(columns).toHaveLength(3);
        expect(columns[0].text()).toContain('Charge 1');
        expect(columns[0].text()).toContain('Charge 6');
        expect(columns[0].text()).toContain('₱1.00');
        expect(columns[0].text()).not.toContain('Instruction Subtotal');
        expect(columns[1].text()).toContain('Charge 7');
        expect(columns[1].text()).toContain('Charge 12');
        expect(columns[1].text()).not.toContain('₱');
        expect(columns[1].text()).not.toContain('Instruction Subtotal');
        expect(columns[2].text()).toContain('Charge 13');
        expect(columns[2].text()).toContain('Charge 18');
        expect(columns[2].text()).not.toContain('Instruction Subtotal');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-cost-subtotal"]')
                .text(),
        ).toBe('₱171.00');
        expect(
            wrapper.get('[data-testid="cockpit-pay-code-cost-total"]').text(),
        ).toBe('₱271.00');
        expect(wrapper.text().match(/₱/g) ?? []).toHaveLength(4);

        const labels = wrapper.findAll(
            '[data-testid="cockpit-pay-code-cost-label"]',
        );

        expect(labels[0].classes()).toContain('whitespace-nowrap');
        expect(labels[0].classes()).toContain('overflow-hidden');
        expect(
            labels[0]
                .get('[data-testid="cockpit-pay-code-cost-label-text"]')
                .classes(),
        ).toContain('truncate');
        expect(labels[0].attributes('title')).toBe('Charge 1');
    });

    it('keeps long cost descriptions to one cropped line in dense ledgers', async () => {
        const longLabel = 'Monthly Gross Income Verification';
        const wrapper = mount(CockpitPayCodeCanvas, {
            props: {
                amount: '100',
                currency: 'PHP',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                costEstimate: {
                    currency: 'PHP',
                    charges: Array.from({ length: 18 }, (_, index) => ({
                        catalog_item_reference: `long-charge.${index + 1}`,
                        label: index === 0 ? longLabel : `Charge ${index + 1}`,
                        price: index + 1,
                    })),
                    total: 171,
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        const label = wrapper
            .findAll('[data-testid="cockpit-pay-code-cost-label"]')
            .at(0);

        expect(
            label
                ?.get('[data-testid="cockpit-pay-code-cost-label-text"]')
                .text(),
        ).toBe(longLabel);
        expect(label?.attributes('title')).toBe(longLabel);
        expect(label?.classes()).toContain('whitespace-nowrap');
        expect(label?.classes()).toContain('overflow-hidden');
        expect(
            label
                ?.get('[data-testid="cockpit-pay-code-cost-label-text"]')
                .classes(),
        ).toContain('truncate');
        expect(label?.classes()).not.toContain('line-clamp-2');
        expect(label?.classes()).not.toContain('break-words');
        expect(wrapper.text()).toContain('Total');
        expect(wrapper.text()).toContain('₱171.00');
    });

    it('renders template selector placeholders as institutional products', () => {
        const wrapper = mount(CockpitTemplateSelector, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                selectedKey: 'money-changer',
            },
        });

        expect(wrapper.text()).toContain('Start from institutional products');
        expect(wrapper.text()).toContain('Money Changer');
        expect(wrapper.text()).toContain('OFW Remittance');
        expect(wrapper.text()).toContain('Settlement Envelope');
        expect(
            wrapper.findAll('[data-testid="cockpit-template-option"]'),
        ).toHaveLength(4);
    });

    it('composes Rider copy and artwork only on the live Pay Code front', async () => {
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

        await flushPromises();

        await wrapper
            .find('[data-testid="cockpit-quick-generate-rider-splash-body"]')
            .setValue('A distinct beneficiary splash');

        await wrapper
            .find('[data-testid="cockpit-quick-generate-primary-purpose"]')
            .setValue('A purpose-led Rider Stamp');

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-og-design"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-logo"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-stamp-copy"]')
                .text(),
        ).toContain('A purpose-led Rider Stamp');
        const splashPreview = wrapper.find(
            '[data-testid="cockpit-quick-generate-rider-splash-html-preview"]',
        );
        expect(splashPreview.exists()).toBe(true);
        expect(splashPreview.classes()).toContain('aspect-[1.72/1]');
        expect(splashPreview.attributes('srcdoc')).toContain(
            'A distinct beneficiary splash',
        );
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-stamp-preview"]',
                )
                .exists(),
        ).toBe(false);

        await wrapper
            .find(
                '[data-testid="cockpit-quick-generate-rider-stamp-source-splash"]',
            )
            .setValue();

        const design = wrapper.find(
            '[data-testid="cockpit-pay-code-canvas-rider-og-design"]',
        );

        expect(design.exists()).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-stamp-preview"]',
                )
                .exists(),
        ).toBe(true);
        expect(design.attributes('srcdoc')).toContain('stamp-abstract-splash');
        expect(design.attributes('srcdoc')).not.toContain(
            'A purpose-led Rider Stamp',
        );
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-stamp-copy"]')
                .text(),
        ).toContain('A purpose-led Rider Stamp');
    });

    it('promotes the first Rider Splash entered on a blank Pay Code to Stamp artwork', async () => {
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
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-rider-splash-format"]')
            .get('input[value="html"]')
            .setValue();
        await wrapper
            .get('[data-testid="cockpit-quick-generate-rider-splash-body"]')
            .setValue(
                '<img src="https://i.imgur.com/L76d0pN.jpeg" alt="Failure of Simultaneity" />',
            );

        expect(
            (
                wrapper.get(
                    '[data-testid="cockpit-quick-generate-rider-stamp-source"] input:checked',
                ).element as HTMLInputElement
            ).value,
        ).toBe('splash');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-rider-og-design"]')
                .attributes('srcdoc'),
        ).toContain('https://i.imgur.com/L76d0pN.jpeg');
    });

    it('preserves an explicit blank Pay Code artwork choice when Splash content is added', async () => {
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
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');
        await wrapper
            .get(
                '[data-testid="cockpit-quick-generate-rider-stamp-source-none"]',
            )
            .setValue();
        await wrapper
            .get(
                '[data-testid="cockpit-quick-generate-rider-stamp-source-x_change"]',
            )
            .setValue();
        await wrapper
            .get('[data-testid="cockpit-quick-generate-rider-splash-body"]')
            .setValue('Introduction only');

        expect(
            (
                wrapper.get(
                    '[data-testid="cockpit-quick-generate-rider-stamp-source"] input:checked',
                ).element as HTMLInputElement
            ).value,
        ).toBe('x_change');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-og-design"]')
                .exists(),
        ).toBe(false);
    });

    it('loads Spotify artwork when action link artwork is selected', async () => {
        vi.useFakeTimers();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                schema: 'x-change.cockpit.rider-artwork-preview.v1',
                available: true,
                source: 'spotify',
                title: 'An Example Track',
                description: 'Spotify',
                image_url: 'https://i.scdn.co/image/example-artwork',
                reference: 'Spotify',
            }),
        });
        vi.stubGlobal('fetch', fetchMock);

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

        await flushPromises();

        await wrapper
            .find('[data-testid="cockpit-quick-generate-rider-url"]')
            .setValue(
                'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking',
            );
        await wrapper
            .find(
                '[data-testid="cockpit-quick-generate-rider-stamp-source-url"]',
            )
            .setValue();
        await vi.advanceTimersByTimeAsync(351);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/x/cockpit/quick-generate/artwork-previews',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    url: 'https://open.spotify.com/track/6CKoWCWAqEVWVjpeoJXyNH?si=tracking',
                }),
            }),
        );
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-og-design"]')
                .attributes('srcdoc'),
        ).toContain('class="artwork-cover"');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-url-preview"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-url-artwork-preview"]',
                )
                .attributes('srcdoc'),
        ).toContain('class="artwork-contain"');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-url-preview-status"]',
                )
                .text(),
        ).toContain('Spotify artwork ready.');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-stamp-preview"]',
                )
                .exists(),
        ).toBe(true);

        await wrapper
            .find(
                '[data-testid="cockpit-quick-generate-rider-stamp-fit-contain"]',
            )
            .setValue();

        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-rider-og-design"]')
                .attributes('srcdoc'),
        ).toContain('class="artwork-contain"');
        expect(
            wrapper
                .find('[data-testid="cockpit-pay-code-canvas-stamp-copy"]')
                .text(),
        ).toContain('An Example Track');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-rider-artwork-status"]',
                )
                .text(),
        ).toContain('Spotify artwork ready.');

        wrapper.unmount();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('renders runtime inputs as reference facts without a submit form', () => {
        const wrapper = mount(CockpitRuntimeInputPanel, {
            props: {
                inputs: cockpitRuntimeInputs,
            },
        });

        expect(wrapper.text()).toContain('Operator input reference');
        expect(wrapper.text()).toContain('Amount');
        expect(wrapper.text()).toContain('Recipient');
        expect(wrapper.text()).toContain('Purpose');
        expect(wrapper.text()).toContain('Use the Quick Generate form');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper.findAll('[data-testid="cockpit-runtime-input"]'),
        ).toHaveLength(3);
    });

    it('renders pricing and funding summaries without calculating or reserving funds', () => {
        const wrapper = mount(CockpitPricingFundingSummary, {
            props: {
                summaries: cockpitPricingFundingSummary,
            },
        });

        expect(wrapper.text()).toContain('Pricing Estimate');
        expect(wrapper.text()).toContain('Shown after submit');
        expect(wrapper.text()).toContain('Funding Impact');
        expect(wrapper.text()).toContain('Existing handoff');
        expect(wrapper.text()).toContain('operator-safe funding preflight');
        expect(
            wrapper.findAll('[data-testid="cockpit-pricing-summary-item"]'),
        ).toHaveLength(3);
    });

    it('shows the generate action as an informational existing handoff status panel', async () => {
        const wrapper = mount(CockpitGenerateActionPanel, {
            props: {
                enabled: false,
                runtimeEnabled: true,
            },
        });

        expect(
            wrapper.find('[data-testid="cockpit-generate-button"]').exists(),
        ).toBe(false);
        const panel = wrapper.find(
            '[data-testid="cockpit-generate-action-panel"]',
        );

        expect(panel.element.tagName.toLowerCase()).toBe('details');
        expect(panel.attributes('open')).toBeUndefined();
        expect(panel.classes()).toContain('py-3');
        expect(panel.find('summary').text()).toContain('4 safeguards');
        expect(wrapper.text()).toContain('Issuance handoff status');
        expect(wrapper.text()).toContain(
            'Quick Generate uses the existing GeneratePayCode path',
        );
        expect(wrapper.text()).toContain(
            'The form above is the only operator submit control',
        );
        expect(wrapper.text()).toContain(
            'Issuance owner remains GeneratePayCode',
        );
        expect(wrapper.text()).toContain(
            'Result panel is the primary operator feedback',
        );
        expect(wrapper.text()).toContain(
            'Journal, action, and feedback handoffs remain separately gated',
        );

        expect(wrapper.emitted()).toEqual({});
    });

    it('demotes historical gate panels behind a diagnostics disclosure', () => {
        const wrapper = mount(CockpitDiagnosticsDisclosure, {
            props: {
                title: 'Engineering diagnostics',
                summary: 'A compact readiness summary is shown first.',
                eyebrow: 'Optional diagnostics',
                actionLabel: 'Show diagnostics',
            },
            slots: {
                default:
                    '<div data-testid="diagnostic-slot">Full architecture history</div>',
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-diagnostics-disclosure"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Optional diagnostics');
        expect(wrapper.text()).toContain('Engineering diagnostics');
        expect(wrapper.text()).toContain('Show diagnostics');
        expect(
            wrapper.find('[data-testid="diagnostic-slot"]').text(),
        ).toContain('Full architecture history');
    });

    it('renders a compact Quick Generate diagnostics summary before full history', () => {
        const wrapper = mount(CockpitQuickGenerateDiagnosticsSummary, {
            props: {
                mutationContract: {
                    status: 'approved-handoff',
                    runtime_enabled: true,
                },
                pricingGate: {
                    status: 'runtime-informational',
                },
                fundingGate: {
                    status: 'runtime-informational',
                },
                idempotencyGate: {
                    status: 'backend-ready',
                },
                validationRedactionGate: {
                    status: 'backend-ready',
                },
                mutationHandoffPlan: {
                    status: 'backend-handoff-wired',
                },
                mutationPreconditionsReview: {
                    recommendation: 'use-existing-issuance-handoff',
                },
                mutationAuthorizationDecision: {
                    status: 'approved-handoff',
                },
                authorization: {
                    status: 'runtime-ready',
                },
            },
        });

        expect(wrapper.text()).toContain('Readiness summary');
        expect(wrapper.text()).toContain('Quick Generate handoff status');
        expect(wrapper.text()).toContain('Operator Submit');
        expect(wrapper.text()).toContain('Ready');
        expect(wrapper.text()).toContain('Pricing');
        expect(wrapper.text()).toContain('Runtime Informational');
        expect(wrapper.text()).toContain('External Effects');
        expect(wrapper.text()).toContain('Separately gated');
        expect(wrapper.text()).toContain('Use Existing Issuance Handoff');
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-diagnostics-summary-item"]',
            ),
        ).toHaveLength(8);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-diagnostics-summary"]',
                )
                .classes(),
        ).toContain('py-3');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-diagnostics-summary-grid"]',
                )
                .classes(),
        ).toContain('gap-2');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-diagnostics-summary-item"]',
                )
                .classes(),
        ).toContain('p-2.5');
        expect(wrapper.text()).toContain('8 checks');
    });

    it('can render a non-diagnostic disclosure for operator reference content', () => {
        const wrapper = mount(CockpitDiagnosticsDisclosure, {
            props: {
                title: 'Template and runtime reference',
                summary: 'Use the Quick Generate form below.',
                eyebrow: 'Reference guide',
                actionLabel: 'Show template reference',
            },
            slots: {
                default:
                    '<div data-testid="reference-slot">Template Selector</div>',
            },
        });

        expect(wrapper.text()).toContain('Reference guide');
        expect(wrapper.text()).toContain('Template and runtime reference');
        expect(wrapper.text()).toContain('Show template reference');
        expect(wrapper.text()).not.toContain('Engineering history');
        expect(wrapper.find('[data-testid="reference-slot"]').text()).toContain(
            'Template Selector',
        );
    });

    it('renders compact reference disclosures without opening them by default', () => {
        const wrapper = mount(CockpitDiagnosticsDisclosure, {
            props: {
                title: 'Template and runtime reference',
                summary: 'Use the Quick Generate form below.',
                eyebrow: 'Reference guide',
                actionLabel: 'Show template reference',
                compact: true,
            },
        });

        const disclosure = wrapper.find(
            '[data-testid="cockpit-diagnostics-disclosure"]',
        );

        expect(disclosure.element.tagName.toLowerCase()).toBe('details');
        expect(disclosure.attributes('open')).toBeUndefined();
        expect(disclosure.classes()).toContain('py-3');
        expect(disclosure.classes()).not.toContain('p-4');
    });

    it('renders the issuance boundary plan without a mutation form', () => {
        const wrapper = mount(CockpitIssuanceBoundaryPanel);

        expect(wrapper.text()).toContain('Issuance Boundary Plan');
        expect(wrapper.text()).toContain('Existing issuance action');
        expect(wrapper.text()).toContain('GeneratePayCode');
        expect(wrapper.text()).toContain('Authorization');
        expect(wrapper.text()).toContain('Pricing');
        expect(wrapper.text()).toContain('Funding');
        expect(wrapper.text()).toContain('Idempotency');
        expect(wrapper.text()).toContain('Redaction');
        expect(wrapper.text()).toContain(
            'current Quick Generate uses the approved handoff route.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-issuance-boundary-panel"]')
                .exists(),
        ).toBe(true);
    });

    it('renders the request draft contract without persistence or submission behavior', () => {
        const wrapper = mount(CockpitQuickGenerateDraftContractPanel, {
            props: {
                draftContract: {
                    schema: 'x-change.cockpit.quick-generate-draft.v1',
                    status: 'draft_only',
                    template_key: 'money-changer',
                    amount: null,
                    currency: 'PHP',
                    recipient_reference: null,
                    purpose: null,
                    idempotency_key: null,
                    redactions: {
                        payloads: 'draft-shape-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Request Draft Contract');
        expect(wrapper.text()).toContain(
            'x-change.cockpit.quick-generate-draft.v1',
        );
        expect(wrapper.text()).toContain('template_key');
        expect(wrapper.text()).toContain('money-changer');
        expect(wrapper.text()).toContain('currency');
        expect(wrapper.text()).toContain('PHP');
        expect(wrapper.text()).toContain('idempotency_key');
        expect(wrapper.text()).toContain('Pending');
        expect(wrapper.text()).toContain(
            'Drafts are local and read-only in Slice 18.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-draft-contract-panel"]',
                )
                .exists(),
        ).toBe(true);
    });

    it('submits a sanitized quick generate payload through the mutation contract route', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-UI-001',
                    links: {
                        redeem: 'https://example.test/x/claim/PC-UI-001/experience',
                        redeem_path: '/x/claim/PC-UI-001/experience',
                        cockpit_detail: '/x/cockpit/pay-codes/PC-UI-001',
                        cockpit_distribution:
                            '/x/cockpit/pay-codes/PC-UI-001/distribution',
                    },
                },
                post_issuance_navigation: {
                    schema: 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
                    status: 'available',
                    auto_redirect: false,
                    items: [
                        {
                            key: 'detail',
                            label: 'Open Cockpit detail',
                            href: '/x/cockpit/pay-codes/PC-UI-001',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                        {
                            key: 'distribution',
                            label: 'Open Distribution workspace',
                            href: '/x/cockpit/pay-codes/PC-UI-001/distribution',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                    ],
                    redactions: {
                        payloads: 'post-issuance-navigation-only',
                    },
                },
                draft: {
                    status: 'compiled',
                    factory: 'CockpitQuickGenerateDraftFactoryContract',
                    compiler: 'CockpitIssuanceDraftCompilerContract',
                },
                preflight: {
                    pricing: {
                        status: 'estimated',
                        currency: 'PHP',
                        base_fee: 1.25,
                        total: 1.75,
                        blocking: false,
                    },
                    funding: {
                        status: 'checked',
                        authority: 'local_ledger',
                        sync_status: 'not_required',
                        authoritative: {
                            balance: 10000,
                            currency: 'PHP',
                        },
                    },
                },
                activity: {
                    schema: 'x-change.cockpit.operator-issuance-activity.v1',
                    status: 'recording-attempted-after-issuance',
                    presentation_only: true,
                    journal_handoff_status: 'not_wired',
                    action_handoff_status: 'not_wired',
                    feedback_handoff_status: 'not_wired',
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-1',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                draftContract: {
                    template_key: 'money-changer',
                    amount: '25',
                    currency: 'PHP',
                    recipient_reference: '',
                    purpose: '',
                },
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['GET', 'POST'],
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-amount"]')
            .setValue('99.50');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-recipient"]')
            .setValue('09173011987');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-primary-purpose"]')
            .setValue('Operator test issuance');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(fetchMock).toHaveBeenCalledTimes(1);

        const [url, options] = fetchMock.mock.calls[0];
        const payload = JSON.parse(options.body);

        expect(url).toBe('/x/cockpit/quick-generate');
        expect(options.method).toBe('POST');
        expect(options.headers['Idempotency-Key']).toBe(
            'cockpit-ui-idempotency-1',
        );
        expect(payload.provider).toBe('netbank');
        expect(payload.cash).toEqual({
            amount: 99.5,
            currency: 'PHP',
            fee_strategy: 'absorb',
            validation: {
                mobile: '09173011987',
            },
        });
        expect(payload.inputs).toEqual({
            fields: ['mobile'],
            requirements: [],
        });
        expect(payload.count).toBe(1);
        expect(payload.feedback).toEqual({
            email: null,
            mobile: '+639173011987',
            webhook: null,
        });
        expect(payload.rider).toMatchObject({
            message: 'Operator test issuance',
            message_format: 'plain',
            splash_meta: null,
        });
        expect(payload.claim).toEqual({
            outcomes: [
                {
                    key: 'provider_disbursement',
                },
            ],
            selection: 'server',
            consumption: 'one_of',
            default_outcome: 'provider_disbursement',
            onboarding: {
                mode: 'if_required',
            },
            claimant: {
                mode: 'unbound',
            },
            profile: 'voucher.claim.v1',
        });
        expect(payload.metadata.custom.cockpit).toMatchObject({
            template_key: 'money-changer',
            source: 'cockpit.quick-generate',
            slice_plan: {
                schema: 'x-change.cockpit.slice-plan.v1',
                mode: 'whole',
                cash_mode: null,
                rows: [
                    {
                        id: 'slice_1',
                        amount: 99.5,
                        description: 'Whole Amount',
                    },
                ],
            },
        });
        expect(payload.metadata.slices).toBeUndefined();
        expect(JSON.stringify(payload)).not.toContain('wallet');
        expect(JSON.stringify(payload)).not.toContain('provider_payload');
        expect(wrapper.emitted('submitSuccess')).toHaveLength(1);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('Generation complete');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('Pay Code issued');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('PC-UI-001');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('ready to copy');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('estimated');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('checked');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('recording-attempted-after-issuance');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('without sending feedback');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-next-actions"]',
                )
                .text(),
        ).toContain('Primary next step');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-next-actions"]',
                )
                .text(),
        ).toContain('Copy claim URL');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-next-actions"]',
                )
                .text(),
        ).toContain('Browser-local copy only. No delivery will be sent.');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-claim-link"]',
                )
                .attributes('href'),
        ).toBe('https://example.test/x/claim/PC-UI-001/experience');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-claim-route-source"]',
                )
                .text(),
        ).toContain('Claim experience URL');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-detail-link"]',
                )
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PC-UI-001');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-financial-readiness"]',
                )
                .text(),
        ).toContain('Pricing summary');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-financial-readiness"]',
                )
                .text(),
        ).toContain('PHP 1.75');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-financial-readiness"]',
                )
                .text(),
        ).toContain('Funding summary');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-financial-readiness"]',
                )
                .text(),
        ).toContain('PHP 10000');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-handoff-status"]',
                )
                .text(),
        ).toContain('Downstream handoff status');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-handoff-status"]',
                )
                .text(),
        ).toContain('presentation-only');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-handoff-status"]',
                )
                .text(),
        ).toContain('Journal');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-handoff-status"]',
                )
                .text(),
        ).toContain('Feedback delivery is not sent by this result card.');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-panel"]',
                )
                .text(),
        ).toContain('Beneficiary Pay Code URL');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-panel"]',
                )
                .text(),
        ).toContain('https://example.test/x/claim/PC-UI-001/experience');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-panel"]',
                )
                .text(),
        ).toContain('/x/claim/PC-UI-001/experience');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-link"]',
                )
                .attributes('href'),
        ).toBe('https://example.test/x/claim/PC-UI-001/experience');
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-result-link"]')
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PC-UI-001');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-supporting-result-details"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-supporting-result-details"]',
                )
                .text(),
        ).toContain('Supporting result details');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-supporting-result-details"]',
                )
                .text(),
        ).toContain('The primary card above is the operator workflow surface.');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-navigation-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-link-detail"]',
                )
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PC-UI-001');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-link-distribution"]',
                )
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PC-UI-001/distribution');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-navigation-panel"]',
                )
                .text(),
        ).toContain('Automatic redirect: disabled');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-navigation-panel"]',
                )
                .text(),
        ).toContain('read-only');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-runtime-preflight-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-pricing-preflight-card"]',
                )
                .text(),
        ).toContain('PHP 1.75');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-funding-preflight-card"]',
                )
                .text(),
        ).toContain('local_ledger');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-funding-preflight-card"]',
                )
                .text(),
        ).toContain('PHP 10000');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-runtime-metadata-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-draft-runtime-card"]',
                )
                .text(),
        ).toContain('compiled');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-activity-runtime-card"]',
                )
                .text(),
        ).toContain('x-change.cockpit.operator-issuance-activity.v1');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-activity-runtime-card"]',
                )
                .text(),
        ).toContain('yes');

        vi.unstubAllGlobals();
    });

    it('builds an Account Funding-only claim contract and locks payout-only controls', async () => {
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
            .find('[data-testid="cockpit-quick-generate-submit-recipient"]')
            .setValue('CASH');
        await wrapper
            .find(
                '[data-testid="cockpit-quick-generate-claim-outcome-account"]',
            )
            .setValue();
        await wrapper.vm.$nextTick();

        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.claim).toEqual({
            outcomes: [
                {
                    key: 'account_funding',
                    pricing_profile: 'account-funding-v1',
                },
            ],
            selection: 'server',
            consumption: 'one_of',
            default_outcome: 'account_funding',
            onboarding: {
                mode: 'if_required',
            },
            claimant: {
                mode: 'unbound',
            },
            profile: 'voucher.claim.v1',
        });
        expect(preview.metadata.custom.cockpit.recipient_reference).toBe(
            'CASH',
        );
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-settlement-rail"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-slice-mode-open"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.text()).toContain('No bank payout occurs');
    });

    it('hands an Account Funding Pay Code to the Funding workspace without exposing it in the URL', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'FUND-UI-001',
                    amount: 125,
                    currency: 'PHP',
                    claim: {
                        outcome: 'account_funding',
                        label: 'Account funds',
                        provider_payout: false,
                        account_funding: true,
                    },
                    links: {
                        redeem: 'https://example.test/x/claim/FUND-UI-001/experience',
                        redeem_path: '/x/claim/FUND-UI-001/experience',
                    },
                },
                post_issuance_navigation: {
                    schema: 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
                    status: 'available',
                    auto_redirect: false,
                    items: [
                        {
                            key: 'account_funding',
                            label: 'Open Account Funding',
                            href: '/x/cockpit/funding?mode=pay_code',
                            status: 'available',
                            enabled: true,
                            read_only: false,
                        },
                    ],
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-funding',
        });

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
            .find('[data-testid="cockpit-quick-generate-submit-recipient"]')
            .setValue('CASH');
        await wrapper
            .find(
                '[data-testid="cockpit-quick-generate-claim-outcome-account"]',
            )
            .setValue();
        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-copy-funding-pay-code"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-open-account-funding"]',
                )
                .attributes('href'),
        ).toBe('/x/cockpit/funding?mode=pay_code');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-claim-link"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('reserved for Account Funding');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .text(),
        ).toContain('Claim path');

        vi.unstubAllGlobals();
    });

    it('renders server validation errors as a structured correction panel', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            json: vi.fn().mockResolvedValue({
                message: 'The given data was invalid.',
                errors: {
                    'cash.amount': ['The amount must be at least 1.'],
                    'feedback.email': ['Enter a valid email address.'],
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-validation',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                draftContract: {
                    template_key: 'money-changer',
                    amount: '25',
                    currency: 'PHP',
                    recipient_reference: '',
                    purpose: '',
                },
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['GET', 'POST'],
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        const errors = wrapper.find(
            '[data-testid="cockpit-quick-generate-submission-errors"]',
        );

        expect(errors.exists()).toBe(true);
        expect(errors.text()).toContain('Fix these fields before issuing');
        expect(errors.text()).toContain('Cash Amount');
        expect(errors.text()).toContain('The amount must be at least 1.');
        expect(errors.text()).toContain('Feedback Email');
        expect(errors.text()).toContain('Enter a valid email address.');
        expect(wrapper.text()).toContain(
            'Your Pay Code needs a few corrections before it can be issued.',
        );
        expect(wrapper.emitted('submitError')).toHaveLength(1);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-productized-result-card"]',
                )
                .exists(),
        ).toBe(false);

        vi.unstubAllGlobals();
    });

    it('presents database contention as retryable issuance state without raw details', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            json: vi.fn().mockResolvedValue({
                success: false,
                code: 'PAY_CODE_ISSUANCE_BUSY',
                message:
                    'Pay Code issuance is temporarily busy. No Pay Code was issued. Please try again.',
                errors: {
                    submission: [
                        'Pay Code issuance is temporarily busy. No Pay Code was issued. Please try again.',
                    ],
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-busy',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                draftContract: {
                    template_key: 'money-changer',
                    amount: '25',
                    currency: 'PHP',
                    recipient_reference: '',
                    purpose: '',
                },
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['GET', 'POST'],
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await flushPromises();

        const errors = wrapper.get(
            '[data-testid="cockpit-quick-generate-submission-errors"]',
        );

        expect(errors.text()).toContain('Issuance is temporarily busy');
        expect(errors.text()).toContain('No Pay Code was issued');
        expect(errors.text()).not.toContain('SQLSTATE');
        expect(wrapper.text()).toContain(
            'No Pay Code was issued or charged. Please try again.',
        );

        vi.unstubAllGlobals();
    });

    it('keeps one instruction hierarchy with a secondary engineering preview', () => {
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

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-contract-builder-checklist"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-voucher-instruction-summary"]')
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('Design status');
        expect(wrapper.text()).not.toContain('Review your Pay Code');
        expect(wrapper.find('#quick-generate-contract-money').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('#quick-generate-contract-execution').exists(),
        ).toBe(true);
        const engineeringPreview = wrapper.find(
            '[data-testid="cockpit-quick-generate-engineering-preview"]',
        );
        expect(engineeringPreview.exists()).toBe(true);
        expect(engineeringPreview.attributes('open')).toBeUndefined();
        expect(engineeringPreview.text()).toContain('Engineering Preview');
    });

    it('reflects fixed slice-builder rows in the engineering preview without submitting named metadata', async () => {
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
            .find('[data-testid="cockpit-quick-generate-submit-amount"]')
            .setValue('100');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-slice-mode-fixed"]')
            .trigger('click');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-fixed-slices"]')
            .setValue('4');
        await wrapper.vm.$nextTick();

        const preview = quickGenerateEngineeringPreview(wrapper);
        const slicePlan = preview.metadata.custom.cockpit.slice_plan;

        expect(preview.cash).toMatchObject({
            amount: 100,
            currency: 'PHP',
            slice_mode: 'fixed',
            slices: 4,
        });
        expect(preview.metadata.slices).toBeUndefined();
        expect(slicePlan).toMatchObject({
            schema: 'x-change.cockpit.slice-plan.v1',
            mode: 'fixed',
            cash_mode: 'fixed',
            currency: 'PHP',
            total_amount: 100,
            row_total: 100,
            remaining: 0,
            max_claims: 4,
            min_withdrawal: 25,
            effective_minimum: 25,
            policy_source: 'issuer default',
            validation_message: null,
        });
        expect(slicePlan.rows).toEqual([
            { id: 'slice_1', amount: 25, description: 'Slice 1' },
            { id: 'slice_2', amount: 25, description: 'Slice 2' },
            { id: 'slice_3', amount: 25, description: 'Slice 3' },
            { id: 'slice_4', amount: 25, description: 'Slice 4' },
        ]);
    });

    it('switches customized slice rows to named metadata and keeps preview reactive', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-NAMED-001',
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-named',
        });

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
            .find('[data-testid="cockpit-quick-generate-submit-amount"]')
            .setValue('100');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-slice-mode-fixed"]')
            .trigger('click');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-fixed-slices"]')
            .setValue('4');
        await wrapper
            .find(
                '[data-testid="cockpit-quick-generate-named-slice-0-description"]',
            )
            .setValue('Transport fare');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-named-slice-0-tag"]')
            .setValue('transport');
        await wrapper.vm.$nextTick();

        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.cash).toMatchObject({
            amount: 100,
            currency: 'PHP',
            slice_mode: 'open',
            max_slices: 4,
            min_withdrawal: 25,
        });
        expect(preview.metadata.slices[0]).toEqual({
            id: 'slice_1',
            amount: 25,
            description: 'Transport fare',
            tag: 'transport',
            claim_on: null,
            claim_by: null,
        });
        expect(preview.metadata.custom.cockpit.slice_plan).toMatchObject({
            mode: 'named',
            cash_mode: 'open',
            max_claims: 4,
            row_total: 100,
            remaining: 0,
        });
        expect(preview.metadata.custom.cockpit.slice_plan.rows[0]).toEqual({
            id: 'slice_1',
            amount: 25,
            description: 'Transport fare',
            tag: 'transport',
        });

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();

        const [, options] = fetchMock.mock.calls[0];
        const payload = JSON.parse(options.body);

        expect(payload.cash).toMatchObject({
            slice_mode: 'open',
            max_slices: 4,
            min_withdrawal: 25,
        });
        expect(payload.metadata.slices[0]).toMatchObject({
            id: 'slice_1',
            amount: 25,
            description: 'Transport fare',
            tag: 'transport',
        });
        expect(payload.metadata.custom.cockpit.slice_plan.rows[0]).toEqual({
            id: 'slice_1',
            amount: 25,
            description: 'Transport fare',
            tag: 'transport',
        });

        vi.unstubAllGlobals();
    });

    it('updates open slice preview rows and does not submit executable named slices', async () => {
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
            .find('[data-testid="cockpit-quick-generate-submit-amount"]')
            .setValue('100');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-slice-mode-open"]')
            .trigger('click');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-max-slices"]')
            .setValue('3');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-min-withdrawal"]')
            .setValue('30');
        await wrapper.vm.$nextTick();

        const preview = quickGenerateEngineeringPreview(wrapper);

        expect(preview.cash).toMatchObject({
            amount: 100,
            currency: 'PHP',
            slice_mode: 'open',
            max_slices: 3,
            min_withdrawal: 30,
        });
        expect(preview.metadata.slices).toBeUndefined();
        expect(preview.metadata.slice_policy).toEqual({
            mode: 'open',
            selection: 'operator',
            enforced: false,
        });
        expect(preview.metadata.custom.cockpit.slice_plan).toMatchObject({
            mode: 'open',
            cash_mode: 'open',
            max_claims: 3,
            min_withdrawal: 30,
        });
        expect(preview.metadata.custom.cockpit.slice_plan.rows).toEqual([
            {
                id: 'slice_1',
                amount: 100,
                description: 'Open Slice',
            },
        ]);
    });

    it('prefills Quick Generate from read-only campaign context without campaign mutation', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-CAMPAIGN-001',
                    links: {
                        redeem: 'https://example.test/r/PC-CAMPAIGN-001',
                        redeem_path: '/r/PC-CAMPAIGN-001',
                        cockpit_detail: '/x/cockpit/pay-codes/PC-CAMPAIGN-001',
                    },
                },
                campaign_attribution: {
                    schema: 'x-change.cockpit.quick-generate-campaign-attribution.v1',
                    status: 'available',
                    available: true,
                    read_only: true,
                    mutates_campaign: false,
                    planning_key: 'plan-35d',
                    execution_id: 'exec-35d',
                    campaign_id: 'campaign-35d',
                    audience_id: 'audience-35d',
                    recipient_id: 'recipient-35d',
                    source: 'campaign_cockpit',
                    generated_code: 'PC-CAMPAIGN-001',
                    template_key: 'ofw-remittance',
                    amount: '500.00',
                    currency: 'PHP',
                    recipient_reference: '09173011987',
                    purpose: 'Campaign payout',
                },
                post_issuance_navigation: {
                    schema: 'x-change.cockpit.quick-generate-post-issuance-navigation.v1',
                    status: 'available',
                    auto_redirect: false,
                    items: [
                        {
                            key: 'campaign_explorer',
                            label: 'Return to Campaign Explorer',
                            href: '/x/cockpit/pay-codes?campaign_planning_key=plan-35d&campaign_execution_id=exec-35d&campaign_id=campaign-35d&campaign_audience_id=audience-35d&campaign_recipient_id=recipient-35d&campaign_source=campaign_cockpit&activity_code=PC-CAMPAIGN-001',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                        {
                            key: 'campaign_dashboard',
                            label: 'Return to Campaign Dashboard',
                            href: '/x/cockpit?campaign_planning_key=plan-35d&campaign_execution_id=exec-35d&campaign_id=campaign-35d&campaign_audience_id=audience-35d&campaign_recipient_id=recipient-35d',
                            status: 'available',
                            enabled: true,
                            read_only: true,
                        },
                    ],
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-campaign',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                draftContract: {
                    template_key: 'money-changer',
                    amount: '25',
                    currency: 'PHP',
                    recipient_reference: '',
                    purpose: '',
                },
                campaignContext: {
                    schema: 'x-change.cockpit.quick-generate-campaign-context.v1',
                    status: 'available',
                    authorized: true,
                    read_only: true,
                    mutates_campaign: false,
                    planning_key: 'plan-35d',
                    execution_id: 'exec-35d',
                    campaign_id: 'campaign-35d',
                    audience_id: 'audience-35d',
                    recipient_id: 'recipient-35d',
                    source: 'campaign_cockpit',
                    draft: {
                        template_key: 'ofw-remittance',
                        amount: '500.00',
                        currency: 'PHP',
                        recipient_reference: '09173011987',
                        purpose: 'Campaign payout',
                    },
                    redactions: {
                        payloads: 'campaign-context-prefill-only',
                    },
                },
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-context-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Campaign context prefill');
        expect(wrapper.text()).toContain('plan-35d');
        expect(wrapper.text()).toContain('exec-35d');
        expect(wrapper.text()).toContain('campaign-35d');
        expect(wrapper.text()).toContain('does not mutate campaign state');
        expect(
            (
                wrapper.find(
                    '[data-testid="cockpit-quick-generate-submit-template"]',
                ).element as HTMLSelectElement
            ).value,
        ).toBe('ofw-remittance');
        expect(
            (
                wrapper.find(
                    '[data-testid="cockpit-quick-generate-submit-amount"]',
                ).element as HTMLInputElement
            ).value,
        ).toBe('500.00');
        expect(
            (
                wrapper.find(
                    '[data-testid="cockpit-quick-generate-submit-recipient"]',
                ).element as HTMLInputElement
            ).value,
        ).toBe('09173011987');
        expect(
            (
                wrapper.find(
                    '[data-testid="cockpit-quick-generate-primary-purpose"]',
                ).element as HTMLInputElement
            ).value,
        ).toBe('Campaign payout');

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();

        const [, options] = fetchMock.mock.calls[0];
        const payload = JSON.parse(options.body);

        expect(payload.provider).toBe('paynamics');
        expect(payload.cash).toEqual({
            amount: 500,
            currency: 'PHP',
            fee_strategy: 'absorb',
            validation: {
                mobile: '09173011987',
            },
        });
        expect(payload.inputs).toEqual({
            fields: ['mobile'],
            requirements: [],
        });
        expect(payload.feedback).toEqual({
            email: null,
            mobile: '+639173011987',
            webhook: null,
        });
        expect(payload.rider).toMatchObject({
            message: 'Campaign payout',
            message_format: 'plain',
            splash_meta: null,
        });
        expect(payload.metadata.campaign).toEqual({
            planning_key: 'plan-35d',
            execution_id: 'exec-35d',
            campaign_id: 'campaign-35d',
            audience_id: 'audience-35d',
            recipient_id: 'recipient-35d',
            source: 'campaign_cockpit',
            read_only: true,
            mutates_campaign: false,
        });
        expect(payload.metadata.custom.cockpit).toMatchObject({
            template_key: 'ofw-remittance',
            source: 'cockpit.quick-generate',
            campaign_context: 'read-model-prefill',
        });
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-beneficiary-url-panel"]',
                )
                .text(),
        ).toContain('https://example.test/r/PC-CAMPAIGN-001');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('Campaign attribution');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('plan-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('PC-CAMPAIGN-001');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('recipient-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('09173011987');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('ofw-remittance');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-attribution-panel"]',
                )
                .text(),
        ).toContain('PHP 500.00');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-return-navigation-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-return-navigation-panel"]',
                )
                .text(),
        ).toContain('Campaign return navigation');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-return-navigation-panel"]',
                )
                .text(),
        ).toContain('Campaign context preserved');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-return-link-campaign_explorer"]',
                )
                .attributes('href'),
        ).toContain('campaign_planning_key=plan-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-campaign-return-link-campaign_dashboard"]',
                )
                .attributes('href'),
        ).toContain('campaign_execution_id=exec-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-link-campaign_explorer"]',
                )
                .attributes('href'),
        ).toContain('campaign_planning_key=plan-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-link-campaign_explorer"]',
                )
                .attributes('href'),
        ).toContain('campaign_recipient_id=recipient-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-link-campaign_dashboard"]',
                )
                .attributes('href'),
        ).toContain('campaign_execution_id=exec-35d');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-post-issuance-link-campaign_dashboard"]',
                )
                .attributes('href'),
        ).toContain('campaign_recipient_id=recipient-35d');
        expect(JSON.stringify(payload)).not.toContain('campaign_payload');
        expect(JSON.stringify(payload)).not.toContain('provider_payload');
        expect(JSON.stringify(payload)).not.toContain('wallet');

        vi.unstubAllGlobals();
    });

    it('refreshes the quick generate read model only when the operator clicks refresh after success', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PC-UI-REFRESH',
                    links: {
                        cockpit_detail: '/x/cockpit/pay-codes/PC-UI-REFRESH',
                    },
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-refresh',
        });
        vi.mocked(router.reload).mockClear();

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
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(router.reload).not.toHaveBeenCalled();

        await wrapper
            .find('[data-testid="cockpit-quick-generate-refresh-button"]')
            .trigger('click');

        expect(router.reload).toHaveBeenCalledWith({
            only: ['quick_generate_read_model'],
        });

        vi.unstubAllGlobals();
    });

    it('keeps quick generate submit disabled until a post route url is available', async () => {
        const fetchMock = vi.fn();

        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: null,
                    allowed_methods: ['GET', 'POST'],
                },
            },
        });

        const button = wrapper.find(
            '[data-testid="cockpit-quick-generate-submit-button"]',
        );

        expect(button.attributes('disabled')).toBeDefined();

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');

        expect(fetchMock).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });

    it('prevents duplicate in-flight quick generate submit requests', async () => {
        let resolveFetch: ((value: unknown) => void) | null = null;
        const fetchMock = vi.fn().mockReturnValue(
            new Promise((resolve) => {
                resolveFetch = resolve;
            }),
        );

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-ui-idempotency-duplicate',
        });

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
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-submit-button"]')
                .attributes('disabled'),
        ).toBeDefined();

        resolveFetch?.({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
            }),
        });
        await Promise.resolve();
        await Promise.resolve();

        vi.unstubAllGlobals();
    });

    it('renders authorization gate facts without enabling generation', () => {
        const wrapper = mount(CockpitQuickGenerateAuthorizationGatePanel, {
            props: {
                authorization: {
                    status: 'runtime-ready',
                    gates: [
                        {
                            key: 'operator-authenticated',
                            label: 'Operator Authenticated',
                            status: 'passed',
                            reason: 'Authenticated Cockpit GET route resolved.',
                        },
                        {
                            key: 'can-generate-pay-code',
                            label: 'Can Generate Pay Code',
                            status: 'passed',
                            reason: 'The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.',
                        },
                    ],
                    redactions: {
                        payloads: 'authorization-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Authorization Runtime Diagnostics');
        expect(wrapper.text()).toContain('Operator Authenticated');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Can Generate Pay Code');
        expect(wrapper.text()).toContain(
            'The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.',
        );
        expect(wrapper.text()).toContain(
            'Provider and money movement authority remain separately gated outside the Cockpit shell.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-authorization-gate-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-authorization-gate"]',
            ),
        ).toHaveLength(2);
    });

    it('renders pricing gate facts without calculating or reserving funds', () => {
        const wrapper = mount(CockpitQuickGeneratePricingGatePanel, {
            props: {
                pricingGate: {
                    status: 'runtime-informational',
                    checks: [
                        {
                            key: 'template-selected',
                            label: 'Template Selected',
                            status: 'passed',
                            reason: 'The Money Changer template is selected by default for the current Quick Generate runtime.',
                        },
                        {
                            key: 'pricing-service-wired',
                            label: 'Pricing Service Wired',
                            status: 'passed',
                            reason: 'The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.',
                        },
                    ],
                    redactions: {
                        payloads: 'pricing-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Pricing Runtime Diagnostics');
        expect(wrapper.text()).toContain('Template Selected');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Pricing Service Wired');
        expect(wrapper.text()).toContain('runtime-informational');
        expect(wrapper.text()).toContain(
            'The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.',
        );
        expect(wrapper.text()).toContain(
            'Cockpit still does not expose raw pricing payloads.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-pricing-gate-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-pricing-gate-check"]',
            ),
        ).toHaveLength(2);
    });

    it('renders funding gate facts without wallet access or reservation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateFundingGatePanel, {
            props: {
                fundingGate: {
                    status: 'runtime-informational',
                    checks: [
                        {
                            key: 'funding-policy-known',
                            label: 'Funding Policy Known',
                            status: 'passed',
                            reason: 'Funding preflight is represented as an operator-safe result after Quick Generate submits.',
                        },
                        {
                            key: 'issuer-wallet-identified',
                            label: 'Issuer Account Identified',
                            status: 'runtime-diagnostic',
                            reason: 'Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.',
                        },
                    ],
                    redactions: {
                        payloads: 'funding-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Funding Runtime Diagnostics');
        expect(wrapper.text()).toContain('Funding Policy Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Issuer Account Identified');
        expect(wrapper.text()).toContain('runtime-informational');
        expect(wrapper.text()).toContain(
            'Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.',
        );
        expect(wrapper.text()).toContain(
            'Cockpit still does not expose raw wallet or provider funding payloads.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-funding-gate-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-funding-gate-check"]',
            ),
        ).toHaveLength(2);
    });

    it('renders idempotency gate facts without persistence or replay behavior', () => {
        const wrapper = mount(CockpitQuickGenerateIdempotencyGatePanel, {
            props: {
                idempotencyGate: {
                    status: 'backend-ready',
                    checks: [
                        {
                            key: 'idempotency-policy-known',
                            label: 'Idempotency Policy Known',
                            status: 'passed',
                            reason: 'Idempotency is represented as a read-only Cockpit readiness fact.',
                        },
                        {
                            key: 'replay-lookup-ready',
                            label: 'Replay Lookup Ready',
                            status: 'blocked',
                            reason: 'Cockpit does not query idempotency stores or replay records in Slice 22.',
                        },
                    ],
                    redactions: {
                        payloads: 'idempotency-gates-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Idempotency Gate Baseline');
        expect(wrapper.text()).toContain('Idempotency Policy Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Replay Lookup Ready');
        expect(wrapper.text()).toContain('blocked');
        expect(wrapper.text()).toContain(
            'Cockpit does not query idempotency stores or replay records in Slice 22.',
        );
        expect(wrapper.text()).toContain(
            'Idempotency gates are read-only facts in Slice 22.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-idempotency-gate-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-idempotency-gate-check"]',
            ),
        ).toHaveLength(2);
    });

    it('renders validation and redaction gate facts without request validation or submitted payload exposure', () => {
        const wrapper = mount(
            CockpitQuickGenerateValidationRedactionGatePanel,
            {
                props: {
                    validationRedactionGate: {
                        status: 'backend-ready',
                        checks: [
                            {
                                key: 'request-schema-known',
                                label: 'Request Schema Known',
                                status: 'passed',
                                reason: 'The Quick Generate mutation request shape is known and handled by the existing handoff route.',
                            },
                            {
                                key: 'sensitive-fields-redacted',
                                label: 'Sensitive Fields Redacted',
                                status: 'passed',
                                reason: 'Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.',
                            },
                        ],
                        redactions: {
                            payloads: 'validation-redaction-gates-only',
                        },
                    },
                },
            },
        );

        expect(wrapper.text()).toContain(
            'Validation and Redaction Diagnostics',
        );
        expect(wrapper.text()).toContain('Request Schema Known');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('Sensitive Fields Redacted');
        expect(wrapper.text()).toContain('backend-ready');
        expect(wrapper.text()).toContain(
            'Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.',
        );
        expect(wrapper.text()).toContain(
            'These diagnostics do not expose request payloads',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-validation-redaction-gate-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-validation-redaction-gate-check"]',
            ),
        ).toHaveLength(2);
    });

    it('renders mutation handoff plan facts without mutation routes or generation behavior', () => {
        const wrapper = mount(CockpitQuickGenerateMutationHandoffPlanPanel, {
            props: {
                mutationHandoffPlan: {
                    status: 'backend-handoff-wired',
                    steps: [
                        {
                            key: 'existing-issuance-owner-identified',
                            label: 'Existing Issuance Owner Identified',
                            status: 'passed',
                            reason: 'Quick Generate must hand off to the existing x-change issuance owner instead of inventing Cockpit generation behavior.',
                        },
                        {
                            key: 'generate-pay-code-action-handoff',
                            label: 'GeneratePayCode Action Handoff',
                            status: 'passed',
                            reason: 'Cockpit POST route calls the existing GeneratePayCode action through the approved handoff.',
                        },
                    ],
                    redactions: {
                        payloads: 'mutation-handoff-plan-only',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Mutation Handoff Diagnostics');
        expect(wrapper.text()).toContain('Existing Issuance Owner Identified');
        expect(wrapper.text()).toContain('passed');
        expect(wrapper.text()).toContain('GeneratePayCode Action Handoff');
        expect(wrapper.text()).toContain('backend-handoff-wired');
        expect(wrapper.text()).toContain(
            'Cockpit POST route calls the existing GeneratePayCode action through the approved handoff.',
        );
        expect(wrapper.text()).toContain(
            'Handoff diagnostics remain operator-safe',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-mutation-handoff-plan-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-mutation-handoff-plan-step"]',
            ),
        ).toHaveLength(2);
    });

    it('renders mutation preconditions review facts without approving mutation wiring', () => {
        const wrapper = mount(
            CockpitQuickGenerateMutationPreconditionsReviewPanel,
            {
                props: {
                    mutationPreconditionsReview: {
                        status: 'existing-handoff-ready',
                        recommendation: 'use-existing-issuance-handoff',
                        items: [
                            {
                                key: 'authorization-ready',
                                label: 'Authorization Ready',
                                status: 'passed',
                                reason: 'The authenticated Cockpit route may submit through the approved GeneratePayCode handoff.',
                            },
                            {
                                key: 'handoff-ready',
                                label: 'Handoff Ready',
                                status: 'passed',
                                reason: 'GeneratePayCode action handoff and GeneratePayCodeController handoff are wired.',
                            },
                        ],
                        redactions: {
                            payloads: 'mutation-preconditions-review-only',
                        },
                    },
                },
            },
        );

        expect(wrapper.text()).toContain('Handoff Preconditions Diagnostics');
        expect(wrapper.text()).toContain('use-existing-issuance-handoff');
        expect(wrapper.text()).toContain('Authorization Ready');
        expect(wrapper.text()).toContain('Handoff Ready');
        expect(wrapper.text()).toContain(
            'GeneratePayCode action handoff and GeneratePayCodeController handoff are wired.',
        );
        expect(wrapper.text()).toContain(
            'Provider, journal, action, feedback, and campaign mutations are not implied',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-mutation-preconditions-review-panel"]',
                )
                .exists(),
        ).toBe(true);
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-mutation-preconditions-review-item"]',
            ),
        ).toHaveLength(2);
    });

    it('renders the mutation authorization decision point without registering mutation behavior', () => {
        const wrapper = mount(
            CockpitQuickGenerateMutationAuthorizationDecisionPanel,
            {
                props: {
                    mutationAuthorizationDecision: {
                        status: 'approved-handoff',
                        decision: 'authorized_existing_handoff',
                        required_approval:
                            'completed-for-existing-generate-pay-code-handoff',
                        rationale:
                            'Cockpit may submit Quick Generate through the existing GeneratePayCode action without inventing a parallel issuance runtime.',
                        next_step:
                            'keep-provider-journal-action-feedback-mutations-separately-gated',
                        redactions: {
                            payloads: 'mutation-authorization-decision-only',
                        },
                    },
                },
            },
        );

        expect(wrapper.text()).toContain('Mutation Authorization Diagnostics');
        expect(wrapper.text()).toContain('authorized_existing_handoff');
        expect(wrapper.text()).toContain(
            'completed-for-existing-generate-pay-code-handoff',
        );
        expect(wrapper.text()).toContain('parallel issuance runtime');
        expect(wrapper.text()).toContain(
            'keep-provider-journal-action-feedback-mutations-separately-gated',
        );
        expect(wrapper.text()).toContain(
            'Provider, journal, action, feedback, and campaign mutations remain separately gated.',
        );
        expect(wrapper.find('form').exists()).toBe(false);
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-mutation-authorization-decision-panel"]',
                )
                .exists(),
        ).toBe(true);
    });

    it('renders the full Quick Generate page with active navigation and no side effects', async () => {
        const wrapper = mount(QuickGenerate, {
            props: {
                cockpit_header_read_model: {
                    balances: [
                        {
                            key: 'internal',
                            label: 'Client Funds',
                            value: '₱56.50',
                            amount_minor: 5650,
                        },
                    ],
                },
            },
        });

        await flushPromises();

        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-shell"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Pay Code Issuance');
        expect(wrapper.text()).toContain(
            'Create a Pay Code for someone to claim.',
        );
        const header = wrapper.find(
            '[data-testid="cockpit-quick-generate-header"]',
        );
        const headerProgress = wrapper.find(
            '[data-testid="cockpit-quick-generate-header-progress"]',
        );

        expect(header.classes()).toContain('px-1');
        expect(header.classes()).not.toContain('rounded-2xl');
        expect(headerProgress.exists()).toBe(false);
        const referenceGuide = wrapper.find(
            '[data-testid="cockpit-quick-generate-reference-guide"]',
        );

        expect(referenceGuide.exists()).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-quick-generate-starting-point"]')
                .exists(),
        ).toBe(true);
        const workflowStack = wrapper.find(
            '[data-testid="cockpit-quick-generate-primary-workflow-stack"]',
        );
        expect(workflowStack.classes()).toContain('space-y-3');
        expect(
            wrapper
                .getComponent(CockpitQuickGenerateSubmitPanel)
                .props('clientFundsMinor'),
        ).toBe(5650);
        const essentialsCanvas = wrapper.find(
            '[data-testid="cockpit-quick-generate-essentials-canvas"]',
        );
        const reuseDesign = wrapper.find(
            '[data-testid="cockpit-quick-generate-starting-point"]',
        );

        expect(essentialsCanvas.exists()).toBe(true);
        expect(essentialsCanvas.text()).toContain('Order');
        expect(essentialsCanvas.text()).toContain('Pay To');
        expect(
            essentialsCanvas
                .get('[data-testid="cockpit-quick-generate-voucher-kind"]')
                .text(),
        ).toBe('Disburseable');
        expect(
            essentialsCanvas
                .find('[data-testid="cockpit-quick-generate-starting-point"]')
                .exists(),
        ).toBe(true);
        expect(reuseDesign.text()).toContain('Templates');
        expect(reuseDesign.text()).toContain('Money Changer');
        expect(reuseDesign.classes()).toContain('border-t');
        expect(reuseDesign.classes()).not.toContain('rounded-2xl');
        expect(wrapper.text()).toContain('Repeat Last');
        expect(wrapper.text()).toContain('Save Template');
        expect(wrapper.text()).not.toContain('Editable Pay Code Preview');
        expect(wrapper.text()).not.toContain(
            'Changes appear here before issuance.',
        );
        const quickGenerateHeader = wrapper.get(
            '[data-testid="cockpit-quick-generate-header"]',
        );
        const fundingLink = quickGenerateHeader.get(
            '[data-testid="cockpit-quick-generate-funding-link"]',
        );

        const canvasHeader = essentialsCanvas.get(
            '[data-testid="cockpit-pay-code-canvas-header"]',
        );
        expect(canvasHeader.text()).toContain('Pay Code');
        expect(canvasHeader.text()).toContain('Stamp');
        expect(canvasHeader.text()).toContain('Cost');
        expect(canvasHeader.classes()).toContain('flex-col');
        expect(canvasHeader.classes()).toContain('@md:flex-row');
        expect(
            canvasHeader
                .get('[data-testid="cockpit-pay-code-canvas-view-switch"]')
                .classes(),
        ).toContain('w-full');
        expect(quickGenerateHeader.classes()).toContain('justify-between');
        expect(quickGenerateHeader.text()).toContain('Pay Code Issuance');
        expect(fundingLink.attributes('href')).toBe('/x/cockpit/funding');
        const orderSubmitButton = essentialsCanvas.get(
            '[data-testid="cockpit-quick-generate-submit-button"]',
        );
        const templateControls = essentialsCanvas.get(
            '[data-testid="cockpit-quick-generate-starting-point"]',
        );

        expect(
            essentialsCanvas
                .find(
                    '[data-testid="cockpit-pay-code-canvas-action-rail"]',
                )
                .exists(),
        ).toBe(false);
        expect(orderSubmitButton.classes()).toContain('min-h-10');
        expect(orderSubmitButton.classes()).toContain('rounded-xl');
        expect(orderSubmitButton.classes()).toContain('bg-emerald-600');
        expect(
            templateControls.findAll('button').some((button) =>
                button.classes().includes('bg-emerald-600'),
            ),
        ).toBe(false);
        expect(
            orderSubmitButton
                .find('[data-testid="cockpit-quick-generate-issue-icon"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).not.toContain('Engineering diagnostics');
        expect(wrapper.text()).not.toContain('Full architecture history');
        expect(wrapper.find('[data-testid="cockpit-layout"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).not.toContain('Review your Pay Code');
        expect(wrapper.text()).not.toContain('Design status');
        expect(wrapper.text()).toContain('Engineering Preview');
        expect(wrapper.text()).toContain('Issue Pay Code');
        expect(
            wrapper.findAll(
                '[data-testid="cockpit-quick-generate-submit-button"]',
            ),
        ).toHaveLength(1);
        const instructionBuilder = wrapper.find(
            '[data-testid="cockpit-voucher-instruction-builder"]',
        );
        const instructionBuilderText = instructionBuilder.text();

        expect(instructionBuilderText).toContain('Claim Experience');
        expect(instructionBuilderText).toContain('Issuance Details');
        expect(instructionBuilderText).toContain('Claim Requirements');
        expect(instructionBuilderText).toContain('Validation And Verification');
        expect(instructionBuilderText).toContain('Rider');
        expect(instructionBuilderText).toContain('Rider Message');
        expect(instructionBuilderText).toContain('Rider URL');
        expect(instructionBuilderText).toContain('Rider Splash');
        expect(instructionBuilderText).toContain('Stamp Appearance');
        expect(instructionBuilderText).toContain('Artwork');
        expect(instructionBuilderText).toContain('Claim Splash Preview');
        expect(instructionBuilderText).not.toContain('Claim Introduction');
        expect(instructionBuilderText).not.toContain('Action Link');
        expect(instructionBuilderText).not.toContain('Message Body');
        expect(instructionBuilderText).toContain('Status Updates');
        expect(instructionBuilderText).toContain(
            'Claim Schedule And Availability',
        );
        expect(instructionBuilderText).toContain(
            'Advanced Settlement Settings',
        );
        expect(instructionBuilderText).not.toContain('CreateV2');
        expect(instructionBuilderText).not.toContain('DTO');
        expect(instructionBuilderText).not.toContain('maps to');
        expect(instructionBuilderText).not.toContain('payload preview');
        expect(instructionBuilderText).not.toContain('instruction metadata');
        const collapsibleInstructionCards = [
            '#quick-generate-contract-money',
            '#quick-generate-contract-inputs',
            '#quick-generate-contract-validation',
            '#quick-generate-contract-feedback',
            '#quick-generate-contract-slices',
            '#quick-generate-contract-execution',
        ].map((selector) => wrapper.find(selector));

        expect(
            collapsibleInstructionCards.every(
                (card) =>
                    card.element.tagName === 'DETAILS' &&
                    card.attributes('open') === undefined,
            ),
        ).toBe(true);
        expect(
            wrapper.get('#quick-generate-contract-rider').element.tagName,
        ).toBe('SECTION');
        expect(wrapper.text()).toContain('Ready to issue');
    });

    it('walks through the protected claim experience inside the canvas', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(
                JSON.stringify({
                    schema: 'x-change.claim-experience-preview.manifest.v1',
                    status: 'ready',
                    reference: 'preview-01',
                    fingerprint: 'preview-fingerprint',
                    generated_at: '2026-07-29T12:00:00+08:00',
                    cache_hit: false,
                    safety: {
                        preview_only: true,
                        interactive: false,
                        money_movement: false,
                        provider_calls: false,
                        claim_submission: false,
                    },
                    journey: {
                        viewport: {
                            profile: 'mobile_claim_v1',
                            width: 390,
                            height: 844,
                        },
                        step_count: 2,
                        steps: [
                            {
                                sequence: 1,
                                key: 'claim-entry',
                                phase: 'entry',
                                title: 'Open Pay Code',
                                description: 'The recipient opens the claim.',
                                actor: 'redeemer',
                                render_kind: 'captured_frame',
                                status: 'captured',
                                preview_url:
                                    '/x/cockpit/quick-generate/claim-previews/preview-01/steps/claim-entry',
                                frame: {
                                    url: '/x/cockpit/quick-generate/claim-previews/preview-01/frames/claim-entry',
                                    mime_type: 'image/png',
                                    sha256: 'frame-hash',
                                    width: 390,
                                    height: 844,
                                },
                            },
                            {
                                sequence: 2,
                                key: 'xray-preview',
                                phase: 'entry',
                                title: 'Pay Code x-ray preview',
                                description: 'Internal recorder overlay.',
                                actor: 'redeemer',
                                render_kind: 'captured_frame',
                                status: 'captured',
                                preview_url:
                                    '/x/cockpit/quick-generate/claim-previews/preview-01/steps/xray-preview',
                                frame: {
                                    url: '/x/cockpit/quick-generate/claim-previews/preview-01/frames/xray-preview',
                                    mime_type: 'image/png',
                                    sha256: 'xray-frame-hash',
                                    width: 390,
                                    height: 844,
                                },
                            },
                        ],
                    },
                    exports: {
                        pdf_url:
                            '/x/cockpit/quick-generate/claim-previews/preview-01/exports/pdf',
                        html_url:
                            '/x/cockpit/quick-generate/claim-previews/preview-01/exports/html',
                    },
                }),
                {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                },
            ),
        );

        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['GET', 'POST'],
                },
                claimPreviewContract: {
                    status: 'registered',
                    route: 'x-change.cockpit.quick-generate.claim-previews.store',
                    route_url: '/x/cockpit/quick-generate/claim-previews',
                    money_movement: false,
                    preview_cache: true,
                },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-quick-generate-submit-amount"]')
            .setValue('25');
        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-claim-button"]')
            .trigger('click');
        await flushPromises();

        const previewCall = fetchMock.mock.calls.find(
            ([url]) => url === '/x/cockpit/quick-generate/claim-previews',
        );

        if (!previewCall) {
            throw new Error('Preview request was not sent.');
        }

        const [url, options] = previewCall;
        const payload = JSON.parse(options.body);

        expect(url).toBe('/x/cockpit/quick-generate/claim-previews');
        expect(options.method).toBe('POST');
        expect(payload.cash.amount).toBe(25);
        expect(payload.preview_profile).toBe('issuer');
        expect(wrapper.text()).not.toContain('Claim Experience Preview');
        expect(wrapper.text()).not.toContain('Preview Only');
        expect(wrapper.text()).not.toContain('Recipient Journey');
        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-claim"]')
                .classes(),
        ).toContain('aspect-[1.72/1]');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-experience-preview"]')
                .classes(),
        ).toContain('min-w-0');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-experience-preview"]')
                .find('ol')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-preview-iframe"]')
                .attributes('src'),
        ).toBe(
            '/x/cockpit/quick-generate/claim-previews/preview-01/steps/claim-entry',
        );

        expect(
            wrapper
                .find('[data-testid="cockpit-claim-experience-concept"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-experience-next"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-claim-preview-panel"]',
                )
                .exists(),
        ).toBe(false);
        expect(wrapper.html()).not.toContain('file://');
    });
});
