<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
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
        <div class="space-y-4 text-center">
            <AlertCircle class="mx-auto h-12 w-12 text-destructive" />

            <h1 class="text-xl font-semibold tracking-tight">
                Unable to Claim
            </h1>

            <p class="text-sm text-muted-foreground">
                {{ message }}
            </p>

            <p v-if="code" class="font-mono text-xs text-muted-foreground">
                Code: {{ code }}
            </p>

            <Button
                class="w-full"
                variant="outline"
                @click="$inertia.visit('/x/dashboard')"
            >
                Go to Dashboard
            </Button>
        </div>
    </ClaimStepShell>
</template>
