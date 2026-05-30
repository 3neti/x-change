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

export const SUPPORTED_FORM_FLOW_FIELD_TYPES = [
    'text',
    'email',
    'date',
    'number',
    'select',
    'textarea',
] as const;

export type SupportedFormFlowFieldType =
    typeof SUPPORTED_FORM_FLOW_FIELD_TYPES[number];

export function isSupportedFormFlowFieldType(
    type: unknown
): type is SupportedFormFlowFieldType {
    return typeof type === 'string'
        && (SUPPORTED_FORM_FLOW_FIELD_TYPES as readonly string[]).includes(type);
}

export function normalizeFormFlowFieldType(type: unknown): SupportedFormFlowFieldType | 'unsupported' {
    return isSupportedFormFlowFieldType(type)
        ? type
        : 'unsupported';
}

export function formFlowFieldPreviewKind(type: unknown): string {
    const normalized = normalizeFormFlowFieldType(type);

    if (normalized === 'unsupported') {
        return 'unsupported field';
    }

    return `${normalized} field`;
}
