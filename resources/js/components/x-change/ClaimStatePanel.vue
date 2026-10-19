<script setup lang="ts">
import ClaimStatusIcon from './ClaimStatusIcon.vue';
import type {
    ClaimIconRole,
    FormFlowPresentation,
} from '@/pages/form-flow/core/components/formFlowPresentation';
defineProps<{
    role: ClaimIconRole;
    profile?: FormFlowPresentation | null;
    iconTestId?: string;
}>();
</script>
<template>
    <section
        data-testid="claim-state-panel"
        data-flow-content
        data-flow-stack
        class="min-w-0 space-y-4 text-center"
    >
        <ClaimStatusIcon
            :role="role"
            :profile="profile"
            :data-testid="iconTestId"
        />
        <slot />
    </section>
</template>
