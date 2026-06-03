import { describe, expect, it } from 'vitest';
import {
    resolveCompiledFormSubmitEvent,
    resolveCompiledFormSubmitIntent,
} from '../../resources/js/components/x-change/compiledFormSubmit';

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

    it('returns blocked submit event for invalid compiled form', () => {
        expect(resolveCompiledFormSubmitEvent(true, false, {
            code: 'TEST123',
            values: {},
        })).toEqual({
            intent: 'blocked',
            submitting: false,
            payload: null,
        });
    });

    it('returns submit event for valid compiled form', () => {
        expect(resolveCompiledFormSubmitEvent(true, true, {
            code: 'TEST123',
            values: {
                first_name: 'Lester',
            },
        })).toEqual({
            intent: 'submit',
            submitting: true,
            payload: {
                code: 'TEST123',
                values: {
                    first_name: 'Lester',
                },
            },
        });
    });
});
