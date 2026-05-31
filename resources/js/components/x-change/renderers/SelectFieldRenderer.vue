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
        kind="select field"
        test-id="select-field-renderer"
    />
</template>
