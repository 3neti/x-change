<script setup lang="ts">
import {
    normalizeFormFlowFieldType,
    formFlowFieldPreviewKind,
    type NormalizedFormFlow,
} from './formFlow';

import {
    resolveFormFlowRenderer,
} from './formFlowRendererRegistry';

import TextFieldRenderer from './renderers/TextFieldRenderer.vue';
import EmailFieldRenderer from './renderers/EmailFieldRenderer.vue';
import DateFieldRenderer from './renderers/DateFieldRenderer.vue';

defineProps<{
    formFlow: NormalizedFormFlow;
}>();

const rendererComponents = {
    TextFieldRenderer,
    EmailFieldRenderer,
    DateFieldRenderer,
} as const;

function rendererComponentName(field: { type?: string }): keyof typeof rendererComponents | null {
    const rendererName = resolveFormFlowRenderer(
        normalizeFormFlowFieldType(field.type ?? 'text')
    );

    return rendererName in rendererComponents
        ? rendererName as keyof typeof rendererComponents
        : null;
}
</script>

<template>
    <div data-testid="form-flow-renderer">
        <div data-testid="form-flow-key">
            {{ formFlow.key }}
        </div>

        <div data-testid="form-flow-owner">
            {{ formFlow.owner ?? '' }}
        </div>

        <div data-testid="form-flow-source">
            {{ formFlow.source ?? '' }}
        </div>

        <div data-testid="form-flow-field-count">
            {{ formFlow.fields.length }}
        </div>

        <div data-testid="form-flow-stage-count">
            {{ formFlow.stages.length }}
        </div>

        <div data-testid="form-flow-fields">
            <div
                v-for="field in formFlow.fields"
                :key="field.key"
                data-testid="form-flow-field"
            >
        <span data-testid="form-flow-field-key">
            {{ field.key }}
        </span>

                <span data-testid="form-flow-field-type">
 {{ normalizeFormFlowFieldType(field.type ?? 'text') }}
        </span>

                <span data-testid="form-flow-field-label">
            {{ field.label ?? field.key }}
        </span>

                <span data-testid="form-flow-field-required">
            {{ field.required ? 'required' : 'optional' }}
        </span>
            </div>
        </div>

        <div data-testid="form-flow-field-preview-rows">
            <div
                v-for="field in formFlow.fields"
                :key="`preview-${field.key}`"
                data-testid="form-flow-field-preview-row"
            >
                <div data-testid="form-flow-field-preview-label">
                    {{ field.label ?? field.key }}
                </div>

                <div data-testid="form-flow-field-preview-meta">
                    {{ normalizeFormFlowFieldType(field.type ?? 'text') }}
                    ·
                    {{ field.required ? 'required' : 'optional' }}
                </div>

                <div data-testid="form-flow-field-preview-kind">
                    {{ formFlowFieldPreviewKind(field.type ?? 'text') }}
                </div>

                <div data-testid="form-flow-field-preview-renderer">
                    {{
                        resolveFormFlowRenderer(
                            normalizeFormFlowFieldType(field.type ?? 'text')
                        )
                    }}
                </div>

                <component
                    v-if="rendererComponentName(field)"
                    :is="rendererComponents[rendererComponentName(field)!]"
                    :field="field"
                />
            </div>
        </div>
    </div>
</template>
