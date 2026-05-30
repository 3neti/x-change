<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="text-field-renderer">
        <div data-testid="text-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="text-field-renderer-kind">
            text field
        </div>

        <div data-testid="text-field-renderer-required">
            {{ field.required ? 'required' : 'optional' }}
        </div>
    </div>
</template>
