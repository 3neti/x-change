import { describe, expect, it } from 'vitest';
import {
    activeClaimExperiencePhase,
    claimExperiencePhases,
    claimExperiencePhaseStages,
} from '../../resources/js/components/x-change/claimExperiencePhases';

describe('claim experience phases', () => {
    it('returns empty phases when claim experience is missing', () => {
        expect(claimExperiencePhases(null)).toEqual([]);
        expect(claimExperiencePhases(undefined)).toEqual([]);
    });

    it('returns phases from claim experience', () => {
        expect(claimExperiencePhases({
            phases: [
                { key: 'form_flow', status: 'active' },
            ],
        })).toEqual([
            { key: 'form_flow', status: 'active' },
        ]);
    });

    it('resolves active phase by key', () => {
        expect(activeClaimExperiencePhase({
            phases: [
                { key: 'form_flow', status: 'skipped' },
                { key: 'form_flow', status: 'active' },
            ],
        }, 'form_flow')).toEqual({
            key: 'form_flow',
            status: 'active',
        });
    });

    it('treats missing status as active', () => {
        expect(activeClaimExperiencePhase({
            phases: [
                { key: 'redirect' },
            ],
        }, 'redirect')).toEqual({
            key: 'redirect',
        });
    });

    it('returns empty when phase is inactive', () => {
        expect(activeClaimExperiencePhase({
            phases: [
                { key: 'redirect', status: 'skipped' },
            ],
        }, 'redirect')).toBeNull();
    });

    it('extracts stages from array form', () => {
        expect(claimExperiencePhaseStages({
            phases: [
                {
                    key: 'success_rider',
                    status: 'active',
                    stages: [
                        { key: 'stage-one' },
                    ],
                },
            ],
        }, 'success_rider')).toEqual([
            { key: 'stage-one' },
        ]);
    });

    it('extracts stages from nested stages form', () => {
        expect(claimExperiencePhaseStages({
            phases: [
                {
                    key: 'success_rider',
                    status: 'active',
                    stages: {
                        stages: [
                            { key: 'stage-one' },
                        ],
                    },
                },
            ],
        }, 'success_rider')).toEqual([
            { key: 'stage-one' },
        ]);
    });
});
