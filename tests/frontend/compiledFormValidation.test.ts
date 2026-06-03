import { describe, expect, it } from 'vitest';
import {
    hasCompiledFormValue,
    isCompiledFormValid,
    missingRequiredCompiledFormFields,
    requiredCompiledFormFields,
} from '../../resources/js/components/x-change/compiledFormValidation';

describe('compiled form validation', () => {
    it('detects present form values', () => {
        expect(hasCompiledFormValue('Lester')).toBe(true);
        expect(hasCompiledFormValue(0)).toBe(true);
        expect(hasCompiledFormValue(false)).toBe(true);
    });

    it('detects missing form values', () => {
        expect(hasCompiledFormValue(null)).toBe(false);
        expect(hasCompiledFormValue(undefined)).toBe(false);
        expect(hasCompiledFormValue('   ')).toBe(false);
    });

    it('returns required fields only', () => {
        expect(requiredCompiledFormFields([
            { key: 'first_name', required: true },
            { key: 'middle_name', required: false },
            { key: 'nickname' },
        ])).toEqual([
            { key: 'first_name', required: true },
        ]);
    });

    it('returns missing required fields', () => {
        expect(missingRequiredCompiledFormFields([
            { key: 'first_name', required: true },
            { key: 'middle_name', required: false },
            { key: 'last_name', required: true },
        ], {
            first_name: 'Lester',
            last_name: '',
        })).toEqual([
            { key: 'last_name', required: true },
        ]);
    });

    it('marks form valid when no required fields are missing', () => {
        expect(isCompiledFormValid([
            { key: 'first_name', required: true },
        ], {
            first_name: 'Lester',
        })).toBe(true);
    });

    it('marks form invalid when required fields are missing', () => {
        expect(isCompiledFormValid([
            { key: 'first_name', required: true },
        ], {
            first_name: '',
        })).toBe(false);
    });
});
