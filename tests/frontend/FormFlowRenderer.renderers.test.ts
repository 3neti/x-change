import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormFlowRenderer from '../../resources/js/components/x-change/FormFlowRenderer.vue';

describe('FormFlowRenderer renderer delegation', () => {
    it('delegates text fields to TextFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'first_name',
                            type: 'text',
                            label: 'First Name',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-field-renderer-label"]').text()).toBe('First Name');
        expect(wrapper.find('[data-testid="text-field-renderer-kind"]').text()).toBe('text field');
        expect(wrapper.find('[data-testid="text-field-renderer-required"]').text()).toBe('required');
    });

    it('does not delegate unsupported fields to TextFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'photo',
                            type: 'camera',
                            label: 'Photo',
                            required: false,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('UnsupportedFieldRenderer');
    });

    it('delegates email fields to EmailFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'email',
                            type: 'email',
                            label: 'Email Address',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="email-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="email-field-renderer-label"]').text()).toBe('Email Address');
        expect(wrapper.find('[data-testid="email-field-renderer-kind"]').text()).toBe('email field');
        expect(wrapper.find('[data-testid="email-field-renderer-required"]').text()).toBe('required');

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
    });
});
