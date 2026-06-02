import { normalizeFormFlowPhase } from '@/components/x-change/formFlow';

export type CompiledClaimPhase = Record<string, unknown>;

export function normalizeCompiledFormFlowPhase(
    phase: CompiledClaimPhase | null | undefined
) {
    if (!phase) {
        return null;
    }

    return normalizeFormFlowPhase(phase);
}
