import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Success from '../../resources/js/pages/x-change/claim/Success.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<slot />',
    },
    router: {
        visit: vi.fn(),
    },
}));

vi.mock('@/components/ui/card', () => ({
    Card: {
        name: 'Card',
        template: '<div data-testid="card"><slot /></div>',
    },
    CardContent: {
        name: 'CardContent',
        template: '<div data-testid="card-content"><slot /></div>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        name: 'Button',
        template: '<button data-testid="button"><slot /></button>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    CheckCircle2: {
        name: 'CheckCircle2',
        template: '<span data-testid="check-icon" />',
    },
}));

const baseProps = {
    voucher: {
        code: 'TEST123',
        amount: 100,
        currency: 'PHP',
    },
    claimOutcome: 'accepted_success',
    rider: {
        state: 'accepted_success',
        subject: {
            type: 'voucher',
            id: 'TEST123',
        },
        stages: {
            stages: [],
        },
    },
    redirectEndpoint: '/x/claim/TEST123/redirect',
    claim_experience: {
        version: 1,
        entry: {
            mode: 'form_first',
            initial_phase: 'pre_claim',
        },
        options: {
            show_redirect_countdown: true,
        },
        phases: [
            {
                key: 'redirect',
                owner: 'claim-widget',
                source: 'voucher.instructions.rider.redirect_url',
                url: 'https://example.com/after-claim',
                delay_seconds: 5,
                show_countdown: true,
            },
        ],
        consumed: {
            splash: false,
        },
        diagnostics: {
            redirect_owner: 'claim-widget',
        },
    },
    redirect: {
        show_countdown: true,
        owner: 'claim-widget',
        delay_seconds: 5,
    },
};

