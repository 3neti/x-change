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
        test-id="date-field-renderer"
    />
    <input
        data-testid="date-field-renderer-input"
        type="date"
        :value="props.value ?? ''"
        @input="emit('update:value', inputElementValue($event))"
    />
</template>
