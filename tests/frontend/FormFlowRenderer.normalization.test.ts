import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormFlowRenderer from '../../resources/js/components/x-change/FormFlowRenderer.vue';

describe('FormFlowRenderer normalized payload rendering', () => {
    it('renders normalized form flow metadata', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        { key: 'mobile' },
                        { key: 'email' },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="form-flow-key"]').text()).toBe('form_flow');
        expect(wrapper.find('[data-testid="form-flow-owner"]').text()).toBe('form-flow');
        expect(wrapper.find('[data-testid="form-flow-source"]').text()).toBe('claim_experience');
        expect(wrapper.find('[data-testid="form-flow-field-count"]').text()).toBe('2');
        expect(wrapper.find('[data-testid="form-flow-stage-count"]').text()).toBe('0');
    });
});
