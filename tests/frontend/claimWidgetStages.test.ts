import { describe, expect, it } from 'vitest';
import { resolveCompiledRiderIntroStages } from '../../resources/js/components/x-change/claimWidgetStages';

describe('claim widget stage resolution', () => {
    it('resolves compiled rider intro visual stages', () => {
        const stages = resolveCompiledRiderIntroStages({
            key: 'rider_intro',
            stages: [
                { key: 'intro', type: 'message', phase: 'rider_intro' },
                { key: 'runtime', type: 'message', phase: 'runtime' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['intro']);
    });

    it('resolves pre claim stages as rider intro stages', () => {
        const stages = resolveCompiledRiderIntroStages({
            key: 'rider_intro',
            stages: [
                { key: 'pre-claim', type: 'message', phase: 'pre_claim' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['pre-claim']);
    });

    it('filters disabled rider intro stages', () => {
        const stages = resolveCompiledRiderIntroStages({
            key: 'rider_intro',
            stages: [
                { key: 'disabled', type: 'message', phase: 'rider_intro', enabled: false },
                { key: 'enabled', type: 'message', phase: 'rider_intro' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['enabled']);
    });

    it('returns empty stages when phase is missing', () => {
        expect(resolveCompiledRiderIntroStages(null)).toEqual([]);
        expect(resolveCompiledRiderIntroStages(undefined)).toEqual([]);
    });
});
