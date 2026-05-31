<script setup lang="ts">
import type { FormFlowField } from '../formFlow';
import UnsupportedFieldRendererShell from './UnsupportedFieldRendererShell.vue';

const props = defineProps<{
    field: FormFlowField;
    value?: unknown;
}>();
</script>

<template>
    <UnsupportedFieldRendererShell
        :field="props.field"
        :value="props.value"
        test-id="unsupported-field-renderer"
    />
</template>
