<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import {
    getFormFlowFieldPresentation,
    normalizeFormFlowFieldType,
} from './formFlow';
import type {NormalizedFormFlow} from './formFlow';

import {
    FORM_FLOW_RENDERER_COMPONENTS,
    resolveFormFlowRendererComponentName
} from './formFlowRendererComponents';


import {
    resolveFormFlowRenderer,
} from './formFlowRendererRegistry';

const props = defineProps<{
    formFlow: NormalizedFormFlow;
}>();

const fieldValues = reactive<Record<string, unknown>>({});

props.formFlow.fields.forEach((field) => {
    fieldValues[field.key] = props.formFlow.values?.[field.key] ?? field.value ?? null;
});

function fieldValue(fieldKey: string): unknown {
    return fieldValues[fieldKey] ?? null;
}

function updateFieldValue(fieldKey: string, value: unknown): void {
    fieldValues[fieldKey] = value;
}

function fieldPresentation(field: NormalizedFormFlow['fields'][number]) {
    return getFormFlowFieldPresentation(field);
}

const currentValues = computed<Record<string, unknown>>(() => {
    return { ...fieldValues };
});

const emit = defineEmits<{
    'update:values': [values: Record<string, unknown>];
}>();

watch(
    currentValues,
    (values) => {
        emit('update:values', values);
    },
    {
        immediate: true,
        deep: true,
    }
);
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
{{ fieldPresentation(field).diagnosticType }}
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
                    {{ fieldPresentation(field).normalizedType }}
                    ·
                    {{ field.required ? 'required' : 'optional' }}
                </div>

                <div data-testid="form-flow-field-preview-kind">
                    {{ fieldPresentation(field).previewKind }}
                </div>

                <div data-testid="form-flow-field-preview-renderer">
                    {{
                        resolveFormFlowRenderer(
                            normalizeFormFlowFieldType(field.type ?? 'text')
                        )
                    }}
                </div>

                <component
                    :is="FORM_FLOW_RENDERER_COMPONENTS[resolveFormFlowRendererComponentName(field)]"
                    :field="field"
                    :value="fieldValue(field.key)"
                    @update:value="updateFieldValue(field.key, $event)"
                />
            </div>
        </div>

        <pre
            data-testid="form-flow-current-values"
        >{{ JSON.stringify(currentValues, null, 2) }}</pre>
    </div>
</template>
