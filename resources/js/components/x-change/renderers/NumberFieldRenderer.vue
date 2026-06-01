<script setup lang="ts">
import { formFlowFieldRendererKind  } from '../formFlow';
import { inputElementValue } from './fieldInputEvents';
import type {FormFlowField} from '../formFlow';
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
        test-id="number-field-renderer"
    />
    <input
        data-testid="number-field-renderer-input"
        type="number"
        :value="props.value ?? ''"
        @input="emit('update:value', inputElementValue($event))"
    />
</template>
