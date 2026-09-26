<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';

export interface ClaimWorkflowAttentionProps {
    key?: string | null;
    label?: string | null;
    message?: string | null;
}

withDefaults(defineProps<ClaimWorkflowAttentionProps>(), {
    key: null,
    label: 'Claim journey needs attention',
    message: 'Contact the issuer before continuing.',
});
</script>

<template>
    <div
        data-testid="claim-workflow-attention"
        role="status"
        class="rounded-lg border border-amber-500/25 bg-amber-500/10 p-4 text-left"
    >
        <div class="flex items-start gap-3">
            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
            <div class="space-y-1">
                <p class="text-foreground text-sm font-semibold">
                    {{ label }}
                </p>
                <p class="text-muted-foreground text-sm leading-6">
                    {{ message }}
                </p>
            </div>
        </div>
    </div>
</template>
