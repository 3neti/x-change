<script setup lang="ts">
import type { FormFlowField } from '../formFlow';
import UnsupportedFieldRendererShell from './UnsupportedFieldRendererShell.vue';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <UnsupportedFieldRendererShell
        :field="field"
        test-id="unsupported-field-renderer"
    />
</template>
