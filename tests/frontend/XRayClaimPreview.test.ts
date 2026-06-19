import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XRayClaimPreview from '../../resources/js/components/x-change/XRayClaimPreview.vue';

vi.mock('lucide-vue-next', () => ({
    AlertCircle: {
        template: '<span />',
    },
}));

describe('XRayClaimPreview', () => {
    it('renders x-ray status, disclosures, requirements, and redaction notice', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    disclosures: [
                        {
                            key: 'status',
                            label: 'Status',
                            value: 'claimable',
                        },
                    ],
                    requirements: [
                        {
                            key: 'mobile',
                            label: 'Mobile',
                            description: 'Mobile number is required.',
                        },
                    ],
                    stages: [
                        {
                            type: 'message',
                            payload: {
                                message: 'Issuer preview message.',
                            },
                        },
                    ],
                    redactions: [
                        {
                            key: 'amount',
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Pay Code x-ray');
        expect(wrapper.text()).toContain('Claimable');
        expect(wrapper.text()).toContain('Status');
        expect(wrapper.text()).toContain('Mobile number is required.');
        expect(wrapper.text()).toContain('Issuer preview message.');
        expect(wrapper.text()).toContain('intentionally hidden');
    });

    it('renders inspection errors', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                error: 'Unable to inspect this Pay Code.',
            },
        });

        expect(wrapper.text()).toContain('Unable to inspect this Pay Code.');
    });
});
