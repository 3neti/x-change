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
});
