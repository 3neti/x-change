<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Image } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { CockpitPayCodeStamp } from '../types';

const props = defineProps<{
    code: string;
    stamp?: CockpitPayCodeStamp;
}>();

const imageFailed = ref(false);
const usingFallback = ref(false);
const imageUrl = computed(() => usingFallback.value ? props.stamp?.fallback_url : props.stamp?.thumbnail_url);

function handleImageError(): void {
    if (!usingFallback.value && props.stamp?.fallback_url) {
        usingFallback.value = true;
        return;
    }

    imageFailed.value = true;
}
</script>

<template>
    <Link
        v-if="stamp?.available && stamp.thumbnail_url && stamp.detail_url && !imageFailed"
        :href="stamp.detail_url"
        :aria-label="`View stamp for Pay Code ${code}`"
        class="block w-22 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-sm transition hover:ring-2 hover:ring-slate-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-700 dark:border-slate-700 dark:bg-slate-800"
        data-testid="cockpit-pay-code-stamp-thumbnail"
    >
        <img
            :src="imageUrl"
            alt=""
            loading="lazy"
            decoding="async"
            class="aspect-[40/21] w-full object-cover"
            @error="handleImageError"
        />
    </Link>
    <span
        v-else
        class="flex aspect-[40/21] w-22 shrink-0 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 text-slate-400 dark:border-slate-700 dark:bg-slate-800/50"
        :title="imageFailed ? 'Stamp image unavailable' : 'No saved stamp'"
        :aria-label="imageFailed ? 'Stamp image unavailable' : 'No saved stamp'"
        data-testid="cockpit-pay-code-stamp-placeholder"
    >
        <Image class="size-4" aria-hidden="true" />
    </span>
</template>
