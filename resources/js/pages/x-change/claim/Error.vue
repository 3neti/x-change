<script setup lang="ts">
import ClaimStatePanel from '@/components/x-change/ClaimStatePanel.vue';
import FormFlowActions from '@/pages/form-flow/core/components/FormFlowActions.vue';

import { Head } from '@inertiajs/vue3';
import FormFlowAction from '@/pages/form-flow/core/components/FormFlowAction.vue';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';
import { AlertCircle } from 'lucide-vue-next';

defineOptions({ layout: null });

defineProps<{
    message: string;
    code?: string;
}>();
</script>

<template>
    <Head title="Claim Error" />

    <ClaimStepShell tone="danger" width="sm">
        <ClaimStatePanel role="error" class="space-y-4 text-center">
            <h1 class="text-xl font-semibold tracking-tight">
                Unable to Claim
            </h1>

            <p class="text-sm text-muted-foreground">
                {{ message }}
            </p>

            <p v-if="code" class="font-mono text-xs text-muted-foreground">
                Code: {{ code }}
            </p>

            <FormFlowActions :show-secondary="false"
                ><FormFlowAction
                    role="open_link"
                    label="Go to Dashboard"
                    class="w-full"
                    variant="outline"
                    @click="$inertia.visit('/x/dashboard')"
                >
                </FormFlowAction
            ></FormFlowActions>
        </ClaimStatePanel>
    </ClaimStepShell>
</template>
