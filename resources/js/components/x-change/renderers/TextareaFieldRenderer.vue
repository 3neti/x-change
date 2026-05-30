<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="textarea-field-renderer">
        <div data-testid="textarea-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="textarea-field-renderer-kind">
            textarea field
        </div>

        <div data-testid="textarea-field-renderer-required">
            {{ field.required ? 'required' : 'optional' }}
        </div>
    </div>
</template>
