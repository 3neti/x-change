import { describe, expect, it } from 'vitest';
import {
    isSupportedFormFlowFieldType,
    normalizeFormFlowFieldType,
    formFlowFieldPreviewKind,
    SUPPORTED_FORM_FLOW_FIELD_TYPES,
} from '../../resources/js/components/x-change/formFlow';

describe('formFlow field type support', () => {
    it('defines supported form flow field types', () => {
        expect(SUPPORTED_FORM_FLOW_FIELD_TYPES).toEqual([
            'text',
            'email',
            'date',
            'number',
            'select',
            'textarea',
        ]);
    });

    it('normalizes unsupported field types explicitly', () => {
        expect(isSupportedFormFlowFieldType('text')).toBe(true);
        expect(isSupportedFormFlowFieldType('camera')).toBe(false);

        expect(normalizeFormFlowFieldType('email')).toBe('email');
        expect(normalizeFormFlowFieldType('camera')).toBe('unsupported');
        expect(normalizeFormFlowFieldType(null)).toBe('unsupported');
    });

    it('returns readonly preview kind by field type', () => {
        expect(formFlowFieldPreviewKind('text')).toBe('text field');
        expect(formFlowFieldPreviewKind('email')).toBe('email field');
        expect(formFlowFieldPreviewKind('date')).toBe('date field');
        expect(formFlowFieldPreviewKind('number')).toBe('number field');
        expect(formFlowFieldPreviewKind('select')).toBe('select field');
        expect(formFlowFieldPreviewKind('textarea')).toBe('textarea field');
        expect(formFlowFieldPreviewKind('camera')).toBe('unsupported field');
    });
});
