<script setup lang="ts">
import type { NormalizedFormFlow } from './formFlow';

defineProps<{
    formFlow: NormalizedFormFlow;
}>();
</script>

<template>
    <div data-testid="form-flow-renderer">
        <div data-testid="form-flow-key">
            {{ formFlow.key }}
        </div>

        <div data-testid="form-flow-owner">
            {{ formFlow.owner ?? '' }}
        </div>

        <div data-testid="form-flow-source">
            {{ formFlow.source ?? '' }}
        </div>

        <div data-testid="form-flow-field-count">
            {{ formFlow.fields.length }}
        </div>

        <div data-testid="form-flow-stage-count">
            {{ formFlow.stages.length }}
        </div>

        <div data-testid="form-flow-fields">
            <div
                v-for="field in formFlow.fields"
                :key="field.key"
                data-testid="form-flow-field"
            >
        <span data-testid="form-flow-field-key">
            {{ field.key }}
        </span>

                <span data-testid="form-flow-field-type">
            {{ field.type ?? 'text' }}
        </span>

                <span data-testid="form-flow-field-label">
            {{ field.label ?? field.key }}
        </span>

                <span data-testid="form-flow-field-required">
            {{ field.required ? 'required' : 'optional' }}
        </span>
            </div>
        </div>
    </div>
</template>
