import { describe, expect, it } from 'vitest';
import {
    resolveCompiledSuccessVisualStages,
    resolveLegacySuccessVisualStages,
    resolveSuccessVisualStages,
    resolveRedirectRuntimeStages,
    shouldRenderFallbackSuccess,
} from '../../resources/js/components/x-change/successRider';

describe('success rider stage resolution', () => {
    it('prefers active compiled success rider stages', () => {
        const stages = resolveSuccessVisualStages(
            {
                phases: [
                    {
                        key: 'success_rider',
                        status: 'active',
                        stages: [
                            { key: 'compiled-success', type: 'message', phase: 'success' },
                        ],
                    },
                ],
            },
            {
                stages: {
                    stages: [
                        { key: 'legacy-success', type: 'message', phase: 'success' },
                    ],
                },
            } as any,
        );

        expect(stages.map((stage) => stage.key)).toEqual(['compiled-success']);
    });

    it('falls back to legacy success rider stages when compiled stages are absent', () => {
        const stages = resolveSuccessVisualStages(
            { phases: [] },
            {
                stages: {
                    stages: [
                        { key: 'legacy-success', type: 'message', phase: 'success' },
                    ],
                },
            } as any,
        );

        expect(stages.map((stage) => stage.key)).toEqual(['legacy-success']);
    });

    it('ignores inactive compiled success rider phase', () => {
        const stages = resolveSuccessVisualStages(
            {
                phases: [
                    {
                        key: 'success_rider',
                        status: 'skipped',
                        stages: [
                            { key: 'compiled-success', type: 'message', phase: 'success' },
                        ],
                    },
                ],
            },
            {
                stages: {
                    stages: [
                        { key: 'legacy-success', type: 'message', phase: 'success' },
                    ],
                },
            } as any,
        );

        expect(stages.map((stage) => stage.key)).toEqual(['legacy-success']);
    });

    it('filters disabled compiled stages', () => {
        const stages = resolveCompiledSuccessVisualStages({
            phases: [
                {
                    key: 'success_rider',
                    status: 'active',
                    stages: [
                        { key: 'disabled-success', type: 'message', phase: 'success', enabled: false },
                        { key: 'enabled-success', type: 'message', phase: 'success' },
                    ],
                },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['enabled-success']);
    });

    it('uses first legacy message only when no explicit legacy success stage exists', () => {
        const stages = resolveLegacySuccessVisualStages({
            stages: {
                stages: [
                    { key: 'legacy-message', type: 'message', phase: 'success' },
                    { key: 'legacy-message-two', type: 'message', phase: 'success' },
                ],
            },
        } as any);

        expect(stages.map((stage) => stage.key)).toEqual(['legacy-message-two']);
    });

    it('resolves enabled rider redirect runtime stages', () => {
        const stages = resolveRedirectRuntimeStages({
            stages: {
                stages: [
                    { key: 'message', type: 'message', phase: 'success' },
                    { key: 'redirect', type: 'redirect', phase: 'redirect' },
                ],
            },
        } as any);

        expect(stages.map((stage) => stage.key)).toEqual(['redirect']);
    });

    it('filters disabled rider redirect runtime stages', () => {
        const stages = resolveRedirectRuntimeStages({
            stages: {
                stages: [
                    { key: 'disabled-redirect', type: 'redirect', phase: 'redirect', enabled: false },
                    { key: 'enabled-redirect', type: 'redirect', phase: 'redirect' },
                ],
            },
        } as any);

        expect(stages.map((stage) => stage.key)).toEqual(['enabled-redirect']);
    });

    it('uses first legacy redirect only when no explicit redirect stage exists', () => {
        const stages = resolveRedirectRuntimeStages({
            stages: {
                stages: [
                    { key: 'legacy-redirect', type: 'redirect', phase: 'redirect' },
                    { key: 'legacy-redirect', type: 'redirect', phase: 'redirect' },
                ],
            },
        } as any);

        expect(stages).toHaveLength(1);
        expect(stages[0].key).toBe('legacy-redirect');
    });

    it('does not resolve rider redirect runtime stages when claim widget owns compiled redirect', () => {
        const stages = resolveRedirectRuntimeStages(
            {
                stages: {
                    stages: [
                        { key: 'redirect', type: 'redirect', phase: 'redirect' },
                    ],
                },
            } as any,
            {
                diagnostics: {
                    redirect_owner: 'claim-widget',
                },
            },
        );

        expect(stages).toEqual([]);
    });

    it('allows rider redirect runtime stages when x-rider owns compiled redirect', () => {
        const stages = resolveRedirectRuntimeStages(
            {
                stages: {
                    stages: [
                        { key: 'redirect', type: 'redirect', phase: 'redirect' },
                    ],
                },
            } as any,
            {
                diagnostics: {
                    redirect_owner: 'x-rider',
                },
            },
        );

        expect(stages.map((stage) => stage.key)).toEqual(['redirect']);
    });

    it('allows legacy rider redirect runtime stages when compiled redirect owner is missing', () => {
        const stages = resolveRedirectRuntimeStages(
            {
                stages: {
                    stages: [
                        { key: 'redirect', type: 'redirect', phase: 'redirect' },
                    ],
                },
            } as any,
            {
                diagnostics: {},
            },
        );

        expect(stages.map((stage) => stage.key)).toEqual(['redirect']);
    });

    it('renders fallback success when there are no success stages, rider message, or redirect', () => {
        expect(shouldRenderFallbackSuccess(false, false, false)).toBe(true);
    });

    it('does not render fallback success when success stages exist', () => {
        expect(shouldRenderFallbackSuccess(true, false, false)).toBe(false);
    });

    it('does not render fallback success when rider message exists', () => {
        expect(shouldRenderFallbackSuccess(false, true, false)).toBe(false);
    });

    it('does not render fallback success when redirect exists', () => {
        expect(shouldRenderFallbackSuccess(false, false, true)).toBe(false);
    });

    it('resolves compiled success stages from nested stage payload', () => {
        const stages = resolveCompiledSuccessVisualStages({
            phases: [
                {
                    key: 'success_rider',
                    status: 'active',
                    stages: {
                        stages: [
                            { key: 'nested-success', type: 'message', phase: 'success' },
                        ],
                    },
                },
            ],
        });

        expect(stages.map((stage) => stage.key)).toEqual(['nested-success']);
    });
});
