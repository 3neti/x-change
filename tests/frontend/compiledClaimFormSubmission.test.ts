import { describe, expect, it } from 'vitest';
import {
    toCompiledClaimFormSubmissionPayload,
} from '../../resources/js/components/x-change/compiledClaimFormSubmission';

describe('compiled claim form submission', () => {
    it('maps claim widget form payload to backend submission payload', () => {
        expect(toCompiledClaimFormSubmissionPayload({
            code: 'TEST123',
            values: {
                first_name: 'Lester',
                email: 'lester@example.com',
            },
        })).toEqual({
            code: 'TEST123',
            inputs: {
                first_name: 'Lester',
                email: 'lester@example.com',
            },
        });
    });
});
