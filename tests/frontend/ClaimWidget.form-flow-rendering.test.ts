import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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

describe('ClaimWidget compiled form flow rendering', () => {
    it('does not render compiled form_flow directly yet', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [
                                {
                                    key: 'compiled-form-flow-stage',
                                    type: 'form',
                                    phase: 'form_flow',
                                    content: 'Compiled form flow stage',
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).not.toContain('compiled-form-flow-stage');
        expect(wrapper.find('[data-testid="compiled-form-flow-boundary"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="legacy-form-flow-boundary"]').exists()).toBe(false);
    });

    it('ignores inactive compiled form_flow phase', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'skipped',
                            stages: [
                                {
                                    key: 'compiled-form-flow-stage',
                                    type: 'form',
                                    phase: 'form_flow',
                                    content: 'Compiled form flow stage',
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).not.toContain('compiled-form-flow-stage');
        expect(wrapper.find('[data-testid="compiled-form-flow-boundary"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="legacy-form-flow-boundary"]').exists()).toBe(true);
    });

    it('uses legacy form flow boundary when compiled form_flow phase is absent', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'runtime',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="compiled-form-flow-boundary"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="legacy-form-flow-boundary"]').exists()).toBe(true);
    });

    it('renders form flow ownership markers inside a dedicated boundary region', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"] [data-testid="compiled-form-flow-boundary"]').exists()).toBe(true);
    });

    it('selects compiled form flow mode when active compiled form_flow exists', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="compiled-form-flow-boundary"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="legacy-form-flow-boundary"]').exists()).toBe(false);
    });

    it('hands active compiled form_flow phase to FormFlowRenderer placeholder', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="form-flow-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="compiled-form-flow-boundary"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="legacy-form-flow-boundary"]').exists()).toBe(false);
    });

    it('does not render FormFlowRenderer placeholder for legacy form flow mode', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="form-flow-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="compiled-form-flow-boundary"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="legacy-form-flow-boundary"]').exists()).toBe(true);
    });

    it('normalizes compiled form_flow phase before handoff to FormFlowRenderer', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'mobile',
                                    type: 'text',
                                    label: 'Mobile',
                                    required: true,
                                },
                            ],
                            stages: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="form-flow-renderer"]').exists()).toBe(true);
    });

    it('disables claim submit button when compiled form is invalid', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: '',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').attributes('disabled'))
            .toBeDefined();
    });

    it('enables claim submit button when compiled form is valid', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Lester',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').attributes('disabled'))
            .toBeUndefined();
    });

    it('enables claim submit button after required compiled form field is filled', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: '',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').attributes('disabled'))
            .toBeDefined();

        await wrapper
            .find('[data-testid="text-field-renderer-input"]')
            .setValue('Lester');

        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').attributes('disabled'))
            .toBeUndefined();
    });

    it('emits compiled form submit payload when valid compiled form is submitted', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Lester',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('idle');

        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('submit:compiled-form')?.[0]).toEqual([
            {
                code: 'TEST123',
                values: {
                    first_name: 'Lester',
                },
            },
        ]);

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('submitting');
    });

    it('does not emit compiled form submit payload when compiled form is invalid', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: '',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        await wrapper
            .find('[data-testid="claim-widget-submit-button"]')
            .trigger('click');

        expect(wrapper.emitted('submit:compiled-form')).toBeUndefined();
    });

    it('shows submitted state when compiled form submission is acknowledged', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                compiledFormSubmitted: false,
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Lester',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('idle');

        await wrapper.find('form').trigger('submit');

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('submitting');

        await wrapper.setProps({
            compiledFormSubmitted: true,
        });

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('submitted');
    });

    it('shows failed state when compiled form submission error is provided', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                compiledFormSubmitError: null,
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Lester',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        await wrapper.find('form').trigger('submit');

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('submitting');

        await wrapper.setProps({
            compiledFormSubmitError: 'Submission failed.',
        });

        expect(wrapper.find('[data-testid="claim-widget-submit-state"]').text()).toBe('failed');
        expect(wrapper.find('[data-testid="claim-widget-submit-error"]').text()).toBe('Submission failed.');
    });

    it('emits compiled form value updates to owner', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Initial Name',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        await nextTick();

        await wrapper
            .find('[data-testid="text-field-renderer-input"]')
            .setValue('Updated Name');

        expect(wrapper.emitted('update:compiled-form-values')?.at(-1)).toEqual([
            {
                first_name: 'Updated Name',
            },
        ]);
    });

    it('renders compiled form flow in a visible claim information region', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            source: 'claim_experience',
                            status: 'active',
                            fields: [
                                {
                                    key: 'first_name',
                                    type: 'text',
                                    label: 'First Name',
                                    required: true,
                                },
                            ],
                            values: {
                                first_name: 'Lester',
                            },
                            stages: [],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="compiled-form-flow-visible-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"]').classes())
            .not
            .toContain('sr-only');
    });

    it('keeps legacy form flow boundary visually hidden', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="compiled-form-flow-visible-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"]').classes())
            .toContain('sr-only');
    });
});
