import { describe, expect, it } from 'vitest';
import { resolveCompiledFormSubmitState } from '../../resources/js/components/x-change/compiledFormSubmitState';

describe('compiled form submit state', () => {
    it('resolves failed state from submit error', () => {
        expect(resolveCompiledFormSubmitState({
            submitError: 'Submission failed.',
            submitted: true,
            submitting: true,
        })).toBe('failed');
    });

    it('resolves submitted state', () => {
        expect(resolveCompiledFormSubmitState({
            submitted: true,
            submitting: true,
        })).toBe('submitted');
    });

    it('resolves submitting state', () => {
        expect(resolveCompiledFormSubmitState({
            submitted: false,
            submitting: true,
        })).toBe('submitting');
    });

    it('resolves idle state', () => {
        expect(resolveCompiledFormSubmitState({
            submitted: false,
            submitting: false,
        })).toBe('idle');
    });
});
