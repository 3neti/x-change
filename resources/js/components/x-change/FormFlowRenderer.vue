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
    </div>
</template>
