import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClaimSurfaceRenderer from '../../resources/js/components/x-change/ClaimSurfaceRenderer.vue';

vi.mock('@/components/x-change/IssuerClaimReviewPanel.vue', () => ({
    default: {
        props: ['headline', 'description', 'outcomePanel', 'requirementItems', 'payoutRoute', 'actions'],
        template: '<div data-testid="issuer-claim-review-panel-stub">{{ headline }}</div>',
    },
}));

vi.mock('@/components/x-change/PayCodeOutcomePanel.vue', () => ({
    default: {
        props: ['statusKey', 'statusLabel', 'code', 'formattedAmount', 'redeemedAt', 'payoutStatus'],
        template: '<div data-testid="pay-code-outcome-panel-stub">{{ statusLabel }}</div>',
    },
}));

describe('ClaimSurfaceRenderer', () => {
    it('renders nothing when no surface is provided', () => {
        const wrapper = mount(ClaimSurfaceRenderer, { props: { surface: null } });

        expect(wrapper.find('[data-testid="issuer-claim-review-panel-stub"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-surface-outcome"]').exists()).toBe(false);
    });

    it('renders nothing for a non-terminal public preview surface, deferring to the widget-owned preview flow', () => {
        const wrapper = mount(ClaimSurfaceRenderer, {
            props: {
                surface: {
                    visibility: 'public_preview',
                    headline: 'Ready to claim',
                    state: { terminal: false },
                    components: [{ type: 'xray_preview', props: {} }],
                    actions: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="issuer-claim-review-panel-stub"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-surface-outcome"]').exists()).toBe(false);
    });

    it('renders the calm outcome panel for a terminal public preview surface', () => {
        const wrapper = mount(ClaimSurfaceRenderer, {
            props: {
                surface: {
                    visibility: 'public_preview',
                    headline: 'Already claimed',
                    description: 'This Pay Code has already been fully claimed.',
                    state: { terminal: true },
                    components: [
                        { type: 'outcome_panel', props: { status_key: 'redeemed', status_label: 'Already claimed' } },
                    ],
                    actions: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="claim-surface-outcome"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Already claimed');
        expect(wrapper.find('[data-testid="pay-code-outcome-panel-stub"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="issuer-claim-review-panel-stub"]').exists()).toBe(false);
    });

    it('renders the issuer console for an issuer_console surface, regardless of terminal state', () => {
        const wrapper = mount(ClaimSurfaceRenderer, {
            props: {
                surface: {
                    visibility: 'issuer_console',
                    headline: 'Your Pay Code was claimed',
                    state: { terminal: false },
                    components: [
                        { type: 'claim_requirement_summary', props: { items: [] } },
                    ],
                    actions: [{ key: 'open_pay_code', label: 'Open Pay Code', href: '/x/pay-codes/ABC123' }],
                },
            },
        });

        expect(wrapper.find('[data-testid="issuer-claim-review-panel-stub"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="claim-surface-outcome"]').exists()).toBe(false);
    });
});
