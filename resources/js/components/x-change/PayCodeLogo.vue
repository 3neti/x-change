<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type PayCodeLogoVariant = 'mark' | 'logo' | 'lockup';

const props = withDefaults(
    defineProps<{
        variant?: PayCodeLogoVariant;
        className?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'logo',
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

const sizeClass = computed(() => {
    if (props.variant === 'mark') {
        return 'h-12 max-h-12 max-w-12';
    }

    if (props.variant === 'lockup') {
        return 'h-32 max-h-32 max-w-32';
    }

    return 'h-24 max-h-24 max-w-24';
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
        :class="[sizeClass, className]"
        :src="logoSrc"
        :alt="altText"
        v-bind="$attrs"
    />
</template>
