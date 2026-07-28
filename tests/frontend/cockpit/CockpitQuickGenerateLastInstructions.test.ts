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

describe('Quick Generate last instructions', () => {
    it('preloads the last successful design without restoring its secret', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
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

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('88.5');
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
            slice_mode: 'fixed',
            slices: 3,
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
                version: 2,
            },
        });
        expect(
            wrapper.get<HTMLSelectElement>(
                '[data-testid="cockpit-quick-generate-rider-stamp-source"]',
            ).element.value,
        ).toBe('splash');
        expect(
            wrapper.get<HTMLSelectElement>(
                '[data-testid="cockpit-quick-generate-rider-stamp-fit"]',
            ).element.value,
        ).toBe('contain');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-start-blank"]')
            .trigger('click');

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
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-recipient"]',
            ).element.value,
        ).toBe('');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-repeat-last"]')
            .trigger('click');

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('88.5');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-recipient"]',
            ).element.value,
        ).toBe('');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-feedback-mobile"]',
            ).element.value,
        ).toBe('');
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
        ).toBe('500');
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-last-instructions"]',
                )
                .exists(),
        ).toBe(false);
    });
});
