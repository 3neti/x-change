import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ClaimSurfaceRenderer from '../../resources/js/components/x-change/ClaimSurfaceRenderer.vue';

vi.mock('@/components/x-change/IssuerClaimReviewPanel.vue', () => ({
    default: { template: '<div data-testid="issuer-claim-review-panel-stub" />' },
}));

vi.mock('@/components/x-change/ClaimExperienceSummary.vue', () => ({
    default: { template: '<div data-testid="claim-experience-summary-stub" />' },
}));

vi.mock('@/components/x-change/PayCodeOutcomePanel.vue', () => ({
    default: { template: '<div data-testid="pay-code-outcome-panel-stub" />' },
}));

describe('canonical campaign payout recovery claim surface', () => {
    it('keeps recovery inside the ordinary non-terminal claim flow', () => {
        const wrapper = mount(ClaimSurfaceRenderer, {
            props: {
                surface: {
                    visibility: 'public_preview',
                    headline: 'Ready to claim',
                    state: {
                        key: 'active',
                        label: 'Ready to claim',
                        can_claim: true,
                        terminal: false,
                    },
                    components: [
                        {
                            type: 'xray_preview',
                            props: {
                                status: 'claimable',
                                requirements: [
                                    { key: 'mobile', required: true },
                                    { key: 'otp', required: true },
                                    { key: 'assigned_mobile', required: true },
                                ],
                                next_actions: [
                                    { key: 'claim', label: 'Start claim', url: '/x/claim?code=CAMP-SAFE' },
                                ],
                            },
                        },
                    ],
                    actions: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="claim-surface-outcome"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="pay-code-outcome-panel-stub"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="issuer-claim-review-panel-stub"]').exists()).toBe(false);
    });
});
