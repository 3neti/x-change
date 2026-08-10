<script setup lang="ts">
import { ref, watch } from 'vue';
import type { Component } from 'vue';

const props = withDefaults(defineProps<{
    iconAsset?: string | null;
    fallbackIcon?: Component | null;
    alt?: string;
    sizeClass?: string;
}>(), {
    iconAsset: null,
    fallbackIcon: null,
    alt: '',
    sizeClass: 'h-3.5 w-3.5',
});

// Icons are a supplementary trust cue, never a replacement for the text
// label rendered alongside this component. When an asset fails to load
// (missing file, network hiccup) we fall back to the neutral lucide glyph
// instead of showing a broken image.
const failed = ref(false);

watch(() => props.iconAsset, () => {
    failed.value = false;
});
</script>

<template>
    <img
        v-if="iconAsset && !failed"
        :src="iconAsset"
        :alt="alt"
        :class="sizeClass"
        class="shrink-0 rounded-sm object-contain"
        @error="failed = true"
    />
    <component
        :is="fallbackIcon"
        v-else-if="fallbackIcon"
        :class="sizeClass"
        class="shrink-0"
    />
</template>
