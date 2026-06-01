import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import TextFieldRenderer from '../../resources/js/components/x-change/renderers/TextFieldRenderer.vue';
import EmailFieldRenderer from '../../resources/js/components/x-change/renderers/EmailFieldRenderer.vue';
import DateFieldRenderer from '../../resources/js/components/x-change/renderers/DateFieldRenderer.vue';
import NumberFieldRenderer from '../../resources/js/components/x-change/renderers/NumberFieldRenderer.vue';
import SelectFieldRenderer from '../../resources/js/components/x-change/renderers/SelectFieldRenderer.vue';
import TextareaFieldRenderer from '../../resources/js/components/x-change/renderers/TextareaFieldRenderer.vue';
import UnsupportedFieldRenderer from '../../resources/js/components/x-change/renderers/UnsupportedFieldRenderer.vue';

const supportedRenderers = [
    {
        component: TextFieldRenderer,
        testId: 'text-field-renderer',
        kind: 'text field',
        type: 'text',
    },
    {
        component: EmailFieldRenderer,
        testId: 'email-field-renderer',
        kind: 'email field',
        type: 'email',
    },
    {
        component: DateFieldRenderer,
        testId: 'date-field-renderer',
        kind: 'date field',
        type: 'date',
    },
    {
        component: NumberFieldRenderer,
        testId: 'number-field-renderer',
        kind: 'number field',
        type: 'number',
    },
    {
        component: SelectFieldRenderer,
        testId: 'select-field-renderer',
        kind: 'select field',
        type: 'select',
    },
    {
        component: TextareaFieldRenderer,
        testId: 'textarea-field-renderer',
        kind: 'textarea field',
        type: 'textarea',
    },
];

describe('form flow field renderer contract', () => {
    it.each(supportedRenderers)(
        'renders supported renderer contract for $type',
        ({ component, testId, kind, type }) => {
            const wrapper = mount(component, {
                props: {
                    field: {
                        key: `${type}_field`,
                        type,
                        label: `${kind} label`,
                        required: true,
                    },
                    value: 'Sample Value',
                },
            });

            expect(wrapper.find(`[data-testid="${testId}"]`).exists()).toBe(true);
            expect(wrapper.find(`[data-testid="${testId}-label"]`).text()).toBe(`${kind} label`);
            expect(wrapper.find(`[data-testid="${testId}-kind"]`).text()).toBe(kind);
            expect(wrapper.find(`[data-testid="${testId}-required"]`).text()).toBe('required');
            expect(wrapper.find(`[data-testid="${testId}-value"]`).text()).toBe('Sample Value');
        }
    );

    it('renders unsupported renderer contract', () => {
        const wrapper = mount(UnsupportedFieldRenderer, {
            props: {
                field: {
                    key: 'camera_field',
                    type: 'camera',
                    label: 'Camera Field',
                    required: false,
                },
                value: 'Unsupported Value',
            },
        });

        expect(wrapper.find('[data-testid="unsupported-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="unsupported-field-renderer-label"]').text()).toBe('Camera Field');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-kind"]').text()).toBe('unsupported field');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-type"]').text()).toBe('camera');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-diagnostic-type"]').text()).toBe('unsupported');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-value"]').text()).toBe('Unsupported Value');
    });

    it('renders blank value when supported renderer value is null', () => {
        const wrapper = mount(TextFieldRenderer, {
            props: {
                field: {
                    key: 'empty_field',
                    type: 'text',
                    label: 'Empty Field',
                    required: false,
                },
                value: null,
            },
        });

        expect(wrapper.find('[data-testid="text-field-renderer-value"]').text()).toBe('');
    });

    it('emits update value from text field input', async () => {
        const wrapper = mount(TextFieldRenderer, {
            props: {
                field: {
                    key: 'first_name',
                    type: 'text',
                    label: 'First Name',
                    required: true,
                },
                value: 'Initial Value',
            },
        });

        await wrapper
            .find('[data-testid="text-field-renderer-input"]')
            .setValue('Updated Text Value');

        expect(wrapper.emitted('update:value')?.[0]).toEqual(['Updated Text Value']);
    });

    it('emits update value from email field input', async () => {
        const wrapper = mount(EmailFieldRenderer, {
            props: {
                field: {
                    key: 'email',
                    type: 'email',
                    label: 'Email Address',
                    required: true,
                },
                value: 'old@example.com',
            },
        });

        await wrapper
            .find('[data-testid="email-field-renderer-input"]')
            .setValue('new@example.com');

        expect(wrapper.emitted('update:value')?.[0]).toEqual(['new@example.com']);
    });
});
