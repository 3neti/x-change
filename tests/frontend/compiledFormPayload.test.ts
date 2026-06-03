import { describe, expect, it } from 'vitest';
import { buildCompiledFormPayload } from '../../resources/js/components/x-change/compiledFormPayload';

describe('compiled form payload', () => {
    it('builds compiled form submit payload', () => {
        expect(buildCompiledFormPayload('TEST123', {
            first_name: 'Lester',
        })).toEqual({
            code: 'TEST123',
            values: {
                first_name: 'Lester',
            },
        });
    });

    it('preserves missing code for caller-side validation', () => {
        expect(buildCompiledFormPayload(null, {})).toEqual({
            code: null,
            values: {},
        });
    });
});
