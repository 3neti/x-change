import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
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

// Captured so tests can simulate the visitor typing a different code after
// mount -- `code` starts out equal to `initialCode` (ClaimWidget seeds it on
// mount), so divergence can only be observed by mutating this ref later.
let capturedCodeRef: { value: string } | null = null;

vi.mock('@/composables/useVoucherPreview', async () => {
    const { ref } = await vi.importActual<typeof import('vue')>('vue');

    return {
        useVoucherPreview: () => {
            const codeRef = ref('TEST123');
            capturedCodeRef = codeRef;

            return {
                code: codeRef,
                loading: ref(false),
                error: ref(null),
                voucherData: ref({
                    code: 'TEST123',
                    status: 'active',
                    instructions: {},
                    rider: { stages: { stages: [] } },
                }),
                showPreview: ref(true),
            };
        },
    };
});

vi.mock('@/composables/useTheme', () => ({
    initializeTheme: vi.fn(),
}));

vi.mock('@/components/x-change/PayCodeLogo.vue', () => ({
    default: { template: '<img />' },
}));

vi.mock('@/components/x-change/ClaimSurfaceRenderer.vue', () => ({
    default: {
        props: ['surface'],
        template:
            '<div data-testid="claim-surface-renderer-stub">{{ surface?.visibility }}</div>',
    },
}));

vi.mock('@/components/ui/input', () => ({
    Input: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template:
            '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: { template: '<button><slot /></button>' },
}));

vi.mock('@/components/ui/label', () => ({
    Label: { template: '<label><slot /></label>' },
}));

vi.mock('@/components/ui/alert', () => ({
    Alert: { template: '<div><slot /></div>' },
    AlertDescription: { template: '<div><slot /></div>' },
}));

vi.mock('@/components/ui/card', () => ({
    Card: { template: '<div><slot /></div>' },
    CardContent: { template: '<div><slot /></div>' },
}));

vi.mock('@/components/ui/spinner', () => ({
    Spinner: { template: '<span />' },
}));

vi.mock('@/components/InputError.vue', () => ({
    default: { template: '<div />' },
}));

vi.mock('@/components/x-change/VoucherStatusStamp.vue', () => ({
    default: { template: '<div data-testid="voucher-status-stamp-stub" />' },
}));

vi.mock('@/components/x-change/XRayClaimPreview.vue', () => ({
    default: { template: '<div />' },
}));

vi.mock('@/components/x-rider/RiderRuntimeSequencer.vue', () => ({
    default: {
        props: ['stages'],
        template:
            '<div data-testid="rider-runtime">{{ stages?.map((stage) => stage.key).join(",") }}</div>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    AlertCircle: { template: '<span />' },
}));

const issuerConsoleSurface = {
    visibility: 'issuer_console',
    headline: 'Your Pay Code was claimed',
    description: 'Review the submitted claim requirements and payout status.',
    state: { key: 'redeemed', label: 'Redeemed', can_claim: false, terminal: true },
    components: [],
    actions: [],
};

const terminalPublicSurface = {
    visibility: 'public_preview',
    headline: 'Already claimed',
    description: 'This Pay Code has already been fully claimed.',
    state: { key: 'redeemed', label: 'Redeemed', can_claim: false, terminal: true },
    components: [
        { type: 'outcome_panel', props: { status_key: 'redeemed', status_label: 'Already claimed' } },
    ],
    actions: [],
};

describe('ClaimWidget claim surface gating', () => {
    it('hides the claim form and renders the issuer console when the surface resolves to issuer_console for the initial code', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: null,
                claimSurface: issuerConsoleSurface,
            },
        });

        const region = wrapper.find('[data-testid="claim-widget-surface-region"]');
        expect(region.exists()).toBe(true);
        expect(region.text()).toBe('issuer_console');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('keeps the ordinary claim form when no claim surface is provided', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: null,
                claimSurface: null,
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-surface-region"]').exists()).toBe(false);
        expect(wrapper.find('form').exists()).toBe(true);
    });

    it('lets a terminal public surface take over before the client preview fetch classifies the voucher', () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: null,
                claimSurface: terminalPublicSurface,
            },
        });

        const region = wrapper.find('[data-testid="claim-widget-surface-region"]');
        expect(region.exists()).toBe(true);
        expect(region.text()).toContain('public_preview');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('suppresses rider and form-flow regions when an issuer surface takes over', () => {
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
                            stages: [
                                {
                                    key: 'owner-splash',
                                    type: 'message',
                                    phase: 'runtime',
                                    content: 'Owner supplied splash',
                                },
                            ],
                        },
                        {
                            key: 'form_flow',
                            owner: 'claim-widget',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [],
                        },
                    ],
                },
                claimSurface: issuerConsoleSurface,
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-surface-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="claim-widget-runtime-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Owner supplied splash');
    });

    it('suppresses rider and form-flow regions when a terminal public surface takes over', () => {
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
                            stages: [
                                {
                                    key: 'terminal-splash',
                                    type: 'message',
                                    phase: 'runtime',
                                    content: 'Terminal splash',
                                },
                            ],
                        },
                        {
                            key: 'redirect',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [
                                {
                                    key: 'terminal-redirect',
                                    type: 'message',
                                    phase: 'redirect',
                                    content: 'Terminal redirect',
                                },
                            ],
                        },
                        {
                            key: 'form_flow',
                            owner: 'claim-widget',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [],
                        },
                    ],
                },
                claimSurface: terminalPublicSurface,
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-surface-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="claim-widget-runtime-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-widget-redirect-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-widget-form-flow-boundary-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-widget-submit-button"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Terminal splash');
        expect(wrapper.text()).not.toContain('Terminal redirect');
    });

    it('stops trusting the resolved surface once the visitor types a different code', async () => {
        const wrapper = mount(ClaimWidget, {
            props: {
                initialCode: 'TEST123',
                claimExperience: null,
                claimSurface: issuerConsoleSurface,
            },
        });

        expect(wrapper.find('[data-testid="claim-widget-surface-region"]').exists()).toBe(true);

        capturedCodeRef!.value = 'DIFFERENT1';
        await nextTick();

        expect(wrapper.find('[data-testid="claim-widget-surface-region"]').exists()).toBe(false);
    });
});
