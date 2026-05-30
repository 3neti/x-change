<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="number-field-renderer">
        <div data-testid="number-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="number-field-renderer-kind">
            number field
        </div>

        <div data-testid="number-field-renderer-required">
            {{ field.required ? 'required' : 'optional' }}
        </div>
    </div>
</template>
