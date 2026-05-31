<script setup lang="ts">
import type { FormFlowField } from '../formFlow';
import { formFlowFieldTypeDiagnostic } from '../formFlow';

defineProps<{
    field: FormFlowField;
    testId: string;
    value?: unknown;
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

        <div :data-testid="`${testId}-diagnostic-type`">
            {{ formFlowFieldTypeDiagnostic(field.type) }}
        </div>

        <div :data-testid="`${testId}-value`">
            {{ value ?? '' }}
        </div>
    </div>
</template>
