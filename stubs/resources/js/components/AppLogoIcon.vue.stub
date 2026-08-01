<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();

const isDark = ref(false);

const updateDarkMode = () => {
    isDark.value = document.documentElement.classList.contains('dark');
};

let observer: MutationObserver | null = null;

onMounted(() => {
    updateDarkMode();
    observer = new MutationObserver(updateDarkMode);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onUnmounted(() => {
    observer?.disconnect();
});

const logoSrc = computed(() => {
    return isDark.value
        ? '/vendor/x-change/images/logo-silver.png'
        : '/vendor/x-change/images/logo-orange.png';
});
</script>

<template>
    <img
        :src="logoSrc"
        alt="X-Change"
        :class="className"
        v-bind="$attrs"
    />
</template>
