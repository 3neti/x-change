<script setup lang="ts">
import type { NormalizedFormFlow } from './formFlow';

defineProps<{
    formFlow: NormalizedFormFlow;
}>();
</script>

<template>
    <div data-testid="form-flow-renderer">
        compiled form flow renderer placeholder
    </div>
</template>
