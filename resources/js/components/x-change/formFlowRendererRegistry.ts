export const FORM_FLOW_RENDERERS = {
    text: 'TextFieldRenderer',
    email: 'EmailFieldRenderer',
    date: 'DateFieldRenderer',
    number: 'NumberFieldRenderer',
    select: 'SelectFieldRenderer',
    textarea: 'TextareaFieldRenderer',
    slice_selector: 'SliceSelectorFieldRenderer',
} as const;

export function resolveFormFlowRenderer(type: string): string {
    return FORM_FLOW_RENDERERS[
        type as keyof typeof FORM_FLOW_RENDERERS
        ] ?? 'UnsupportedFieldRenderer';
}
