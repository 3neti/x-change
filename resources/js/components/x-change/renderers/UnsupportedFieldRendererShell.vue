<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
    testId: string;
}>();
</script>

<template>
    <div :data-testid="testId">
        <div :data-testid="`${testId}-label`">
            {{ field.label ?? field.key }}
        </div>

        <div :data-testid="`${testId}-kind`">
            unsupported field
        </div>

        <div :data-testid="`${testId}-type`">
            {{ field.type ?? 'unknown' }}
        </div>
    </div>
</template>
