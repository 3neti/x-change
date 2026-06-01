<script setup lang="ts">
import { formFlowFieldRendererKind  } from '../formFlow';
import type {FormFlowField} from '../formFlow';
import { emitInputElementValue } from './fieldInputEvents';
import ReadonlyFieldRendererShell from './ReadonlyFieldRendererShell.vue';

const props = defineProps<{
    field: FormFlowField;
    value?: unknown;
}>();

const emit = defineEmits<{
    'update:value': [value: unknown];
}>();
</script>

<template>
    <ReadonlyFieldRendererShell
        :field="props.field"
        :value="props.value"
        :kind="formFlowFieldRendererKind(props.field.type ?? 'text')"
        test-id="email-field-renderer"
    />
    <input
        data-testid="email-field-renderer-input"
        type="email"
        :value="props.value ?? ''"
        @input="emitInputElementValue(emit, $event)"
    />
</template>
