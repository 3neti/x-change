import { describe, expect, it } from 'vitest';
import {
    normalizeCompiledFormFlowPhase,
    resolveCompiledFormFlowPhase,
} from '../../resources/js/components/x-change/compiledFormFlow';

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

    it('resolves active compiled form flow phase from claim experience', () => {
        expect(resolveCompiledFormFlowPhase({
            phases: [
                {
                    key: 'form_flow',
                    owner: 'claim-widget',
                    status: 'active',
                    fields: [
                        { key: 'first_name', type: 'text', required: true },
                    ],
                }
            ],
        })).toEqual({
            key: 'form_flow',
            owner: 'claim-widget',
            status: 'active',
            fields: [
                { key: 'first_name', type: 'text', required: true },
            ],
        });
    });

    it('ignores inactive compiled form flow phase', () => {
        expect(resolveCompiledFormFlowPhase({
            phases: [
                {
                    key: 'form_flow',
                    status: 'skipped',
                    fields: [
                        { key: 'first_name', type: 'text', required: true },
                    ],
                },
            ],
        })).toBeNull();
    });
});
