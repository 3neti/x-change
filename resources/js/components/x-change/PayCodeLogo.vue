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
        return '/vendor/x-change/images/pay-code/pay-code-mark.png';
    }

    if (props.variant === 'lockup') {
        return '/vendor/x-change/images/pay-code/pay-code-lockup.png';
    }

    return '/vendor/x-change/images/pay-code/pay-code-logo.png';
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
        class="pay-code-logo"
        :class="[variant, className]"
        :src="logoSrc"
        :alt="altText"
        v-bind="$attrs"
    />
</template>

<style scoped>
.pay-code-logo {
    display: block;
    width: auto;
    object-fit: contain;
}

.pay-code-logo.mark {
    height: 3rem;
    max-height: 3rem;
    max-width: 3rem;
}

.pay-code-logo.logo {
    height: 3.25rem;
    max-height: 3.25rem;
    max-width: 13rem;
}

.pay-code-logo.lockup {
    height: 7rem;
    max-height: 7rem;
    max-width: 16rem;
}
</style>
