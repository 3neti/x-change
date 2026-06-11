import { normalizeFormFlowPhase } from '@/components/x-change/formFlow';
import {
    activeClaimExperiencePhase,
    type ClaimExperiencePayload,
} from '@/components/x-change/claimExperiencePhases';

export function resolveCompiledFormFlowPhase(
    claimExperience: ClaimExperiencePayload,
): Record<string, any> | null {
    const phase = activeClaimExperiencePhase(claimExperience, 'form_flow');

    if (!phase) {
        return null;
    }

    if (phase.owner !== 'claim-widget') {
        return null;
    }

    return phase;
}

export type CompiledClaimPhase = Record<string, unknown>;

export function normalizeCompiledFormFlowPhase(
    phase: CompiledClaimPhase | null | undefined
) {
    if (!phase) {
        return null;
    }

    return normalizeFormFlowPhase(phase);
}
