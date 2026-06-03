import type { RawRiderStage } from '@/components/x-rider/types';
import { stageIsInPhase } from '@/components/x-rider/useRiderStagePhase';

export function resolveCompiledRiderIntroStages(
    phase: Record<string, any> | null | undefined,
): RawRiderStage[] {
    const stages = Array.isArray(phase?.stages)
        ? phase.stages as RawRiderStage[]
        : [];

    return stages.filter((stage) =>
            stage.enabled !== false
            && (
                stageIsInPhase(stage, 'rider_intro')
                || stageIsInPhase(stage, 'pre_claim')
            )
    );
}

export function resolveCompiledRuntimeStages(
    phase: Record<string, any> | null | undefined,
): RawRiderStage[] {
    const stages = Array.isArray(phase?.stages)
        ? phase.stages as RawRiderStage[]
        : [];

    return stages.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'runtime')
    );
}

export function resolveCompiledRedirectStages(
    phase: Record<string, any> | null | undefined,
): RawRiderStage[] {
    const stages = Array.isArray(phase?.stages)
        ? phase.stages as RawRiderStage[]
        : [];

    return stages.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'redirect')
    );
}

export function isVisualPreviewStage(stage: RawRiderStage): boolean {
    return ['splash', 'message', 'image', 'link', 'cta'].includes(stage.type);
}

export function isPreClaimStage(stage: RawRiderStage): boolean {
    return stageIsInPhase(stage, 'pre_claim');
}

export function isLegacyInstructionSplash(stage: RawRiderStage): boolean {
    return stage.key === 'legacy-splash';
}

export function preferVoucherInstructionSplash(stages: RawRiderStage[]): RawRiderStage[] {
    const instructionSplash = stages.find(isLegacyInstructionSplash);

    if (!instructionSplash) {
        return stages;
    }

    return [
        instructionSplash,
        ...stages.filter((stage) =>
            stage.key !== instructionSplash.key
            && stage.type !== 'splash'
        ),
    ];
}

export function resolveLegacyPreClaimVisualStages(
    stages: RawRiderStage[],
): RawRiderStage[] {
    return preferVoucherInstructionSplash(
        stages.filter((stage) =>
            stage.enabled !== false
            && isPreClaimStage(stage)
            && isVisualPreviewStage(stage)
        )
    );
}

export function resolveLegacyRuntimeStages(
    stages: RawRiderStage[],
): RawRiderStage[] {
    return stages.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'runtime')
        && isVisualPreviewStage(stage)
    );
}

export function resolveLegacyRedirectStages(
    stages: RawRiderStage[],
): RawRiderStage[] {
    return stages.filter((stage) =>
        stage.enabled !== false
        && stageIsInPhase(stage, 'redirect')
        && isVisualPreviewStage(stage)
    );
}
