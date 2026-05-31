import { describe, expect, it } from 'vitest';
import {
    isSupportedFormFlowFieldType,
    normalizeFormFlowFieldType,
    formFlowFieldPreviewKind,
    formFlowFieldRendererKind,
    formFlowFieldTypeDiagnostic,
    getFormFlowFieldPresentation,
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

    it('reports missing field type as default text diagnostic', () => {
        expect(formFlowFieldTypeDiagnostic(undefined)).toBe('default:text');
        expect(formFlowFieldTypeDiagnostic(null)).toBe('default:text');
        expect(formFlowFieldTypeDiagnostic('')).toBe('default:text');
        expect(formFlowFieldTypeDiagnostic('email')).toBe('email');
        expect(formFlowFieldTypeDiagnostic('camera')).toBe('unsupported');
    });

    it('returns renderer kind labels by field type', () => {
        expect(formFlowFieldRendererKind('text')).toBe('text field');
        expect(formFlowFieldRendererKind('email')).toBe('email field');
        expect(formFlowFieldRendererKind('date')).toBe('date field');
        expect(formFlowFieldRendererKind('number')).toBe('number field');
        expect(formFlowFieldRendererKind('select')).toBe('select field');
        expect(formFlowFieldRendererKind('textarea')).toBe('textarea field');
        expect(formFlowFieldRendererKind('camera')).toBe('unsupported field');
    });

    it('returns field presentation metadata', () => {
        expect(getFormFlowFieldPresentation({
            key: 'email',
            type: 'email',
        })).toEqual({
            diagnosticType: 'email',
            normalizedType: 'email',
            previewKind: 'email field',
        });

        expect(getFormFlowFieldPresentation({
            key: 'photo',
            type: 'camera',
        })).toEqual({
            diagnosticType: 'unsupported',
            normalizedType: 'unsupported',
            previewKind: 'unsupported field',
        });

        expect(getFormFlowFieldPresentation({
            key: 'name',
        })).toEqual({
            diagnosticType: 'default:text',
            normalizedType: 'text',
            previewKind: 'text field',
        });
    });
});
