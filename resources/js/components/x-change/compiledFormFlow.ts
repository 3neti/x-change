import { normalizeFormFlowPhase } from '@/components/x-change/formFlow';
import {
    activeClaimExperiencePhase,
    type ClaimExperiencePayload,
} from '@/components/x-change/claimExperiencePhases';

export function resolveCompiledFormFlowPhase(
    claimExperience: ClaimExperiencePayload,
) {
    return activeClaimExperiencePhase(claimExperience, 'form_flow');
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
