import { describe, expect, it } from 'vitest';
import { shouldSubmitCompiledForm } from '../../resources/js/components/x-change/compiledFormSubmitGuard';

describe('compiled form submit guard', () => {
    it('allows submit when there is no compiled form', () => {
        expect(shouldSubmitCompiledForm(false, false)).toBe(true);
    });

    it('allows submit when compiled form is valid', () => {
        expect(shouldSubmitCompiledForm(true, true)).toBe(true);
    });

    it('blocks submit when compiled form is invalid', () => {
        expect(shouldSubmitCompiledForm(true, false)).toBe(false);
    });
});
