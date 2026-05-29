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

let voucherPreviewFixture: any;

vi.mock('@/composables/useVoucherPreview', async () => {
    const { ref } = await vi.importActual<typeof import('vue')>('vue');

    return {
        useVoucherPreview: () => ({
            code: ref('TEST123'),
            loading: ref(false),
            error: ref(null),
            voucherData: ref(voucherPreviewFixture),
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
    beforeEach(() => {
        voucherPreviewFixture = {
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
        };
    });

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

    it('renders compiled rider intro even when voucher preview has no legacy rider stages', () => {
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
            global: {
                provide: {
                    voucherPreviewOverride: {
                        code: 'TEST123',
                        status: 'active',
                        instructions: {},
                        rider: {
                            stages: {
                                stages: [],
                            },
                        },
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-runtime"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').text()).toBe('compiled-rider-intro');

        expect(wrapper.text()).not.toContain('legacy-splash');
        expect(wrapper.text()).not.toContain('legacy-rider-intro');
    });

    it('ignores inactive compiled rider intro and falls back to legacy splash', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'rider_intro',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'skipped',
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
        expect(wrapper.find('[data-testid="runtime-stage"]').text()).toBe('legacy-splash');

        expect(wrapper.text()).not.toContain('compiled-rider-intro');
    });

    it('ignores non-visual compiled rider intro stages and falls back to legacy splash', () => {
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
                                    key: 'compiled-action-stage',
                                    type: 'redirect',
                                    phase: 'pre_claim',
                                    content: 'Compiled non-visual action',
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-runtime"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="runtime-stage"]').text()).toBe('legacy-splash');

        expect(wrapper.text()).not.toContain('compiled-action-stage');
    });

    it('renders multiple compiled rider intro visual stages in order', () => {
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
                                    key: 'compiled-intro-image',
                                    type: 'image',
                                    phase: 'pre_claim',
                                    content: 'Compiled intro image',
                                },
                                {
                                    key: 'compiled-intro-message',
                                    type: 'message',
                                    phase: 'pre_claim',
                                    content: 'Compiled intro message',
                                },
                            ],
                        },
                    ],
                },
            },
        });

        const stages = wrapper
            .findAll('[data-testid="runtime-stage"]')
            .map((stage) => stage.text());

        expect(stages).toEqual([
            'compiled-intro-image',
            'compiled-intro-message',
        ]);

        expect(wrapper.text()).not.toContain('legacy-splash');
        expect(wrapper.text()).not.toContain('legacy-rider-intro');
    });
});
