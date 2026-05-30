export type FormFlowField = {
    key: string;
    type?: string;
    label?: string;
    required?: boolean;
};

export type NormalizedFormFlow = {
    key: string;
    owner?: string;
    source?: string;
    fields: FormFlowField[];
    stages: Record<string, any>[];
};

export function normalizeFormFlowPhase(phase: Record<string, any>): NormalizedFormFlow {
    return {
        key: String(phase.key ?? 'form_flow'),
        owner: typeof phase.owner === 'string' ? phase.owner : undefined,
        source: typeof phase.source === 'string' ? phase.source : undefined,
        fields: Array.isArray(phase.fields) ? phase.fields : [],
        stages: Array.isArray(phase.stages) ? phase.stages : [],
    };
}
