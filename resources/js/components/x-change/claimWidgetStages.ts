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
