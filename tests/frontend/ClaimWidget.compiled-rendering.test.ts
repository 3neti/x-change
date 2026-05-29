import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClaimWidget from '../../resources/js/components/x-change/ClaimWidget.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: () => ({
        code: '',
        processing: false,
        get: vi.fn(),
    }),
    usePage: () => ({
        props: {
            errors: {},
        },
    }),
}));

vi.mock('@/composables/useVoucherPreview', async () => {
    const { ref } = await vi.importActual<typeof import('vue')>('vue');

    return {
        useVoucherPreview: () => ({
            code: ref('TEST123'),
            loading: ref(false),
            error: ref(null),
            voucherData: ref({
                code: 'TEST123',
                status: 'active',
                instructions: {
                    rider: {
                        splash: '<h1>Legacy splash</h1>',
                    },
                },
                rider: {
                    stages: {
                        stages: [
                            {
                                key: 'legacy-rider-intro',
                                type: 'splash',
                                phase: 'pre_claim',
                                content: 'Legacy rider intro',
                            },
                        ],
                    },
                },
            }),
            showPreview: ref(true),
        }),
    };
});

vi.mock('@/composables/useTheme', () => ({
    initializeTheme: vi.fn(),
}));

vi.mock('@/components/AppLogoIcon.vue', () => ({
    default: {
        template: '<div data-testid="app-logo" />',
    },
}));

vi.mock('@/components/ui/input', () => ({
    Input: {
        template: '<input />',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        template: '<button><slot /></button>',
    },
}));

vi.mock('@/components/ui/label', () => ({
    Label: {
        template: '<label><slot /></label>',
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

vi.mock('@/components/ui/card', () => ({
    Card: {
        template: '<div><slot /></div>',
    },
    CardContent: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('@/components/ui/tabs', () => ({
    Tabs: {
        template: '<div><slot /></div>',
    },
    TabsContent: {
        template: '<div><slot /></div>',
    },
    TabsList: {
        template: '<div><slot /></div>',
    },
    TabsTrigger: {
        template: '<button><slot /></button>',
    },
}));

vi.mock('@/components/ui/spinner', () => ({
    Spinner: {
        template: '<span />',
    },
}));

vi.mock('@/components/InputError.vue', () => ({
    default: {
        template: '<div />',
    },
}));

vi.mock('@/components/x-change/VoucherInstructionsDisplay.vue', () => ({
    default: {
        template: '<div />',
    },
}));

vi.mock('@/components/x-change/VoucherMetadataDisplay.vue', () => ({
    default: {
        template: '<div />',
    },
}));

vi.mock('@/components/x-change/VoucherStatusStamp.vue', () => ({
    default: {
        template: '<div />',
    },
}));

vi.mock('@/components/x-rider/RiderRuntimeSequencer.vue', () => ({
    default: {
        props: ['stages'],
        template: `
            <div data-testid="rider-runtime">
                <span
                    v-for="stage in stages"
                    :key="stage.key"
                    data-testid="runtime-stage"
                >
                    {{ stage.key }}
                </span>
            </div>
        `,
    },
}));

vi.mock('@/components/x-rider/useRiderStagePhase', () => ({
    stageIsInPhase: (stage: any, phase: string) =>
        stage.phase === phase || stage.phases?.includes?.(phase),
}));

vi.mock('lucide-vue-next', () => ({
    AlertCircle: {
        template: '<span />',
    },
}));

describe('ClaimWidget compiled rendering', () => {
    it('prefers compiled rider intro stages over legacy rider splash stages', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'rider_intro',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [
                                {
                                    key: 'compiled-rider-intro',
                                    type: 'splash',
                                    phase: 'pre_claim',
                                    content: 'Compiled rider intro',
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-runtime"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').text()).toBe('compiled-rider-intro');

        expect(wrapper.text()).not.toContain('legacy-rider-intro');
        expect(wrapper.text()).not.toContain('legacy-splash');
    });

    it('falls back to legacy voucher instruction splash when compiled rider intro is absent', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'voucher-redemption.yaml',
                            status: 'active',
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-runtime"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').text()).toBe('legacy-splash');

        expect(wrapper.text()).not.toContain('compiled-rider-intro');
    });
});
