import type { RawRiderStage } from '@/components/x-rider/types';
import { activeClaimExperiencePhase } from '@/components/x-change/claimExperiencePhases';
import {
    isVisualPreviewStage,
    preferCompiledStages,
    resolveCompiledRedirectStages,
    resolveCompiledRiderIntroStages,
    resolveCompiledRuntimeStages,
    resolveLegacyPreClaimVisualStages,
    resolveLegacyRedirectStages,
    resolveLegacyRuntimeStages,
} from '@/components/x-change/claimWidgetStages';

export type ClaimWidgetExperienceStagesInput = {
    claimExperience?: Record<string, unknown> | null;
    legacyStages: RawRiderStage[];
};

export type ClaimWidgetExperienceStages = {
    preClaimVisualStages: RawRiderStage[];
    runtimeStages: RawRiderStage[];
    redirectStages: RawRiderStage[];
};

function compiledPhase(
    claimExperience: Record<string, unknown> | null | undefined,
    key: string,
): Record<string, any> | null {
    return activeClaimExperiencePhase(claimExperience, key);
}

export function resolveClaimWidgetExperienceStages(
    input: ClaimWidgetExperienceStagesInput,
): ClaimWidgetExperienceStages {
    const compiledPreClaimVisualStages = resolveCompiledRiderIntroStages(
        compiledPhase(input.claimExperience, 'rider_intro'),
    ).filter(isVisualPreviewStage);

    const legacyPreClaimVisualStages = resolveLegacyPreClaimVisualStages(
        input.legacyStages,
    );

    const compiledRuntimeStages = resolveCompiledRuntimeStages(
        compiledPhase(input.claimExperience, 'runtime'),
    ).filter(isVisualPreviewStage);

    const legacyRuntimeStages = resolveLegacyRuntimeStages(
        input.legacyStages,
    );

    const compiledRedirectStages = resolveCompiledRedirectStages(
        compiledPhase(input.claimExperience, 'redirect'),
    ).filter(isVisualPreviewStage);

    const legacyRedirectStages = resolveLegacyRedirectStages(
        input.legacyStages,
    );

    return {
        preClaimVisualStages: preferCompiledStages(
            compiledPreClaimVisualStages,
            legacyPreClaimVisualStages,
        ),
        runtimeStages: preferCompiledStages(
            compiledRuntimeStages,
            legacyRuntimeStages,
        ),
        redirectStages: preferCompiledStages(
            compiledRedirectStages,
            legacyRedirectStages,
        ),
    };
}