describe('claim Success redirect countdown rendering', () => {
    it('renders RiderCountdown when compiled redirect countdown is enabled', () => {
        const wrapper = mount(Success, {
            props: baseProps,
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-delay"]').text()).toBe('5');
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).toBe('/x/claim/TEST123/redirect');
    });

    it('does not render RiderCountdown when compiled redirect countdown is disabled', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                redirect: {
                    show_countdown: false,
                    owner: 'claim-widget',
                    delay_seconds: 5,
                },
                claim_experience: {
                    ...baseProps.claim_experience,
                    options: {
                        show_redirect_countdown: false,
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(false);
    });

    it('does not render RiderCountdown when redirect endpoint is missing', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                redirectEndpoint: null,
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(false);
    });

    it('lets rider redirect take precedence over compiled redirect', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    redirect: {
                        enabled: true,
                        url: 'https://rider.example.com/raw-target',
                        delay_seconds: 9,
                        content: 'Rider redirect wins',
                    },
                },
                redirect: {
                    show_countdown: true,
                    owner: 'claim-widget',
                    delay_seconds: 5,
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-delay"]').text()).toBe('9');
    });

    it('passes redirectEndpoint to RiderCountdown instead of raw rider url', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    redirect: {
                        enabled: true,
                        url: 'https://rider.example.com/raw-target',
                        delay_seconds: 9,
                        content: 'Rider redirect wins',
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).toBe('/x/claim/TEST123/redirect');
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).not.toBe('https://rider.example.com/raw-target');
    });

    it('passes the redirect gate endpoint to RiderCountdown for final navigation', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    redirect: {
                        enabled: true,
                        url: 'https://example.com/raw-rider-url',
                        timeout: 5,
                    },
                },
                redirectEndpoint: '/x/claim/TEST123/redirect',
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).toBe('/x/claim/TEST123/redirect');
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).not.toBe('https://example.com/raw-rider-url');
    });

    it('renders success visual stages together with redirect countdown when both exist', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'success-message',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Claim complete',
                                },
                            },
                        ],
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
    });

    it('renders countdown when there are no success visual stages', () => {
        const wrapper = mount(Success, {
            props: baseProps,
        });

        expect(wrapper.find('[data-testid="rider-stage"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
    });

    it('renders fallback success message when no stages and no countdown exist', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                redirect: {
                    show_countdown: false,
                    owner: null,
                    delay_seconds: null,
                },
                claim_experience: {
                    ...baseProps.claim_experience,
                    options: {
                        show_redirect_countdown: false,
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-stage"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Disbursed to your account');
    });

    // Product decision:
// Success rider stages and redirect countdown render together.
// Success stages communicate outcome; countdown communicates upcoming navigation.
    it('renders success visual stages together with redirect countdown when both exist', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'success-message',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Claim complete',
                                },
                            },
                        ],
                    },
                },
                redirect: {
                    show_countdown: true,
                    owner: 'claim-widget',
                    delay_seconds: 5,
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-delay"]').text()).toBe('5');
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).toBe('/x/claim/TEST123/redirect');
    });

    it('does not let success visual stages suppress redirect countdown', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'success-message',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Claim complete',
                                },
                            },
                        ],
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-stage"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
    });

    it('does not render compiled countdown when redirect owner is not claim-widget', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                redirect: {
                    show_countdown: true,
                    owner: 'x-rider',
                    delay_seconds: 5,
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(false);
    });

    it('renders success rider stages inside a dedicated success stage region', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'success-message',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Claim complete',
                                },
                            },
                        ],
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="success-stage-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="success-stage-region"] [data-testid="rider-stage"]').exists()).toBe(true);
    });

    it('renders redirect countdown inside a dedicated countdown region', () => {
        const wrapper = mount(Success, {
            props: baseProps,
        });

        expect(wrapper.find('[data-testid="redirect-countdown-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="redirect-countdown-region"] [data-testid="rider-countdown"]').exists()).toBe(true);
    });

    it('renders fallback success region only when there are no success stages and no countdown', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                redirect: {
                    show_countdown: false,
                    owner: null,
                    delay_seconds: null,
                },
                claim_experience: {
                    ...baseProps.claim_experience,
                    options: {
                        show_redirect_countdown: false,
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="fallback-success-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="success-stage-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="redirect-countdown-region"]').exists()).toBe(false);
    });

    it('prefers compiled success_rider stages over rider success fallback stages', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                claim_experience: {
                    ...baseProps.claim_experience,
                    phases: [
                        {
                            key: 'success_rider',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [
                                {
                                    key: 'compiled-success-rider-stage',
                                    type: 'message',
                                    phase: 'success',
                                    payload: {
                                        title: 'Compiled success rider',
                                    },
                                },
                            ],
                        },
                    ],
                },
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'legacy-success-rider-stage',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Legacy success rider',
                                },
                            },
                        ],
                    },
                },
            },
        });

        const stages = wrapper
            .findAll('[data-testid="rider-stage"]')
            .map((stage) => stage.text());

        expect(stages).toContain('compiled-success-rider-stage');
        expect(stages).not.toContain('legacy-success-rider-stage');
    });

    it('falls back to rider success stages when compiled success_rider phase is absent', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                claim_experience: {
                    ...baseProps.claim_experience,
                    phases: [],
                },
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'legacy-success-rider-stage',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Legacy success rider',
                                },
                            },
                        ],
                    },
                },
            },
        });

        const stages = wrapper
            .findAll('[data-testid="rider-stage"]')
            .map((stage) => stage.text());

        expect(stages).toContain('legacy-success-rider-stage');
        expect(stages).not.toContain('compiled-success-rider-stage');
    });

    it('ignores inactive compiled success_rider phase and falls back to rider success stages', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                claim_experience: {
                    ...baseProps.claim_experience,
                    phases: [
                        {
                            key: 'success_rider',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'skipped',
                            stages: [
                                {
                                    key: 'compiled-success-rider-stage',
                                    type: 'message',
                                    phase: 'success',
                                    payload: {
                                        title: 'Compiled success rider',
                                    },
                                },
                            ],
                        },
                    ],
                },
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'legacy-success-rider-stage',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Legacy success rider',
                                },
                            },
                        ],
                    },
                },
            },
        });

        const stages = wrapper
            .findAll('[data-testid="rider-stage"]')
            .map((stage) => stage.text());

        expect(stages).toContain('legacy-success-rider-stage');
        expect(stages).not.toContain('compiled-success-rider-stage');
    });

    it('renders compiled success rider and compiled redirect countdown together', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                claim_experience: {
                    ...baseProps.claim_experience,
                    phases: [
                        {
                            key: 'success_rider',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'active',
                            stages: [
                                {
                                    key: 'compiled-success-rider-stage',
                                    type: 'message',
                                    phase: 'success',
                                    payload: {
                                        title: 'Compiled success rider',
                                    },
                                },
                            ],
                        },
                        {
                            key: 'redirect',
                            owner: 'claim-widget',
                            source: 'voucher.instructions.rider.redirect_url',
                            status: 'active',
                            url: 'https://example.com/after-claim',
                            delay_seconds: 5,
                            show_countdown: true,
                        },
                    ],
                },
                rider: {
                    ...baseProps.rider,
                    stages: {
                        stages: [
                            {
                                key: 'legacy-success-rider-stage',
                                type: 'message',
                                phase: 'success',
                                payload: {
                                    title: 'Legacy success rider',
                                },
                            },
                        ],
                    },
                },
                redirect: {
                    show_countdown: true,
                    owner: 'claim-widget',
                    delay_seconds: 5,
                },
            },
        });

        const stages = wrapper
            .findAll('[data-testid="rider-stage"]')
            .map((stage) => stage.text());

        expect(stages).toContain('compiled-success-rider-stage');
        expect(stages).not.toContain('legacy-success-rider-stage');

        expect(wrapper.find('[data-testid="success-stage-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="redirect-countdown-region"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-delay"]').text()).toBe('5');
    });

    it('does not render redirect countdown when compiled redirect is owned by x-rider', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                claim_experience: {
                    ...baseProps.claim_experience,
                    phases: [
                        {
                            key: 'redirect',
                            owner: 'x-rider',
                            source: 'claim_experience',
                            status: 'active',
                            url: 'https://example.com/after-claim',
                            delay_seconds: 5,
                            show_countdown: true,
                        },
                    ],
                },
                redirect: {
                    show_countdown: true,
                    owner: 'x-rider',
                    delay_seconds: 5,
                },
            },
        });

        expect(wrapper.find('[data-testid="redirect-countdown-region"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(false);
    });

    it('normalizes redirect countdown delay from compiled redirect payload', () => {
        const wrapper = mount(Success, {
            props: {
                ...baseProps,
                redirect: {
                    show_countdown: true,
                    owner: 'claim-widget',
                    delay_seconds: 12,
                },
            },
        });

        expect(wrapper.find('[data-testid="rider-countdown"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="countdown-delay"]').text()).toBe('12');
        expect(wrapper.find('[data-testid="countdown-endpoint"]').text()).toBe('/x/claim/TEST123/redirect');
    });
});

