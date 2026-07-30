import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AuthRequired from '../../resources/js/pages/x-change/claim/AuthRequired.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
}));

vi.mock('lucide-vue-next', () => ({
    ShieldCheck: { template: '<span data-testid="shield-check-icon" />' },
}));

vi.mock('@/components/x-change/ClaimStepShell.vue', () => ({
    default: {
        template: '<main data-testid="claim-step-shell"><slot /></main>',
    },
}));

vi.mock('@/components/ui/button', () => ({
    Button: {
        template: '<div data-testid="button"><slot /></div>',
    },
}));

describe('claim authorization required page', () => {
    it('explains campaign officer authorization before login', () => {
        const wrapper = mount(AuthRequired, {
            props: {
                code: 'AUTH1',
                login_url: '/login',
                claim_url: '/x/claim/AUTH1',
                intent: {
                    type: 'campaign_authorization',
                    code: 'AUTH1',
                    title: 'Officer authorization required',
                    description:
                        'Sign in with the campaign officer account authorized to approve this worksheet.',
                    intended_url: '/x/claim/AUTH1',
                },
                workflow: {
                    key: 'campaign.officer-authorization.v1',
                    title: 'Campaign Officer Authorization',
                    description:
                        'Review the frozen worksheet for 1 beneficiary totaling 125.00 PHP. No payout will be sent by this approval.',
                    review: {
                        beneficiary_count: 1,
                        principal_minor: 12500,
                        currency: 'PHP',
                    },
                },
            },
        });

        expect(wrapper.get('[data-testid="claim-auth-required-page"]').text()).toContain(
            'Officer authorization required',
        );
        expect(wrapper.text()).toContain('frozen campaign worksheet');
        expect(wrapper.text()).toContain('AUTH1');
        expect(wrapper.text()).toContain('does not issue Pay Codes');
        expect(wrapper.get('[data-testid="claim-auth-login"] a').attributes('href')).toBe(
            '/login',
        );
    });
});
