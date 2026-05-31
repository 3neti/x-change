<script setup lang="ts">
import type { FormFlowField } from '../formFlow';

defineProps<{
    field: FormFlowField;
    kind: string;
    testId: string
    value?: unknown;
}>();
</script>

<template>
    <div :data-testid="testId">
        <div :data-testid="`${testId}-label`">
            {{ field.label ?? field.key }}
        </div>

        <div :data-testid="`${testId}-kind`">
            {{ kind }}
        </div>

        <div :data-testid="`${testId}-required`">
            {{ field.required ? 'required' : 'optional' }}
        </div>

        <div :data-testid="`${testId}-value`">
            {{ value ?? '' }}
        </div>
    </div>
</template>
