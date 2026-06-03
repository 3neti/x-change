import { describe, expect, it } from 'vitest';
import { resolveCompiledFormSubmitIntent } from '../../resources/js/components/x-change/compiledFormSubmit';

describe('compiled form submit intent', () => {
    it('blocks invalid compiled form submission', () => {
        expect(resolveCompiledFormSubmitIntent(true, false)).toBe('blocked');
    });

    it('allows valid compiled form submission', () => {
        expect(resolveCompiledFormSubmitIntent(true, true)).toBe('submit');
    });

    it('allows non-compiled form submission', () => {
        expect(resolveCompiledFormSubmitIntent(false, false)).toBe('submit');
    });
});
