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
                },
            });

            expect(wrapper.find(`[data-testid="${testId}"]`).exists()).toBe(true);
            expect(wrapper.find(`[data-testid="${testId}-label"]`).text()).toBe(`${kind} label`);
            expect(wrapper.find(`[data-testid="${testId}-kind"]`).text()).toBe(kind);
            expect(wrapper.find(`[data-testid="${testId}-required"]`).text()).toBe('required');
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
            },
        });

        expect(wrapper.find('[data-testid="unsupported-field-renderer"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="unsupported-field-renderer-label"]').text()).toBe('Camera Field');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-kind"]').text()).toBe('unsupported field');
        expect(wrapper.find('[data-testid="unsupported-field-renderer-type"]').text()).toBe('camera');
    });
});
