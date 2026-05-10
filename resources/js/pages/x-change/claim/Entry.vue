<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClaimWidget from '@/components/x-change/ClaimWidget.vue';

defineOptions({ layout: null });

defineProps<{
    initial_code?: string | null;
}>();
</script>

<template>
    <Head title="Claim Pay Code" />
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gradient-to-b from-primary/5 via-background to-background p-6 md:p-10">
        <div class="w-full max-w-sm">
            <ClaimWidget :initial-code="initial_code" />
        </div>
    </div>
</template>
