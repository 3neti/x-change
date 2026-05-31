<script setup lang="ts">
import type { FormFlowField } from '../formFlow';
import ReadonlyFieldRendererShell from './ReadonlyFieldRendererShell.vue';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <ReadonlyFieldRendererShell
        :field="field"
        kind="text field"
        test-id="text-field-renderer"
    />
</template>
