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
});
