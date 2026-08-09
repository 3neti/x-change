<script setup lang="ts">
import type { CSSProperties, HTMLAttributes } from 'vue';
import { computed } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type PayCodeLogoVariant = 'mark' | 'logo' | 'lockup';
type PayCodeLogoSize = 'micro' | 'header' | 'brand' | 'display';

const props = withDefaults(
    defineProps<{
        variant?: PayCodeLogoVariant;
        size?: PayCodeLogoSize;
        className?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'logo',
        size: 'brand',
    },
);

const logoSrc = computed(() => {
    if (props.variant === 'mark') {
        return '/vendor/x-change/images/pay-code/pay-code-mark.svg';
    }

    if (props.variant === 'lockup') {
        return '/vendor/x-change/images/pay-code/pay-code-lockup.svg';
    }

    return '/vendor/x-change/images/pay-code/pay-code-logo.svg';
});

const sizeStyle = computed<CSSProperties>(() => {
    if (props.size === 'micro') {
        return props.variant === 'lockup'
            ? { height: '1.25rem', maxHeight: '1.25rem', maxWidth: '4rem' }
            : { height: '1.25rem', maxHeight: '1.25rem', maxWidth: '1.25rem' };
    }

    if (props.size === 'header') {
        return props.variant === 'lockup'
            ? { height: '2rem', maxHeight: '2rem', maxWidth: '7rem' }
            : { height: '2rem', maxHeight: '2rem', maxWidth: '2rem' };
    }

    if (props.size === 'display') {
        return props.variant === 'lockup'
            ? { height: '6rem', maxHeight: '6rem', maxWidth: '10rem' }
            : { height: '5rem', maxHeight: '5rem', maxWidth: '5rem' };
    }

    return props.variant === 'lockup'
        ? { height: '4rem', maxHeight: '4rem', maxWidth: '8rem' }
        : { height: '4rem', maxHeight: '4rem', maxWidth: '4rem' };
});

const altText = computed(() => {
    if (props.variant === 'mark') {
        return 'Pay Code mark';
    }

    return 'Pay Code';
});
</script>

<template>
    <img
        class="block w-auto object-contain"
        :class="className"
        :style="sizeStyle"
        :src="logoSrc"
        :alt="altText"
        v-bind="$attrs"
    />
</template>
