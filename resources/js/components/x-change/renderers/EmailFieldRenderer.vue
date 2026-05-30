<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
}>();
</script>

<template>
    <div data-testid="email-field-renderer">
        <div data-testid="email-field-renderer-label">
            {{ field.label ?? field.key }}
        </div>

        <div data-testid="email-field-renderer-kind">
            email field
        </div>

        <div data-testid="email-field-renderer-required">
            {{ field.required ? 'required' : 'optional' }}
        </div>
    </div>
</template>
