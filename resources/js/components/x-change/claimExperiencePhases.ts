export type ClaimExperiencePayload = Record<string, any> | null | undefined;
export type ClaimExperiencePhase = Record<string, any>;

export function claimExperiencePhases(
    claimExperience: ClaimExperiencePayload,
): ClaimExperiencePhase[] {
    return Array.isArray(claimExperience?.phases)
        ? claimExperience.phases as ClaimExperiencePhase[]
        : [];
}

export function activeClaimExperiencePhase(
    claimExperience: ClaimExperiencePayload,
    key: string,
): ClaimExperiencePhase | null {
    return claimExperiencePhases(claimExperience).find((phase) =>
        phase.key === key
        && (phase.status ?? 'active') === 'active'
    ) ?? null;
}

export function claimExperiencePhaseStages<T = Record<string, any>>(
    claimExperience: ClaimExperiencePayload,
    key: string,
): T[] {
    const phase = activeClaimExperiencePhase(claimExperience, key);
    const stages = phase?.stages;

    if (Array.isArray(stages)) {
        return stages as T[];
    }

    if (Array.isArray(stages?.stages)) {
        return stages.stages as T[];
    }

    return [];
}
