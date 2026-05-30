<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="unsupported-field-renderer">
        <div data-testid="unsupported-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="unsupported-field-renderer-kind">
            unsupported field
        </div>

        <div data-testid="unsupported-field-renderer-type">
            {{ field.type ?? 'unknown' }}
        </div>
    </div>
</template>
