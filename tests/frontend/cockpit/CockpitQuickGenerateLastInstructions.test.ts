import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: {
        reload: vi.fn(),
    },
}));

const collectionDestination = {
    schema: 'x-change.cockpit.collection-destination.v1',
    label: 'Your Client Funds',
    description:
        'Payments are credited to the collection account authorized for the signed-in operator.',
    authority: 'authenticated_operator',
    status: 'ready',
    editable: false,
    managed_automatically: true,
} as const;

describe('Quick Generate last instructions', () => {
    it('ignores legacy wallet ids and presents the authoritative collection destination', () => {
        const instructions = (collectionWalletId?: string) => ({
            schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
            saved_at: '2026-08-25T09:00:00Z',
            instructions: {
                voucher_type: 'payable',
                cash: { amount: 100, currency: 'PHP' },
                metadata: collectionWalletId
                    ? { collection_wallet_id: collectionWalletId }
                    : {},
            },
        });

        const remembered = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                collectionDestination,
                startupMode: 'repeat_last',
                lastInstructions: instructions('shared-wallet-9'),
            },
        });
        const defaulted = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                collectionDestination,
                startupMode: 'repeat_last',
                lastInstructions: instructions(),
            },
        });

        expect(
            remembered
                .get(
                    '[data-testid="cockpit-quick-generate-collection-destination-label"]',
                )
                .text(),
        ).toBe('Your Client Funds');
        expect(
            defaulted
                .get(
                    '[data-testid="cockpit-quick-generate-collection-destination-label"]',
                )
                .text(),
        ).toBe('Your Client Funds');
        expect(
            remembered.find(
                '[data-testid="cockpit-quick-generate-collection-wallet"]',
            ).exists(),
        ).toBe(false);
    });

    it('shows the destination for an explicit collectible flow', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                collectionDestination,
                startupMode: 'repeat_last',
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-28T09:00:00Z',
                    instructions: {
                        voucher_type: 'redeemable',
                        cash: { amount: 100, currency: 'PHP' },
                        metadata: { flow_type: 'collectible' },
                    },
                },
            },
        });

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-collection-destination-label"]',
                )
                .text(),
        ).toBe('Your Client Funds');
    });

    it('hydrates the payable amount from the canonical target with a legacy cash fallback', () => {
        const instructions = (targetAmount?: number) => ({
            schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
            saved_at: '2026-08-26T09:00:00Z',
            instructions: {
                voucher_type: 'payable',
                target_amount: targetAmount,
                cash: {
                    amount: targetAmount === undefined ? 62.5 : 0,
                    currency: 'PHP',
                },
            },
        });
        const mountWith = (targetAmount?: number) =>
            mount(CockpitQuickGenerateSubmitPanel, {
                props: {
                    templates: cockpitQuickGenerateTemplates,
                    startupMode: 'repeat_last',
                    lastInstructions: instructions(targetAmount),
                },
            });

        expect(
            mountWith(125).get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('125.00');
        expect(
            mountWith().get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('62.50');
    });

    it('hydrates settlement cash and target values independently', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                startupMode: 'repeat_last',
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-26T09:00:00Z',
                    instructions: {
                        voucher_type: 'settlement',
                        target_amount: 900,
                        cash: {
                            amount: 300,
                            currency: 'PHP',
                        },
                    },
                },
            },
        });

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('300.00');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-target-amount"]',
            ).element.value,
        ).toBe('900');
    });

    it('keeps the last successful design behind Repeat Last and restores it without its secret', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: document.body,
            props: {
                templates: cockpitQuickGenerateTemplates,
                savedTemplates: [
                    {
                        reference: '01SAVEDTEMPLATE',
                        name: 'School Allowance',
                        description: 'Reusable school support settings.',
                        base_template_key: 'money-changer',
                        instructions: {},
                        include_amount: true,
                        include_purpose: true,
                    },
                ],
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-07-27T22:00:00+08:00',
                    instructions: {
                        cash: {
                            amount: 88.5,
                            currency: 'PHP',
                            fee_strategy: 'include',
                            validation: {
                                secret: 'never-restore-me',
                                mobile: '09173011987',
                                country: 'PH',
                            },
                            slice_mode: 'fixed',
                            slices: 3,
                        },
                        provider: 'netbank',
                        inputs: {
                            fields: ['mobile', 'name', 'signature'],
                            requirements: ['kyc', 'otp'],
                        },
                        count: 2,
                        feedback: {
                            mobile: '+639173011987',
                            email: 'recipient@example.test',
                            webhook: null,
                        },
                        rider: {
                            message: 'School allowance',
                            message_format: 'markdown',
                            url: 'https://example.test/instructions',
                            splash: '<h1>Ready for school</h1>',
                            splash_format: 'html',
                            splash_timeout: 5,
                            og_source: 'message',
                            stamp: {
                                source: 'splash',
                                title: 'Back to school',
                                description: 'A Pay Code for school needs.',
                                fit: 'contain',
                                position: 'top',
                                scrim: 12,
                                theme: 'dark',
                                version: 1,
                            },
                        },
                        ttl: 'P3D',
                        claim: {
                            default_outcome: 'provider_disbursement',
                        },
                        metadata: {
                            custom: {
                                cockpit: {
                                    template_key: 'money-changer',
                                    saved_template: {
                                        reference: '01SAVEDTEMPLATE',
                                        name: 'Untrusted Historical Name',
                                    },
                                },
                            },
                        },
                    },
                },
            },
        });

        const amountInput = wrapper.get<HTMLInputElement>(
            '[data-testid="cockpit-quick-generate-primary-amount"]',
        );
        const repeatLastButton = wrapper.get(
            '[data-testid="cockpit-quick-generate-repeat-last"]',
        );

        expect(amountInput.element.value).toBe('');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-start-blank"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await repeatLastButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(amountInput.element.value).toBe('88.50');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-recipient"]',
            ).element.value,
        ).toBe('');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-purpose"]',
            ).element.value,
        ).toBe('School allowance');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-validation-secret"]',
            ).element.value,
        ).toBe('');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-current-template"]')
                .text(),
        ).toContain('School Allowance');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-current-template"]')
                .text(),
        ).toContain('Current ·');
        expect(repeatLastButton.attributes('aria-pressed')).toBe('true');
        expect(repeatLastButton.classes()).toContain('bg-emerald-50');
        expect(repeatLastButton.classes()).not.toContain('bg-emerald-600');
        expect(
            repeatLastButton
                .find(
                    '[data-testid="cockpit-quick-generate-repeat-last-check"]',
                )
                .exists(),
        ).toBe(true);

        const preview = JSON.parse(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-engineering-preview-json"]',
                )
                .text(),
        );

        expect(preview.cash).toMatchObject({
            amount: 88.5,
            currency: 'PHP',
            fee_strategy: 'include',
        });
        expect(preview.cash).not.toHaveProperty('slice_mode');
        expect(preview.cash).not.toHaveProperty('slices');
        expect(preview.slice_plan).toMatchObject({
            schema: 'voucher.slice-plan.v1',
            mode: 'equal',
            selection: 'next_only',
            total_minor: 8850,
            currency: 'PHP',
            slices: [
                { id: 'slice_1', label: 'Slice 1', amount_minor: 2950 },
                { id: 'slice_2', label: 'Slice 2', amount_minor: 2950 },
                { id: 'slice_3', label: 'Slice 3', amount_minor: 2950 },
            ],
        });
        expect(preview.cash.validation).not.toHaveProperty('secret');
        expect(preview.cash.validation).not.toHaveProperty('mobile');
        expect(preview.inputs).toEqual({
            fields: ['mobile', 'signature', 'name'],
            requirements: ['kyc', 'otp'],
        });
        expect(preview.rider).toMatchObject({
            message: 'School allowance',
            message_format: 'markdown',
            url: 'https://example.test/instructions',
            splash: '<h1>Ready for school</h1>',
            splash_format: 'html',
            splash_timeout: 5,
            og_source: 'splash',
            stamp: {
                source: 'splash',
                title: 'Back to school',
                description: 'A Pay Code for school needs.',
                fit: 'contain',
                position: 'top',
                scrim: 12,
                theme: 'dark',
                version: 3,
                design_id: 'x-change-default',
                design_version: 1,
            },
        });
        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-design-button"]')
            .trigger('click');
        await wrapper.vm.$nextTick();

        expect(
            (
                wrapper.get(
                    '[data-testid="cockpit-quick-generate-rider-stamp-source"] input:checked',
                ).element as HTMLInputElement
            ).value,
        ).toBe('splash');
        expect(
            (
                wrapper.get(
                    '[data-testid="cockpit-quick-generate-rider-stamp-fit"] input:checked',
                ).element as HTMLInputElement
            ).value,
        ).toBe('contain');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-last-instructions"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('');
        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]')
                .element,
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-start-blank"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-repeat-last"]')
                .attributes('aria-pressed'),
        ).toBe('false');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-recipient"]',
            ).element.value,
        ).toBe('');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-repeat-last"]')
            .trigger('click');
        await wrapper.vm.$nextTick();

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('88.50');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-recipient"]',
            ).element.value,
        ).toBe('');
        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]')
                .element,
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-repeat-last"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-feedback-mobile"]',
            ).element.value,
        ).toBe('');
        wrapper.unmount();
    });

    it('automatically restores Repeat Last only when configured', async () => {
        const host = document.createElement('div');
        document.body.appendChild(host);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: host,
            props: {
                templates: cockpitQuickGenerateTemplates,
                startupMode: 'repeat_last',
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-08-29T00:00:00Z',
                    instructions: {
                        cash: { amount: 42.5, currency: 'PHP' },
                        inputs: { fields: [] },
                    },
                },
            },
        });

        await wrapper.vm.$nextTick();

        const amountInput = wrapper.get<HTMLInputElement>(
            '[data-testid="cockpit-quick-generate-primary-amount"]',
        );

        expect(amountInput.element.value).toBe('42.50');
        expect(amountInput.element).toBe(document.activeElement);
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-repeat-last"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        wrapper.unmount();
        host.remove();
    });

    it('falls back to Blank when Repeat Last is configured without history', async () => {
        const host = document.createElement('div');
        document.body.appendChild(host);

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: host,
            props: {
                templates: cockpitQuickGenerateTemplates,
                startupMode: 'repeat_last',
            },
        });

        await wrapper.vm.$nextTick();

        const amountInput = wrapper.get<HTMLInputElement>(
            '[data-testid="cockpit-quick-generate-primary-amount"]',
        );

        expect(amountInput.element.value).toBe('');
        expect(amountInput.element).toBe(document.activeElement);
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-start-blank"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        wrapper.unmount();
        host.remove();
    });

    it('gives explicit campaign context precedence over remembered instructions', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                campaignContext: {
                    status: 'available',
                    authorized: true,
                    read_only: true,
                    mutates_campaign: false,
                    draft: {
                        template_key: 'ofw-remittance',
                        amount: '500',
                        currency: 'PHP',
                        recipient_reference: '09170000000',
                        purpose: 'Campaign payout',
                    },
                },
                lastInstructions: {
                    schema: 'x-change.cockpit.quick-generate-last-instructions.v1',
                    saved_at: '2026-07-27T22:00:00+08:00',
                    instructions: {
                        cash: {
                            amount: 88.5,
                            currency: 'PHP',
                        },
                    },
                },
            },
        });

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('500.00');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-last-instructions"]',
                )
                .exists(),
        ).toBe(false);
    });
});
