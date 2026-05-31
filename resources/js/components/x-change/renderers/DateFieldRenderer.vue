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
        kind="date field"
        test-id="date-field-renderer"
    />
</template>
