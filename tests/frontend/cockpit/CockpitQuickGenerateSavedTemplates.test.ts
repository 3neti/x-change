import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

const { post } = vi.hoisted(() => ({
    post: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: {
        post,
        reload: vi.fn(),
    },
}));

describe('Quick Generate saved templates', () => {
    beforeEach(() => {
        post.mockReset();
    });

    it('applies an owner template without restoring recipient details', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
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

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('75');
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
            base_template_key: 'money-changer',
            include_amount: true,
            include_purpose: true,
        });
        expect(payload.instructions.metadata.custom.cockpit).not.toHaveProperty(
            'recipient_reference',
        );
        expect(payload.instructions.cash.validation).not.toHaveProperty(
            'mobile',
        );
    });
});
