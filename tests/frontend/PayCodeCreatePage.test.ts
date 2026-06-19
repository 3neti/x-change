import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CreatePage from '../../resources/js/pages/x-change/pay-codes/Create.vue';

const { routerVisit } = vi.hoisted(() => ({
    routerVisit: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
    router: {
        visit: routerVisit,
    },
}));

vi.mock('@/layouts/x-change/XChangeLayout.vue', () => ({
    default: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        emits: ['click'],
        template:
            '<button type="button" @click="$emit(\'click\')"><slot /></button>',
    },
}));

vi.mock('@/components/ui/alert', () => ({
    Alert: {
        template: '<div><slot /></div>',
    },
    AlertDescription: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('@/components/ui/tabs', () => ({
    Tabs: {
        template: '<div><slot /></div>',
    },
    TabsList: {
        template: '<div><slot /></div>',
    },
    TabsTrigger: {
        template: '<button type="button"><slot /></button>',
    },
    TabsContent: {
        template: '<div><slot /></div>',
    },
}));

vi.mock(
    '@/components/x-change/pay-codes',
    () => ({
        PayCodeGenerationBasicForm: {
            props: ['modelValue'],
            emits: ['update:modelValue'],
            template: `
            <button
                data-testid="set-valid-form"
                type="button"
                @click="$emit('update:modelValue', {
                    ...modelValue,
                    amount: 100,
                    quantity: 1,
                })"
            >
                Set Valid
            </button>
        `,
        },
        PayCodeGenerationAdvancedForm: {
            template: '<div />',
        },
        PayCodeCostEstimateCard: {
            template: '<div />',
        },
        PayCodeInstructionPreview: {
            template: '<div />',
        },
    }),
    { virtual: true },
);

vi.mock('@/composables/useXChangeRoutes', () => ({
    useXChangeRoutes: () => ({
        payCodes: {
            index: () => '/x/pay-codes',
            show: (code: string) => `/x/pay-codes/${code}`,
        },
        api: {
            estimatePayCode: '/api/x/v1/pay-codes/estimate',
            generatePayCode: '/api/x/v1/pay-codes',
        },
    }),
}));

vi.mock('lucide-vue-next', () => ({
    ArrowRight: {
        template: '<span />',
    },
    AlertCircle: {
        template: '<span />',
    },
    ArrowLeft: {
        template: '<span />',
    },
    CheckCircle2: {
        template: '<span />',
    },
    Loader2: {
        template: '<span />',
    },
    PlusCircle: {
        template: '<span />',
    },
    RefreshCcw: {
        template: '<span />',
    },
    ShieldAlert: {
        template: '<span />',
    },
}));

describe('PayCodeCreatePage', () => {
    beforeEach(() => {
        routerVisit.mockReset();
        vi.useFakeTimers();
        global.fetch = vi
            .fn()
            .mockResolvedValueOnce({
                ok: false,
                json: async () => ({
                    success: false,
                    code: 'PROVIDER_PROVISIONING_REQUIRED',
                    message:
                        'Pay Code issuance requires provider provisioning before the voucher can be created.',
                    errors: {
                        provisioning: {
                            provider: 'paynamics',
                            mode: 'wallet_create',
                            reason: 'Issuer provider customer wallet is not ready.',
                            onboarding: {
                                reference: 'onb-issue-123',
                            },
                            descriptor: {
                                title: 'Create your Paynamics wallet',
                                description:
                                    'Complete wallet setup so Pay Codes can be issued and paid out.',
                                steps: ['profile', 'wallet', 'kyc', 'ready'],
                            },
                        },
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    success: true,
                    data: {
                        code: 'PC-12345',
                    },
                }),
            }) as typeof fetch;
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('renders provisioning guidance when generate returns a provisioning-required error', async () => {
        const wrapper = mount(CreatePage);

        await wrapper.find('[data-testid="set-valid-form"]').trigger('click');
        await nextTick();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Generate Pay Code'))
            ?.trigger('click');
        await nextTick();
        await Promise.resolve();
        await nextTick();

        expect(wrapper.text()).toContain('Create your Paynamics wallet');
        expect(wrapper.text()).toContain(
            'Complete wallet setup so Pay Codes can be issued and paid out.',
        );
        expect(wrapper.find('[data-testid="set-valid-form"]').exists()).toBe(
            false,
        );
    });

    it('uses the padded pay code page shell', () => {
        const wrapper = mount(CreatePage);
        const shell = wrapper
            .findAll('div')
            .find((element) => element.classes().includes('lg:p-8'));

        expect(shell?.classes()).toEqual(
            expect.arrayContaining(['p-4', 'sm:p-6', 'lg:p-8']),
        );
    });

    it('resumes pay code issuance with the onboarding reference payload', async () => {
        const wrapper = mount(CreatePage);

        await wrapper.find('[data-testid="set-valid-form"]').trigger('click');
        await nextTick();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Generate Pay Code'))
            ?.trigger('click');
        await nextTick();
        await Promise.resolve();
        await nextTick();

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Continue setup'))
            ?.trigger('click');
        await nextTick();
        await Promise.resolve();
        await nextTick();

        const lastGenerateCall = vi.mocked(global.fetch).mock.calls.at(-1);
        const request = lastGenerateCall?.[1] as RequestInit | undefined;
        const body = JSON.parse(String(request?.body ?? '{}'));

        expect(lastGenerateCall?.[0]).toBe('/api/x/v1/pay-codes');
        expect(body.onboarding.reference).toBe('onb-issue-123');
        expect(body.metadata.onboarding_reference).toBe('onb-issue-123');
        expect(routerVisit).toHaveBeenCalledWith('/x/pay-codes/PC-12345');
    });
});
