import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClaimWidget from '../../resources/js/components/x-change/ClaimWidget.vue';

vi.mock('@inertiajs/vue3', () => ({
    useForm: () => ({
        code: '',
        processing: false,
        post: vi.fn(),
    }),
    usePage: () => ({
        props: {
            errors: {},
        },
    }),
}));

vi.mock('@/composables/useTheme', () => ({
    initializeTheme: vi.fn(),
}));

vi.mock('@/composables/useVoucherPreview', () => ({
    useVoucherPreview: () => ({
        code: { value: 'TEST123' },
        loading: { value: false },
        error: { value: null },
        voucherData: {
            value: {
                code: 'TEST123',
                status: 'active',
                preview: {},
                instructions: {},
                metadata: {},
            },
        },
        showPreview: { value: false },
    }),
}));

vi.mock('@/components/AppLogoIcon.vue', () => ({
    default: {
        template: '<div data-testid="app-logo" />',
    },
}));

vi.mock('@/components/InputError.vue', () => ({
    default: {
        props: ['message'],
        template: '<div data-testid="input-error">{{ message }}</div>',
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

vi.mock('@/components/ui/button', () => ({
    Button: {
        props: ['disabled'],
        template: '<button :disabled="disabled"><slot /></button>',
    },
}));

vi.mock('@/components/ui/card', () => ({
    Card: {
        template: '<div data-testid="card"><slot /></div>',
    },
    CardContent: {
        template: '<div data-testid="card-content"><slot /></div>',
    },
}));

vi.mock('@/components/ui/input', () => ({
    Input: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: `
            <input
                :value="modelValue"
                @input="$emit('update:modelValue', $event.target.value)"
            />
        `,
    },
}));

vi.mock('@/components/ui/label', () => ({
    Label: {
        template: '<label><slot /></label>',
    },
}));

vi.mock('@/components/ui/spinner', () => ({
    Spinner: {
        template: '<div data-testid="spinner" />',
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

vi.mock('@/components/x-change/VoucherInstructionsDisplay.vue', () => ({
    default: {
        template: '<div data-testid="voucher-instructions" />',
    },
}));

vi.mock('@/components/x-change/VoucherMetadataDisplay.vue', () => ({
    default: {
        template: '<div data-testid="voucher-metadata" />',
    },
}));

vi.mock('@/components/x-change/VoucherStatusStamp.vue', () => ({
    default: {
        template: '<div data-testid="voucher-status-stamp" />',
    },
}));

vi.mock('@/components/x-rider/RiderRuntimeSequencer.vue', () => ({
    default: {
        props: ['stages'],
        template: '<div data-testid="rider-runtime">{{ stages?.length ?? 0 }}</div>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    AlertCircle: {
        template: '<span data-testid="alert-circle" />',
    },
}));

vi.mock('@/components/x-change/FormFlowRenderer.vue', () => ({
    default: {
        props: ['formFlow'],
        emits: ['update:values'],
        template: `
            <div data-testid="form-flow-renderer">
                <div data-testid="form-flow-field-count">{{ formFlow?.fields?.length ?? 0 }}</div>
                <button
                    data-testid="form-flow-update"
                    type="button"
                    @click="$emit('update:values', { first_name: 'Lester' })"
                >
                    Update
                </button>
            </div>
        `,
    },
}));

const claimExperienceWithCompiledForm = {
    phases: [
        {
            key: 'form_flow',
            owner: 'claim-widget',
            status: 'active',
            fields: [
                {
                    name: 'first_name',
                    label: 'First name',
                    type: 'text',
                    required: true,
                },
            ],
        },
    ],
};

describe('ClaimWidget direct compiled form rendering', () => {
    it('renders compiled form flow boundary when compiled form exists', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: claimExperienceWithCompiledForm,
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="compiled-form-flow-visible-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="form-flow-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="form-flow-field-count"]').text()).toBe('1');
    });

    it('renders compiled form directly when form flow phase is owned by claim widget', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'claim-widget',
                            status: 'active',
                            fields: [
                                {
                                    name: 'first_name',
                                    label: 'First name',
                                    type: 'text',
                                    required: true,
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="compiled-form-flow-visible-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="form-flow-renderer"]').exists()).toBe(true);
    });

    it('does not render compiled form directly when form flow phase is owned by form-flow', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: {
                    phases: [
                        {
                            key: 'form_flow',
                            owner: 'form-flow',
                            status: 'active',
                            fields: [
                                {
                                    name: 'first_name',
                                    label: 'First name',
                                    type: 'text',
                                    required: true,
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.find('[data-testid="compiled-form-flow-visible-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="form-flow-renderer"]').exists()).toBe(false);
    });

    it('disables submit while required compiled form fields are missing', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: claimExperienceWithCompiledForm,
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').attributes('disabled')).toBeDefined();
    });

    it('emits compiled form value updates from direct renderer', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: claimExperienceWithCompiledForm,
            },
        });

        await wrapper.find('[data-testid="form-flow-update"]').trigger('click');

        expect(wrapper.emitted('update:compiled-form-values')?.[0]).toEqual([
            {
                first_name: 'Lester',
            },
        ]);
    });

    it('keeps submit blocked until compiled form is valid', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: claimExperienceWithCompiledForm,
            },
        });

        await wrapper.find('[data-testid="form-flow-update"]').trigger('click');
        await wrapper.find('form').trigger('submit.prevent');

        expect(wrapper.emitted('submit:compiled-form')).toBeUndefined();
    });

    it('renders compiled form submit error when provided', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: claimExperienceWithCompiledForm,
                compiledFormSubmitError: 'Compiled claim failed.',
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-submit-error"]').text())
            .toBe('Compiled claim failed.');
    });
});
