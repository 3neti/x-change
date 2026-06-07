import { describe, expect, it } from 'vitest';
import { resolveClaimWidgetExperienceStages } from '../../resources/js/components/x-change/claimWidgetExperienceStages';

describe('claim widget experience stage orchestration', () => {
    it('falls back to legacy pre-claim, runtime, and redirect stages', () => {
        const stages = resolveClaimWidgetExperienceStages({
            claimExperience: null,
            legacyStages: [
                {
                    key: 'legacy-splash',
                    type: 'splash',
                    phase: 'pre_claim',
                },
                {
                    key: 'legacy-runtime',
                    type: 'message',
                    phase: 'runtime',
                },
                {
                    key: 'legacy-redirect',
                    type: 'link',
                    phase: 'redirect',
                },
            ],
        });

        expect(stages.preClaimVisualStages.map((stage) => stage.key)).toEqual([
            'legacy-splash',
        ]);

        expect(stages.runtimeStages.map((stage) => stage.key)).toEqual([
            'legacy-runtime',
        ]);

        expect(stages.redirectStages.map((stage) => stage.key)).toEqual([
            'legacy-redirect',
        ]);
    });

    it('prefers compiled rider intro over legacy pre-claim stages', () => {
        const stages = resolveClaimWidgetExperienceStages({
            claimExperience: {
                phases: [
                    {
                        key: 'rider_intro',
                        status: 'active',
                        stages: [
                            {
                                key: 'compiled-intro',
                                type: 'message',
                                phase: 'rider_intro',
                            },
                        ],
                    },
                ],
            },
            legacyStages: [
                {
                    key: 'legacy-splash',
                    type: 'splash',
                    phase: 'pre_claim',
                },
            ],
        });

        expect(stages.preClaimVisualStages.map((stage) => stage.key)).toEqual([
            'compiled-intro',
        ]);
    });

    it('prefers compiled runtime over legacy runtime stages', () => {
        const stages = resolveClaimWidgetExperienceStages({
            claimExperience: {
                phases: [
                    {
                        key: 'runtime',
                        status: 'active',
                        stages: [
                            {
                                key: 'compiled-runtime',
                                type: 'message',
                                phase: 'runtime',
                            },
                        ],
                    },
                ],
            },
            legacyStages: [
                {
                    key: 'legacy-runtime',
                    type: 'message',
                    phase: 'runtime',
                },
            ],
        });

        expect(stages.runtimeStages.map((stage) => stage.key)).toEqual([
            'compiled-runtime',
        ]);
    });

    it('prefers compiled redirect over legacy redirect stages', () => {
        const stages = resolveClaimWidgetExperienceStages({
            claimExperience: {
                phases: [
                    {
                        key: 'redirect',
                        status: 'active',
                        stages: [
                            {
                                key: 'compiled-redirect',
                                type: 'cta',
                                phase: 'redirect',
                            },
                        ],
                    },
                ],
            },
            legacyStages: [
                {
                    key: 'legacy-redirect',
                    type: 'link',
                    phase: 'redirect',
                },
            ],
        });

        expect(stages.redirectStages.map((stage) => stage.key)).toEqual([
            'compiled-redirect',
        ]);
    });

    it('ignores non-visual compiled stages and falls back to legacy stages', () => {
        const stages = resolveClaimWidgetExperienceStages({
            claimExperience: {
                phases: [
                    {
                        key: 'runtime',
                        status: 'active',
                        stages: [
                            {
                                key: 'compiled-hidden',
                                type: 'form',
                                phase: 'runtime',
                            },
                        ],
                    },
                ],
            },
            legacyStages: [
                {
                    key: 'legacy-runtime',
                    type: 'message',
                    phase: 'runtime',
                },
            ],
        });

        expect(stages.runtimeStages.map((stage) => stage.key)).toEqual([
            'legacy-runtime',
        ]);
    });

    it('ignores inactive compiled phases and falls back to legacy stages', () => {
        const stages = resolveClaimWidgetExperienceStages({
            claimExperience: {
                phases: [
                    {
                        key: 'rider_intro',
                        status: 'skipped',
                        stages: [
                            {
                                key: 'compiled-skipped',
                                type: 'message',
                                phase: 'rider_intro',
                            },
                        ],
                    },
                ],
            },
            legacyStages: [
                {
                    key: 'legacy-splash',
                    type: 'splash',
                    phase: 'pre_claim',
                },
            ],
        });

        expect(stages.preClaimVisualStages.map((stage) => stage.key)).toEqual([
            'legacy-splash',
        ]);
    });
});
