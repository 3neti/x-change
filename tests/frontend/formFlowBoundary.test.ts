import { describe, expect, it } from 'vitest';
import { resolveFormFlowBoundary } from '../../resources/js/components/x-change/formFlowBoundary';

describe('form flow boundary', () => {
    it('resolves compiled form flow boundary when phase exists', () => {
        expect(resolveFormFlowBoundary({
            key: 'form_flow',
            status: 'active',
        })).toEqual({
            mode: 'compiled',
            phase: {
                key: 'form_flow',
                status: 'active',
            },
        });
    });

    it('resolves legacy form flow boundary when compiled phase is missing', () => {
        expect(resolveFormFlowBoundary(null)).toEqual({
            mode: 'legacy',
            phase: null,
        });

        expect(resolveFormFlowBoundary(undefined)).toEqual({
            mode: 'legacy',
            phase: null,
        });
    });
});
