import { describe, expect, it } from 'vitest';
import {
    extractStages,
    instructionSplashStage,
    mergeStageWithRaw,
    resolveLegacyRiderStages,
    uniqueStages,
} from '../../resources/js/components/x-change/claimWidgetLegacyStages';

describe('claim widget legacy rider stage resolution', () => {
    it('returns an empty stage list without voucher preview data', () => {
        expect(resolveLegacyRiderStages(null)).toEqual([]);
        expect(resolveLegacyRiderStages(undefined)).toEqual([]);
    });

    it('extracts stages from direct arrays and stage containers', () => {
        const stages = [
            {
                key: 'message',
                type: 'message',
            },
        ];

        expect(extractStages(stages)).toEqual(stages);
        expect(extractStages({ stages })).toEqual(stages);
        expect(extractStages({})).toEqual([]);
        expect(extractStages(null)).toEqual([]);
    });

    it('creates a legacy splash stage from voucher rider instructions', () => {
        const stage = instructionSplashStage({
            instructions: {
                rider: {
                    splash: '<h1>Welcome</h1>',
                    splash_timeout: 3,
                    splash_meta: {
                        source: 'instruction',
                    },
                },
            },
        });

        expect(stage).toMatchObject({
            key: 'legacy-splash',
            type: 'splash',
            enabled: true,
            phase: 'pre_claim',
            presentation: 'fullscreen',
            content: '<h1>Welcome</h1>',
            content_type: 'html',
            payload: {
                content: '<h1>Welcome</h1>',
                content_type: 'html',
                timeout: 3,
                presentation: 'fullscreen',
                meta: {
                    source: 'instruction',
                },
            },
            meta: {
                source: 'instruction',
            },
        });
    });

    it('does not create a legacy splash stage for blank splash content', () => {
        expect(instructionSplashStage({
            instructions: {
                rider: {
                    splash: '   ',
                },
            },
        })).toBeNull();
    });

    it('merges resolved rider stages with raw instruction stages by key', () => {
        expect(mergeStageWithRaw(
            {
                key: 'demo-message',
                type: 'message',
                content: 'Resolved content',
                payload: {
                    resolved: true,
                    meta: {
                        resolved_meta: true,
                    },
                },
                meta: {
                    resolved_stage_meta: true,
                },
            },
            [
                {
                    key: 'demo-message',
                    type: 'message',
                    phase: 'pre_claim',
                    presentation: 'embedded',
                    content: 'Raw content',
                    payload: {
                        raw: true,
                        meta: {
                            raw_meta: true,
                        },
                    },
                    meta: {
                        raw_stage_meta: true,
                    },
                },
            ],
        )).toMatchObject({
            key: 'demo-message',
            type: 'message',
            phase: 'pre_claim',
            presentation: 'embedded',
            content: 'Resolved content',
            payload: {
                raw: true,
                resolved: true,
                meta: {
                    raw_meta: true,
                    resolved_meta: true,
                },
            },
            meta: {
                raw_stage_meta: true,
                resolved_stage_meta: true,
            },
        });
    });

    it('deduplicates stages by key', () => {
        expect(uniqueStages([
            {
                key: 'same',
                type: 'message',
                content: 'First',
            },
            {
                key: 'same',
                type: 'message',
                content: 'Second',
            },
            {
                key: 'different',
                type: 'message',
                content: 'Third',
            },
        ])).toEqual([
            {
                key: 'same',
                type: 'message',
                content: 'First',
            },
            {
                key: 'different',
                type: 'message',
                content: 'Third',
            },
        ]);
    });

    it('keeps raw instruction stages missing from resolved stages', () => {
        const stages = resolveLegacyRiderStages({
            rider: {
                stages: [
                    {
                        key: 'resolved-message',
                        type: 'message',
                        phase: 'runtime',
                    },
                ],
            },
            instructions: {
                rider: {
                    stages: [
                        {
                            key: 'raw-link',
                            type: 'link',
                            phase: 'runtime',
                        },
                    ],
                },
            },
        });

        expect(stages.map((stage) => stage.key)).toEqual([
            'resolved-message',
            'raw-link',
        ]);
    });

    it('resolves merged rider stages, missing raw stages, and instruction splash together', () => {
        const stages = resolveLegacyRiderStages({
            rider: {
                stages: [
                    {
                        key: 'shared-message',
                        type: 'message',
                        content: 'Resolved message',
                    },
                ],
            },
            instructions: {
                rider: {
                    splash: '<h1>Welcome</h1>',
                    splash_meta: {
                        campaign: 'demo',
                    },
                    stages: [
                        {
                            key: 'shared-message',
                            type: 'message',
                            phase: 'pre_claim',
                            content: 'Raw message',
                        },
                        {
                            key: 'raw-cta',
                            type: 'cta',
                            phase: 'pre_claim',
                            content: 'Continue',
                        },
                    ],
                },
            },
        });

        expect(stages.map((stage) => stage.key)).toEqual([
            'shared-message',
            'raw-cta',
            'legacy-splash',
        ]);

        expect(stages[0]).toMatchObject({
            key: 'shared-message',
            content: 'Resolved message',
            phase: 'pre_claim',
        });

        expect(stages[2]).toMatchObject({
            key: 'legacy-splash',
            meta: {
                campaign: 'demo',
            },
            payload: {
                meta: {
                    campaign: 'demo',
                },
            },
        });
    });
});
