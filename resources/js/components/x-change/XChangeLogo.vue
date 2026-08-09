<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { xChangeBrandAssets } from './brandAssets';

defineOptions({
    inheritAttrs: false,
});

type XChangeLogoVariant =
    | 'logo'
    | 'mark'
    | 'lockup'
    | 'light'
    | 'dark'
    | 'monochrome';

const props = withDefaults(
    defineProps<{
        variant?: XChangeLogoVariant;
        alt?: string;
        className?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'logo',
        alt: 'x-change',
    },
);

const logoSrc = computed(() => xChangeBrandAssets[props.variant]);
</script>

<template>
    <img
        class="block w-auto object-contain"
        :class="className"
        :src="logoSrc"
        :alt="alt"
        v-bind="$attrs"
    />
</template>
