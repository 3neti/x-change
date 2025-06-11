<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps<{
    qrCode: string | null;
}>();
</script>

<template>
    <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border flex">
        <!-- Label: 1/4 width -->
        <div class="w-1/4 flex flex-col items-center justify-center p-4">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 leading-snug text-center">
                Add<br />Funds
            </h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 text-center">
                using GCash App
            </p>
        </div>

        <!-- QR Code Image: 3/4 width -->
        <div v-if="props.qrCode" class="w-3/4 flex items-center justify-center bg-white dark:bg-black z-10">
            <img
                :src="props.qrCode"
                alt="QR Code"
                class="max-w-full max-h-full object-contain"
            />
        </div>
    </div>
</template>
