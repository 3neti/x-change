import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

const { patch, post } = vi.hoisted(() => ({
    patch: vi.fn(),
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: {
        patch,
        post,
        reload: vi.fn(),
    },
}));

describe('Quick Generate saved templates', () => {
    beforeEach(() => {
        patch.mockReset();
        post.mockReset();
    });

    it('applies an owner template without restoring recipient details', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            attachTo: document.body,
            props: {
                templates: cockpitQuickGenerateTemplates,
                savedTemplates: [
                    {
                        reference: '01TEMPLATE',
                        name: 'Weekly Allowance',
                        description: 'A reusable allowance design.',
                        base_template_key: 'money-changer',
                        include_amount: true,
                        include_purpose: true,
                        instructions: {
                            cash: {
                                amount: 75,
                                currency: 'PHP',
                                validation: {
                                    mobile: '09173011987',
                                },
                            },
                            rider: {
                                message: 'Weekly allowance',
                            },
                            feedback: {
                                mobile: '09173011987',
                            },
                            metadata: {
                                custom: {
                                    cockpit: {
                                        template_key: 'money-changer',
                                        recipient_reference: '09173011987',
                                    },
                                },
                            },
                        },
                    },
                ],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-choose-template"]')
            .trigger('click');

        expect(wrapper.text()).toContain('My Templates');
        expect(wrapper.text()).toContain('Weekly Allowance');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-saved-template-option"]')
            .trigger('click');
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-current-template"]')
                .text(),
        ).toBe('Current · Weekly Allowance');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('75.00');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-purpose"]',
            ).element.value,
        ).toBe('Weekly allowance');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-recipient"]',
            ).element.value,
        ).toBe('');
        expect(document.activeElement).toBe(
            wrapper.get(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element,
        );

        const preview = JSON.parse(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-engineering-preview-json"]',
                )
                .text(),
        );

        expect(preview.metadata.custom.cockpit.saved_template).toEqual({
            reference: '01TEMPLATE',
            name: 'Weekly Allowance',
        });
        wrapper.unmount();
    });

    it('submits a sanitized reusable blueprint through Wayfinder', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('09173011987');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-save-template"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-template-name"]')
            .setValue('Branch Cash Out');
        await wrapper
            .get(
                '[data-testid="cockpit-quick-generate-template-include-amount"]',
            )
            .setValue(true);
        await wrapper
            .get('[data-testid="cockpit-quick-generate-template-save-submit"]')
            .trigger('click');

        expect(post).toHaveBeenCalledOnce();

        const [route, payload] = post.mock.calls[0] as [
            { url: string; method: string },
            Record<string, any>,
        ];

        expect(route).toEqual({
            url: '/x/cockpit/pay-code-templates',
            method: 'post',
        });
        expect(payload).toMatchObject({
            name: 'Branch Cash Out',
            base_template_key: 'blank-pay-code',
            include_amount: true,
            include_purpose: true,
        });
        expect(payload.instructions.metadata.custom.cockpit).not.toHaveProperty(
            'recipient_reference',
        );
        expect(payload.instructions.metadata.custom.cockpit).not.toHaveProperty(
            'saved_template',
        );
        expect(payload.instructions.cash.validation).not.toHaveProperty(
            'mobile',
        );
    });

    it('updates the active personal template without creating a duplicate', async () => {
        const savedTemplate = {
            reference: '01TEMPLATE',
            name: 'Weekly Allowance',
            description: 'A reusable allowance template.',
            base_template_key: 'money-changer',
            include_amount: true,
            include_purpose: true,
            updated_at: '2026-07-29T06:00:00+08:00',
            instructions: {
                cash: {
                    amount: 75,
                    currency: 'PHP',
                },
                rider: {
                    message: 'Weekly allowance',
                },
                metadata: {
                    custom: {
                        cockpit: {
                            template_key: 'money-changer',
                        },
                    },
                },
            },
        };
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                savedTemplates: [savedTemplate],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-choose-template"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-saved-template-option"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-save-template"]')
            .trigger('click');

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-template-update-mode"]',
                )
                .text(),
        ).toContain('Weekly Allowance');
        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-template-name"]',
            ).element.value,
        ).toBe('Weekly Allowance');
        expect(wrapper.text()).toContain(
            'Already issued Pay Codes will not change.',
        );

        await wrapper
            .get('[data-testid="cockpit-quick-generate-template-name"]')
            .setValue('Monthly Allowance');
        patch.mockImplementationOnce(
            (
                _route: unknown,
                _payload: unknown,
                options: {
                    onSuccess?: (page: {
                        props: Record<string, unknown>;
                    }) => void;
                },
            ) => {
                options.onSuccess?.({
                    props: {
                        saved_templates: [
                            {
                                ...savedTemplate,
                                name: 'Monthly Allowance',
                                updated_at: '2026-07-29T06:15:00+08:00',
                            },
                        ],
                    },
                });
            },
        );
        await wrapper
            .get('[data-testid="cockpit-quick-generate-template-save-submit"]')
            .trigger('click');

        expect(patch).toHaveBeenCalledOnce();

        const [route, payload] = patch.mock.calls[0] as [
            { url: string; method: string },
            Record<string, any>,
        ];

        expect(route).toEqual({
            url: '/x/cockpit/pay-code-templates/01TEMPLATE',
            method: 'patch',
        });
        expect(payload).toMatchObject({
            name: 'Monthly Allowance',
            expected_updated_at: '2026-07-29T06:00:00+08:00',
            include_amount: true,
            include_purpose: true,
        });
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-current-template"]')
                .text(),
        ).toBe('Current · Monthly Allowance');
        expect(
            wrapper.find(
                '[data-testid="cockpit-quick-generate-save-template-dialog"]',
            ).exists(),
        ).toBe(false);
        expect(post).not.toHaveBeenCalled();
    });

    it('can save the active personal template as a new template', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                savedTemplates: [
                    {
                        reference: '01TEMPLATE',
                        name: 'Weekly Allowance',
                        description: null,
                        base_template_key: 'money-changer',
                        include_amount: false,
                        include_purpose: true,
                        updated_at: '2026-07-29T06:00:00+08:00',
                        instructions: {
                            cash: {
                                currency: 'PHP',
                            },
                            metadata: {
                                custom: {
                                    cockpit: {
                                        template_key: 'money-changer',
                                    },
                                },
                            },
                        },
                    },
                ],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-choose-template"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-saved-template-option"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-save-template"]')
            .trigger('click');
        await wrapper
            .get(
                '[data-testid="cockpit-quick-generate-template-create-mode"]',
            )
            .trigger('click');

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-template-name"]',
            ).element.value,
        ).toBe('Weekly Allowance Copy');

        await wrapper
            .get('[data-testid="cockpit-quick-generate-template-save-submit"]')
            .trigger('click');

        expect(post).toHaveBeenCalledOnce();
        expect(patch).not.toHaveBeenCalled();
    });
});
