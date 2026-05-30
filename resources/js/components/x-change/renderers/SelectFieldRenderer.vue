<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="select-field-renderer">
        <div data-testid="select-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="select-field-renderer-kind">
            select field
        </div>

        <div data-testid="select-field-renderer-required">
            {{ field.required ? 'required' : 'optional' }}
        </div>
    </div>
</template>
