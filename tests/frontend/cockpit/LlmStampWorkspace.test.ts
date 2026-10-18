import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import QuickGenerate from '../../../resources/js/cockpit/pages/QuickGenerate.vue';
import SubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import Prompt from '../../../resources/js/cockpit/components/CockpitQuickGeneratePromptComposer.vue';
import { stampPreviewCache } from '../../../resources/js/cockpit/components/CockpitSystemStamp.vue';
import Canvas from '../../../resources/js/cockpit/components/CockpitPayCodeCanvas.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    Head: { template: '<div><slot /></div>' },
    router: { reload: vi.fn() },
    usePage: () => ({
        props: {
            xchange: {
                stamp_default: 'steampunk',
                stamps: [
                    {
                        id: 'steampunk',
                        revision: 8,
                        name: 'Steampunk',
                        customization: {
                            controls: ['qr', 'tagline'],
                            defaults: {},
                        },
                    },
                    {
                        id: 'cats',
                        revision: 7,
                        name: 'Cats',
                        customization: {
                            controls: ['crop', 'qr', 'tagline'],
                            defaults: {},
                        },
                    },
                ],
            },
        },
    }),
}));
afterEach(() => {
    stampPreviewCache.clear();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

it.each([false, true])(
    'shares the Order and preserved design, reviews without issuance, and restores the prompt in public mode %s',
    async (publicMode) => {
        vi.useFakeTimers();
        const fetch = vi.fn(async (url: string) => ({
            ok: url !== '/issue',
            json: async () =>
                url === '/issue'
                    ? {
                          message: 'Review failed: fix the Order.',
                          errors: {
                              'cash.amount': ['Review failed: fix the Order.'],
                          },
                      }
                    : url === '/interpret'
                      ? {
                            status: 'ready',
                            instructions: {
                                cash: { amount: 500, currency: 'PHP' },
                                rider: { message: 'School allowance' },
                                inputs: { fields: ['signature'] },
                                ttl: 'P3D',
                            },
                            pricing: { account_debit: 515 },
                        }
                      : url.includes('/stamps/')
                        ? { image: 'data:image/png;base64,preview' }
                        : {
                              success: true,
                              data: {
                                  cost: {
                                      currency: 'PHP',
                                      account_debit: 515,
                                      total: 15,
                                  },
                              },
                          },
        }));
        vi.stubGlobal('fetch', fetch);
        const wrapper = mount(QuickGenerate, {
            props: {
                initial_workspace: 'llm',
                prompt_interpret_endpoint: '/interpret',
                ...(publicMode
                    ? { surface_profile: { kind: 'public_auto_generate' } }
                    : {}),
                quick_generate_read_model: {
                    status: 'available',
                    authorized: true,
                    mutation_contract: {
                        runtime_enabled: true,
                        route_url: '/issue',
                        allowed_methods: ['POST'],
                    },
                },
            } as any,
            global: {
                stubs: {
                    Teleport: true,
                    CockpitLayout: { template: '<main><slot /></main>' },
                    PublicIssuanceLayout: { template: '<main><slot /></main>' },
                },
            },
        });
        try {
            await flushPromises();
            const canonical = wrapper.findComponent(SubmitPanel);
            const instance = canonical.vm;
            const orderElement = canonical.element;
            (instance as any).applyInstructionBlueprint(
                {
                    cash: { amount: 100, currency: 'PHP' },
                    rider: {
                        stamp: {
                            version: 3,
                            design_id: 'cats',
                            design_version: 7,
                            show_tagline: false,
                        },
                    },
                    metadata: {
                        custom: {
                            stamp_presentation: {
                                options: {
                                    preset: 'soft',
                                    crop: 'bottom',
                                    show_tagline: false,
                                },
                            },
                        },
                    },
                },
                false,
            );
            const prompt = wrapper.findComponent(Prompt);
            await prompt
                .get('[data-testid="cockpit-quick-generate-prompt-input"]')
                .setValue('500 pesos for school, signature required');
            await vi.advanceTimersByTimeAsync(450);
            await flushPromises();
            await vi.advanceTimersByTimeAsync(350);
            await flushPromises();
            expect(fetch.mock.calls.filter(call => call[0].includes('/stamps/'))).toHaveLength(1);
            const stamp = prompt.findComponent(Canvas);
            expect(stamp.props()).toMatchObject({
                amount: '500',
                purpose: 'School allowance',
                expiry: '3 Days',
                instructionKeys: expect.arrayContaining(['input.signature']),
                riderStamp: { design: { id: 'cats', version: 7 } },
                stampPresentation: {
                    options: {
                        preset: 'soft',
                        crop: 'bottom',
                        show_tagline: false,
                    },
                },
            });
            expect(canonical.emitted('submitStart')).toBeUndefined();
            await prompt
                .get('[data-testid="cockpit-quick-generate-prompt-go"]')
                .trigger('click');
            await flushPromises();
            expect(
                wrapper
                    .get(
                        '[data-testid="cockpit-quick-generate-cost-preview-backdrop"]',
                    )
                    .exists(),
            ).toBe(true);
            expect(wrapper.findAll('[role="tab"][aria-selected="true"]').map(tab => tab.text())).toContain('Stamp');
            expect(
                wrapper.get('[data-testid="llm-reviewed-order-issue"]').text(),
            ).toContain('Issue');
            const actions = wrapper.get('[data-testid="pay-code-review-actions"]');
            expect(actions.get('[data-testid="cockpit-quick-generate-cost-preview-close"]').text()).toBe('Close');
            expect(actions.get('[data-testid="llm-reviewed-order-issue"]').exists()).toBe(true);
            expect(fetch.mock.calls.some((call) => call[0] === '/issue')).toBe(
                false,
            );
            expect(canonical.emitted('submitStart')).toBeUndefined();
            await wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-cost-preview-close"]',
                )
                .trigger('click');
            await flushPromises();
            expect(wrapper.findComponent(SubmitPanel).element).toBe(
                orderElement,
            );
            expect(
                prompt.get<HTMLInputElement>(
                    '[data-testid="cockpit-quick-generate-prompt-input"]',
                ).element.value,
            ).toBe('500 pesos for school, signature required');
            expect(
                prompt
                    .get('[data-testid="cockpit-quick-generate-prompt-go"]')
                    .attributes('disabled'),
            ).toBeUndefined();
            await prompt
                .get('[data-testid="llm-estimated-total"]')
                .trigger('click');
            await flushPromises();
            expect(wrapper.findAll('[role="tab"][aria-selected="true"]').map(tab => tab.text())).toContain('Cost');
            expect(fetch.mock.calls.some((call) => call[0] === '/issue')).toBe(false);
            const issue = wrapper.get(
                '[data-testid="llm-reviewed-order-issue"]',
            );
            expect(issue.attributes('disabled')).toBeUndefined();
            await issue.trigger('click');
            await flushPromises();
            expect(
                fetch.mock.calls.filter((call) => call[0] === '/issue'),
            ).toHaveLength(1);
            expect(canonical.emitted('submitStart')).toHaveLength(1);
            expect(canonical.isVisible()).toBe(true);
            expect(canonical.text()).toContain('Review failed: fix the Order.');
        } finally {
            wrapper.unmount();
        }
    },
);
