<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        document: string;
        surface?: 'splash' | 'og-meta';
    }>(),
    {
        surface: 'splash',
    },
);

const aspectClass = computed<string>(() => {
    return props.surface === 'og-meta'
        ? 'aspect-[1200/630]'
        : 'aspect-[1.72/1]';
});
</script>

<template>
    <iframe
        :title="title"
        sandbox=""
        class="block w-full rounded-lg border bg-slate-950"
        :class="aspectClass"
        :srcdoc="document"
    />
</template>
