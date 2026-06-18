import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClaimEntryPage from '../../resources/js/pages/x-change/claim/Entry.vue';

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

vi.mock('@/components/x-change/ClaimWidget.vue', () => ({
    default: {
        template: '<div data-testid="claim-widget" />',
    },
}));

vi.mock('@/composables/useXChangeRoutes', () => ({
    useXChangeRoutes: () => ({
        claim: {
            start: () => '/x/claim',
        },
    }),
}));

vi.mock('@/components/ui/alert', () => ({
    Alert: {
        template: '<div><slot /></div>',
    },
    AlertDescription: {
        template: '<div><slot /></div>',
    },
}));

vi.mock('lucide-vue-next', () => ({
    ArrowRight: { template: '<span />' },
    CheckCircle2: { template: '<span />' },
    Loader2: { template: '<span />' },
    RefreshCcw: { template: '<span />' },
    ShieldAlert: { template: '<span />' },
}));

describe('ClaimEntryPage', () => {
    it('renders provisioning guidance from the page descriptor prop and hides the claim widget', () => {
        routerVisit.mockReset();

        const wrapper = mount(ClaimEntryPage, {
            props: {
                initial_code: 'TEST123',
                provisioning_requirement: {
                    provider: 'netbank',
                    mode: 'bank_account_link',
                    reason: 'Bank account readiness is missing.',
                    onboarding: {
                        reference: 'onb-claim-123',
                    },
                    descriptor: {
                        title: 'Add payout destination',
                        description: 'Complete your payout destination setup before continuing.',
                        steps: ['bank_account', 'consent', 'ready'],
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Add payout destination');
        expect(wrapper.find('[data-testid="claim-widget"]').exists()).toBe(false);
    });

    it('resumes the guarded claim flow with the onboarding reference path', async () => {
        routerVisit.mockReset();

        const wrapper = mount(ClaimEntryPage, {
            props: {
                initial_code: 'TEST123',
                provisioning_requirement: {
                    onboarding: {
                        reference: 'onb-claim-123',
                    },
                    descriptor: {
                        title: 'Add payout destination',
                    },
                },
            },
        });

        await wrapper.findAll('button').find((button) => button.text().includes('Continue setup'))?.trigger('click');

        expect(routerVisit).toHaveBeenCalledWith('/x/claim?code=TEST123&onboarding_reference=onb-claim-123');
    });
});
