export function stageIsInPhase(stage: any, phase: string): boolean {
    return stage?.phase === phase || stage?.phases?.includes?.(phase);
}
