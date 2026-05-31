import { getFormFlowFieldPresentation, type FormFlowField } from './formFlow';
import { resolveFormFlowRenderer } from './formFlowRendererRegistry';

import TextFieldRenderer from './renderers/TextFieldRenderer.vue';
import EmailFieldRenderer from './renderers/EmailFieldRenderer.vue';
import DateFieldRenderer from './renderers/DateFieldRenderer.vue';
import NumberFieldRenderer from './renderers/NumberFieldRenderer.vue';
import SelectFieldRenderer from './renderers/SelectFieldRenderer.vue';
import TextareaFieldRenderer from './renderers/TextareaFieldRenderer.vue';
import UnsupportedFieldRenderer from './renderers/UnsupportedFieldRenderer.vue';

export const FORM_FLOW_RENDERER_COMPONENTS = {
    TextFieldRenderer,
    EmailFieldRenderer,
    DateFieldRenderer,
    NumberFieldRenderer,
    SelectFieldRenderer,
    TextareaFieldRenderer,
    UnsupportedFieldRenderer,
} as const;

export type FormFlowRendererComponentName =
    keyof typeof FORM_FLOW_RENDERER_COMPONENTS;

export function hasFormFlowRendererComponent(
    name: string
): name is FormFlowRendererComponentName {
    return name in FORM_FLOW_RENDERER_COMPONENTS;
}

export function resolveFormFlowRendererComponentName(
    field: FormFlowField
): FormFlowRendererComponentName {
    const presentation = getFormFlowFieldPresentation(field);

    const rendererName = resolveFormFlowRenderer(
        presentation.normalizedType
    );

    return hasFormFlowRendererComponent(rendererName)
        ? rendererName
        : 'UnsupportedFieldRenderer';
}
