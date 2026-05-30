<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="date-field-renderer">
        <div data-testid="date-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="date-field-renderer-kind">
            date field
        </div>

        <div data-testid="date-field-renderer-required">
            {{ field.required ? 'required' : 'optional' }}
        </div>
    </div>
</template>
