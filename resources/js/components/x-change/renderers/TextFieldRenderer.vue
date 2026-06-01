<script setup lang="ts">
import { formFlowFieldRendererKind  } from '../formFlow';
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
        test-id="text-field-renderer"
    />
    <button
        type="button"
        data-testid="text-field-renderer-update"
        @click="emit('update:value', 'Updated Text Value')"
    >
        update text value
    </button>
</template>
