<script setup lang="ts">
import { defineProps } from 'vue';

const props = defineProps<{
    qrCode: string | null;
}>();
</script>

<template>
    <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border flex">
        <img
            :src="props.qrCode"
            alt="QR Code"
            class="max-w-full max-h-full object-contain"
        />
    </div>
</template>
