<script setup lang="ts">
import { ref, watch } from 'vue';
import type { RiderStampArtworkSource } from '../riderStampPreview';

const props = withDefaults(
    defineProps<{
        source: RiderStampArtworkSource;
        imageUrl?: string | null;
        title?: string;
        description?: string;
        resolving?: boolean;
    }>(),
    {
        imageUrl: null,
        title: '',
        description: '',
        resolving: false,
    },
);

const imageFailed = ref(false);

watch(
    () => props.imageUrl,
    (): void => {
        imageFailed.value = false;
    },
);
</script>

<template>
    <div
        class="relative aspect-[1200/630] w-full overflow-hidden rounded-md border border-black/10 bg-slate-100 dark:border-white/10 dark:bg-slate-900"
        aria-hidden="true"
        data-testid="cockpit-rider-artwork-thumbnail"
    >
        <div
            v-if="resolving"
            class="grid h-full place-items-center bg-slate-100 dark:bg-slate-900"
            data-testid="cockpit-rider-artwork-thumbnail-loading"
        >
            <span class="size-3 animate-pulse rounded-full bg-sky-500"></span>
        </div>
        <template v-else-if="source === 'x_change'">
            <div
                class="absolute inset-y-0 left-0 w-1 bg-emerald-600"
                aria-hidden="true"
            />
            <div
                class="grid h-full place-items-center bg-[#fffaf0] px-1 text-center dark:bg-[#19170f]"
                data-testid="cockpit-rider-artwork-thumbnail-x-change"
            >
                <div class="min-w-0">
                    <p
                        class="truncate text-[8px] font-black tracking-[0.12em] text-emerald-700 uppercase dark:text-emerald-300"
                    >
                        x-change
                    </p>
                    <p
                        class="mt-0.5 truncate text-[6px] font-semibold text-slate-500 dark:text-amber-100/60"
                    >
                        Money adapts
                    </p>
                </div>
            </div>
        </template>
        <template v-else-if="source === 'none'">
            <div
                class="grid h-full place-items-center bg-slate-100 p-1 dark:bg-slate-900"
                data-testid="cockpit-rider-artwork-thumbnail-none"
            >
                <div
                    class="h-3/5 w-4/5 rounded-sm border border-dashed border-slate-300 bg-white/70 dark:border-slate-700 dark:bg-slate-950/70"
                />
            </div>
        </template>
        <template v-else>
            <img
                v-if="imageUrl !== null && !imageFailed"
                :src="imageUrl"
                alt=""
                class="h-full w-full object-cover"
                loading="lazy"
                decoding="async"
                data-testid="cockpit-rider-artwork-thumbnail-image"
                @error="imageFailed = true"
            />
            <div
                v-else
                class="grid h-full place-items-center bg-gradient-to-br p-1 text-center"
                :class="
                    source === 'url'
                        ? 'from-sky-100 to-indigo-200 text-sky-950 dark:from-sky-950 dark:to-indigo-950 dark:text-sky-100'
                        : 'from-violet-100 to-rose-200 text-violet-950 dark:from-violet-950 dark:to-rose-950 dark:text-violet-100'
                "
                data-testid="cockpit-rider-artwork-thumbnail-fallback"
            >
                <div class="min-w-0">
                    <p class="truncate text-[7px] font-bold">
                        {{
                            title ||
                            (source === 'url' ? 'Rider Link' : 'Rider Splash')
                        }}
                    </p>
                    <p
                        v-if="description"
                        class="mt-0.5 truncate text-[6px] opacity-70"
                    >
                        {{ description }}
                    </p>
                </div>
            </div>
            <div
                v-if="imageUrl !== null && !imageFailed && title"
                class="absolute inset-x-0 bottom-0 bg-black/55 px-1 py-0.5 text-left text-[6px] font-semibold text-white"
            >
                <p class="truncate">{{ title }}</p>
            </div>
        </template>
    </div>
</template>
