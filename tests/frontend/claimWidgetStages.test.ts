import { describe, expect, it } from 'vitest';
import { resolveCompiledRiderIntroStages, resolveCompiledRuntimeStages, resolveCompiledRedirectStages,} from '../../resources/js/components/x-change/claimWidgetStages';

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

    it('resolves compiled runtime stages', () => {
        const stages = resolveCompiledRuntimeStages({
            key: 'runtime',
            stages: [
                { key: 'runtime', type: 'message', phase: 'runtime' },
                { key: 'intro', type: 'message', phase: 'rider_intro' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['runtime']);
    });

    it('filters disabled compiled runtime stages', () => {
        const stages = resolveCompiledRuntimeStages({
            key: 'runtime',
            stages: [
                { key: 'disabled-runtime', type: 'message', phase: 'runtime', enabled: false },
                { key: 'enabled-runtime', type: 'message', phase: 'runtime' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['enabled-runtime']);
    });

    it('resolves compiled redirect stages', () => {
        const stages = resolveCompiledRedirectStages({
            key: 'redirect',
            stages: [
                { key: 'redirect', type: 'message', phase: 'redirect' },
                { key: 'runtime', type: 'message', phase: 'runtime' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['redirect']);
    });

    it('filters disabled compiled redirect stages', () => {
        const stages = resolveCompiledRedirectStages({
            key: 'redirect',
            stages: [
                { key: 'disabled-redirect', type: 'message', phase: 'redirect', enabled: false },
                { key: 'enabled-redirect', type: 'message', phase: 'redirect' },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['enabled-redirect']);
    });
});
