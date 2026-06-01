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

    it('delegates date fields to DateFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
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

        expect(wrapper.find('[data-testid="date-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="date-field-renderer-label"]').text()).toBe('Birth Date');
        expect(wrapper.find('[data-testid="date-field-renderer-kind"]').text()).toBe('date field');
        expect(wrapper.find('[data-testid="date-field-renderer-required"]').text()).toBe('optional');

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="email-field-renderer"]').exists()).toBe(false);
    });

    it('delegates number fields to NumberFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'gross_monthly_income',
                            type: 'number',
                            label: 'Gross Monthly Income',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="number-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="number-field-renderer-label"]').text()).toBe('Gross Monthly Income');
        expect(wrapper.find('[data-testid="number-field-renderer-kind"]').text()).toBe('number field');
        expect(wrapper.find('[data-testid="number-field-renderer-required"]').text()).toBe('required');

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="email-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="date-field-renderer"]').exists()).toBe(false);
    });

    it('delegates select fields to SelectFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'bank_code',
                            type: 'select',
                            label: 'Bank',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="select-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="select-field-renderer-label"]').text()).toBe('Bank');
        expect(wrapper.find('[data-testid="select-field-renderer-kind"]').text()).toBe('select field');
        expect(wrapper.find('[data-testid="select-field-renderer-required"]').text()).toBe('required');

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="email-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="date-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="number-field-renderer"]').exists()).toBe(false);
    });

    it('delegates textarea fields to TextareaFieldRenderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    fields: [
                        {
                            key: 'address',
                            type: 'textarea',
                            label: 'Address',
                            required: false,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="textarea-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="textarea-field-renderer-label"]').text()).toBe('Address');
        expect(wrapper.find('[data-testid="textarea-field-renderer-kind"]').text()).toBe('textarea field');
        expect(wrapper.find('[data-testid="textarea-field-renderer-required"]').text()).toBe('optional');

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="email-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="date-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="number-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="select-field-renderer"]').exists()).toBe(false);
    });

    it('delegates unsupported fields to UnsupportedFieldRenderer', () => {
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

        expect(wrapper.find('[data-testid="unsupported-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="unsupported-field-renderer-label"]').text()).toBe('Photo');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-kind"]').text()).toBe('unsupported field');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-type"]').text()).toBe('camera');

        expect(wrapper.find('[data-testid="text-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="email-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="date-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="number-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="select-field-renderer"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="textarea-field-renderer"]').exists()).toBe(false);
    });

    it('passes readonly field values to delegated renderers', () => {
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
                            value: 'Lester',
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="text-field-renderer-value"]').text()).toBe('Lester');
    });

    it('uses formFlow values map before field value when passing readonly values to renderers', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {
                        first_name: 'From values map',
                    },
                    fields: [
                        {
                            key: 'first_name',
                            type: 'text',
                            label: 'First Name',
                            required: true,
                            value: 'From field value',
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="text-field-renderer-value"]').text()).toBe('From values map');
    });

    it('falls back to field value when formFlow values map has no value for field', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {},
                    fields: [
                        {
                            key: 'first_name',
                            type: 'text',
                            label: 'First Name',
                            required: true,
                            value: 'From field value',
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="text-field-renderer-value"]').text()).toBe('From field value');
    });

    it('passes readonly field values to unsupported renderer', () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {
                        photo: 'raw-camera-value',
                    },
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

        expect(wrapper.find('[data-testid="unsupported-field-renderer-value"]').text()).toBe('raw-camera-value');
    });

    it('updates local field value when delegated text input emits update', async () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {
                        first_name: 'Initial Value',
                    },
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

        expect(wrapper.find('[data-testid="text-field-renderer-value"]').text()).toBe('Initial Value');

        await wrapper
            .find('[data-testid="text-field-renderer-input"]')
            .setValue('Updated Text Value');

        expect(wrapper.find('[data-testid="text-field-renderer-value"]').text()).toBe('Updated Text Value');
    });

    it('updates local field value when delegated email input emits update', async () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {
                        email: 'old@example.com',
                    },
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

        expect(wrapper.find('[data-testid="email-field-renderer-value"]').text()).toBe('old@example.com');

        await wrapper
            .find('[data-testid="email-field-renderer-input"]')
            .setValue('new@example.com');

        expect(wrapper.find('[data-testid="email-field-renderer-value"]').text()).toBe('new@example.com');
    });

    it('updates local field value when delegated date input emits update', async () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {
                        birth_date: '1990-01-01',
                    },
                    fields: [
                        {
                            key: 'birth_date',
                            type: 'date',
                            label: 'Birth Date',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="date-field-renderer-value"]').text()).toBe('1990-01-01');

        await wrapper
            .find('[data-testid="date-field-renderer-input"]')
            .setValue('1991-02-03');

        expect(wrapper.find('[data-testid="date-field-renderer-value"]').text()).toBe('1991-02-03');
    });

    it('updates local field value when delegated number input emits update', async () => {
        const wrapper = mount(FormFlowRenderer, {
            props: {
                formFlow: {
                    key: 'form_flow',
                    owner: 'form-flow',
                    source: 'claim_experience',
                    values: {
                        gross_monthly_income: '10000',
                    },
                    fields: [
                        {
                            key: 'gross_monthly_income',
                            type: 'number',
                            label: 'Gross Monthly Income',
                            required: true,
                        },
                    ],
                    stages: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="number-field-renderer-value"]').text()).toBe('10000');

        await wrapper
            .find('[data-testid="number-field-renderer-input"]')
            .setValue('25000');

        expect(wrapper.find('[data-testid="number-field-renderer-value"]').text()).toBe('25000');
    });
});
