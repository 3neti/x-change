import type { RawRiderStage, RiderExperience } from '@/components/x-rider/types';
import { stageIsInPhase } from '@/components/x-rider/useRiderStagePhase';
import {
    claimExperiencePhaseStages

} from '@/components/x-change/claimExperiencePhases';
import type {ClaimExperiencePayload} from '@/components/x-change/claimExperiencePhases';

function isRedirectStage(stage: RawRiderStage): boolean {
    return stage.type === 'redirect'
        || stageIsInPhase(stage, 'redirect');
}

function riderStages(rider: RiderExperience | null | undefined): RawRiderStage[] {
    const stages = rider?.stages?.stages;

    return Array.isArray(stages)
        ? stages as RawRiderStage[]
        : [];
}

function explicitOrFirstNonLegacy(
    stages: RawRiderStage[],
    legacyKey: string,
): RawRiderStage[] {
    const explicit = stages.filter((stage) =>
        stage.key !== legacyKey
    );

    return explicit.length > 0
        ? explicit
        : stages.slice(0, 1);
}

function redirectOwner(claimExperience: ClaimExperiencePayload): string | null {
    return typeof claimExperience?.diagnostics?.redirect_owner === 'string'
        ? claimExperience.diagnostics.redirect_owner
        : null;
}

function claimWidgetOwnsRedirect(claimExperience: ClaimExperiencePayload): boolean {
    return redirectOwner(claimExperience) === 'claim-widget';
}

export function resolveLegacySuccessVisualStages(
    rider: RiderExperience | null | undefined,
): RawRiderStage[] {
    const stages = riderStages(rider).filter((stage) =>
            stage.enabled !== false
            && !isRedirectStage(stage)
            && (
                stageIsInPhase(stage, 'success')
                || stageIsInPhase(stage, 'post_claim')
            )
    );

    return explicitOrFirstNonLegacy(stages, 'legacy-message');
}

export function resolveCompiledSuccessVisualStages(
    claimExperience: ClaimExperiencePayload,
): RawRiderStage[] {
    return claimExperiencePhaseStages(claimExperience, 'success_rider')
        .filter((stage) =>
                stage.enabled !== false
                && !isRedirectStage(stage)
                && (
                    stageIsInPhase(stage, 'success')
                    || stageIsInPhase(stage, 'post_claim')
                )
        );
}

export function resolveSuccessVisualStages(
    claimExperience: ClaimExperiencePayload,
    rider: RiderExperience | null | undefined,
): RawRiderStage[] {
    const compiled = resolveCompiledSuccessVisualStages(claimExperience);

    return compiled.length > 0
        ? compiled
        : resolveLegacySuccessVisualStages(rider);
}

export function resolveRedirectRuntimeStages(
    rider: RiderExperience | null | undefined,
    claimExperience: ClaimExperiencePayload = null,
): RawRiderStage[] {
    if (claimWidgetOwnsRedirect(claimExperience)) {
        return [];
    }

    const stages = riderStages(rider).filter((stage) =>
        stage.enabled !== false
        && isRedirectStage(stage)
    );

    return explicitOrFirstNonLegacy(stages, 'legacy-redirect');
}

export function shouldRenderFallbackSuccess(
    hasSuccessStages: boolean,
    hasRiderMessage: boolean,
    hasRedirect: boolean,
): boolean {
    return !hasSuccessStages
        && !hasRiderMessage
        && !hasRedirect;
}
