import { describe, expect, it } from 'vitest';
import { normalizeCompiledFormFlowPhase } from '../../resources/js/components/x-change/compiledFormFlow';

describe('compiled form flow', () => {
    it('returns null when compiled form flow phase is missing', () => {
        expect(normalizeCompiledFormFlowPhase(null)).toBeNull();
        expect(normalizeCompiledFormFlowPhase(undefined)).toBeNull();
    });

    it('normalizes compiled form flow phase fields', () => {
        expect(normalizeCompiledFormFlowPhase({
            key: 'form_flow',
            fields: [
                {
                    key: 'first_name',
                    label: 'First Name',
                    type: 'text',
                    required: true,
                },
            ],
        })).toMatchObject({
            fields: [
                {
                    key: 'first_name',
                    label: 'First Name',
                    type: 'text',
                    required: true,
                },
            ],
        });
    });
});
