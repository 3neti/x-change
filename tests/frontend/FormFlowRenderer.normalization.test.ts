import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormFlowRenderer from '../../resources/js/components/x-change/FormFlowRenderer.vue';

describe('FormFlowRenderer normalized payload rendering', () => {
    it('renders normalized form flow field diagnostics', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'mobile',
                            type: 'text',
                            label: 'Mobile',
                            required: true,
                        },
                        {
                            key: 'email',
                            type: 'email',
                            label: 'Email',
                            required: false,
                        },
                    ],
                    stages: [],
                },
            },
        });

        const fields = wrapper.findAll('[data-testid="form-flow-field"]');

        expect(fields).toHaveLength(2);
        expect(fields[0].text()).toContain('mobile');
        expect(fields[0].text()).toContain('text');
        expect(fields[0].text()).toContain('Mobile');
        expect(fields[0].text()).toContain('required');

        expect(fields[1].text()).toContain('email');
        expect(fields[1].text()).toContain('email');
        expect(fields[1].text()).toContain('Email');
        expect(fields[1].text()).toContain('optional');
    });

    it('renders normalized fields as readonly preview rows', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'mobile',
                            type: 'text',
                            label: 'Mobile',
                            required: true,
                        },
                        {
                            key: 'birth_date',
                            type: 'date',
                            label: 'Birth Date',
                            required: false,
                        },
                    ],
                    stages: [],
                },
            },
        });

        const rows = wrapper.findAll('[data-testid="form-flow-field-preview-row"]');

        expect(rows).toHaveLength(2);

        expect(rows[0].text()).toContain('Mobile');
        expect(rows[0].text()).toContain('text');
        expect(rows[0].text()).toContain('required');

        expect(rows[1].text()).toContain('Birth Date');
        expect(rows[1].text()).toContain('date');
        expect(rows[1].text()).toContain('optional');

        expect(rows[0].text()).toContain('text field');
        expect(rows[1].text()).toContain('date field');

        expect(rows[0].text())
            .toContain('TextFieldRenderer');

        expect(rows[1].text())
            .toContain('DateFieldRenderer');
    });

    it('marks unsupported form flow field types explicitly', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'mobile',
                            type: 'text',
                            label: 'Mobile',
                            required: true,
                        },
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

        const diagnostics = wrapper.findAll('[data-testid="form-flow-field"]');
        const previews = wrapper.findAll('[data-testid="form-flow-field-preview-row"]');

        expect(diagnostics[0].text()).toContain('text');
        expect(previews[0].text()).toContain('text');

        expect(diagnostics[1].text()).toContain('unsupported');
        expect(previews[1].text()).toContain('unsupported');

        expect(previews[1].text()).toContain('unsupported field');

        expect(previews[1].text())
            .toContain('UnsupportedFieldRenderer');
    });

    it('renders missing field type as default text diagnostic while previewing as text', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'name',
                            label: 'Name',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="form-flow-field-type"]').text()).toBe('default:text');
        expect(wrapper.find('[data-testid="form-flow-field-preview-meta"]').text()).toContain('text');
        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(true);
    });
});
