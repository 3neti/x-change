import { describe, expect, it } from 'vitest';
import {
    FORM_FLOW_RENDERER_COMPONENTS,
    hasFormFlowRendererComponent,
    resolveFormFlowRendererComponentName,
} from '../../resources/js/components/x-change/formFlowRendererComponents';

describe('form flow renderer components', () => {
    it('defines renderer component mappings', () => {
        expect(Object.keys(FORM_FLOW_RENDERER_COMPONENTS)).toEqual([
            'TextFieldRenderer',
            'EmailFieldRenderer',
            'DateFieldRenderer',
            'NumberFieldRenderer',
            'SelectFieldRenderer',
            'TextareaFieldRenderer',
            'UnsupportedFieldRenderer',
        ]);
    });

    it('checks whether a renderer component exists', () => {
        expect(hasFormFlowRendererComponent('TextFieldRenderer')).toBe(true);
        expect(hasFormFlowRendererComponent('UnsupportedFieldRenderer')).toBe(true);
        expect(hasFormFlowRendererComponent('MissingRenderer')).toBe(false);
    });

    it('resolves renderer component names from fields', () => {
        expect(resolveFormFlowRendererComponentName({
            key: 'name',
            type: 'text',
        })).toBe('TextFieldRenderer');

        expect(resolveFormFlowRendererComponentName({
            key: 'email',
            type: 'email',
        })).toBe('EmailFieldRenderer');

        expect(resolveFormFlowRendererComponentName({
            key: 'photo',
            type: 'camera',
        })).toBe('UnsupportedFieldRenderer');
    });

    it('defaults missing field type to text renderer', () => {
        expect(resolveFormFlowRendererComponentName({
            key: 'name',
        })).toBe('TextFieldRenderer');
    });
});
