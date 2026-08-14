import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ClaimRequirementSummary from '../../resources/js/components/x-change/ClaimRequirementSummary.vue';

describe('ClaimRequirementSummary', () => {
    it('renders a friendly status badge per requirement item', () => {
        const wrapper = mount(ClaimRequirementSummary, {
            props: {
                items: [
                    { key: 'mobile', label: 'Mobile number', status: 'completed', tone: 'positive', description: null },
                    { key: 'destination_account', label: 'Destination account', status: 'completed', tone: 'positive', description: null },
                    { key: 'selfie', label: 'Selfie', status: 'captured', tone: 'neutral', description: null },
                    { key: 'approval', label: 'Approval', status: 'pending', tone: 'warning', description: null },
                ],
            },
        });

        expect(wrapper.find('[data-testid="claim-requirement-summary-item-mobile"]').text()).toContain('Mobile number');
        expect(wrapper.find('[data-testid="claim-requirement-summary-item-mobile"]').text()).toContain('Completed');
        expect(wrapper.find('[data-testid="claim-requirement-summary-item-approval"]').text()).toContain('Pending');
    });

    it('never renders a description, since requirement rows are summary-only', () => {
        const wrapper = mount(ClaimRequirementSummary, {
            props: {
                items: [
                    { key: 'selfie', label: 'Selfie', status: 'captured', tone: 'neutral', description: 'this should never appear' },
                ],
            },
        });

        expect(wrapper.text()).not.toContain('this should never appear');
    });

    it('shows a retained image preview while a captured badge is pressed', async () => {
        const wrapper = mount(ClaimRequirementSummary, {
            props: {
                items: [
                    {
                        key: 'selfie',
                        label: 'Selfie',
                        status: 'captured',
                        tone: 'positive',
                        description: null,
                        preview: {
                            type: 'image',
                            href: '/x/cockpit/pay-codes/ABCD/evidence/claim/123',
                            label: 'Selfie preview',
                        },
                    },
                ],
            },
        });

        const trigger = wrapper.find('[data-testid="claim-requirement-preview-trigger-selfie"]');

        expect(wrapper.find('[data-testid="claim-requirement-image-preview-selfie"]').exists()).toBe(false);

        await trigger.trigger('pointerdown');

        const preview = wrapper.find('[data-testid="claim-requirement-image-preview-selfie"]');
        const image = preview.find('img');

        expect(preview.exists()).toBe(true);
        expect(image.attributes('src')).toBe('/x/cockpit/pay-codes/ABCD/evidence/claim/123');
        expect(image.attributes('alt')).toBe('Selfie preview');

        await trigger.trigger('pointerup');

        expect(wrapper.find('[data-testid="claim-requirement-image-preview-selfie"]').exists()).toBe(false);
    });

    it('does not turn plain captured statuses into preview triggers', () => {
        const wrapper = mount(ClaimRequirementSummary, {
            props: {
                items: [
                    { key: 'location', label: 'Location', status: 'captured', tone: 'positive', description: null },
                ],
            },
        });

        expect(wrapper.find('[data-testid="claim-requirement-preview-trigger-location"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="claim-requirement-summary-item-location"]').text()).toContain('Captured');
    });
});
