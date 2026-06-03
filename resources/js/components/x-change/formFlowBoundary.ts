export type FormFlowBoundaryMode = 'compiled' | 'legacy';

export type FormFlowBoundary = {
    mode: FormFlowBoundaryMode;
    phase: Record<string, any> | null;
};

export function resolveFormFlowBoundary(
    compiledFormFlowPhase: Record<string, any> | null | undefined,
): FormFlowBoundary {
    if (compiledFormFlowPhase) {
        return {
            mode: 'compiled',
            phase: compiledFormFlowPhase,
        };
    }

    return {
        mode: 'legacy',
        phase: null,
    };
}
