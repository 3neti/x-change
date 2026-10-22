<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Image } from 'lucide-vue-next';
import { ref } from 'vue';
import type { CockpitPayCodeStamp } from '../types';

defineProps<{
    code: string;
    stamp?: CockpitPayCodeStamp;
}>();

const imageFailed = ref(false);
</script>

<template>
    <Link
        v-if="stamp?.available && stamp.thumbnail_url && stamp.detail_url"
        :href="stamp.detail_url"
        :aria-label="`View stamp for Pay Code ${code}`"
        class="block w-22 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-sm transition hover:ring-2 hover:ring-slate-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-700 dark:border-slate-700 dark:bg-slate-800"
        data-testid="cockpit-pay-code-stamp-thumbnail"
    >
        <img
            v-if="!imageFailed"
            :src="stamp.thumbnail_url"
            alt=""
            loading="lazy"
            decoding="async"
            class="aspect-[40/21] w-full object-cover"
            @error="imageFailed = true"
        />
        <span
            v-else
            class="flex aspect-[40/21] w-full items-center justify-center text-slate-500"
            title="Open the current stamp rendering"
            data-testid="cockpit-pay-code-stamp-reconstruction-link"
        >
            <Image class="size-4" aria-hidden="true" />
        </span>
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
