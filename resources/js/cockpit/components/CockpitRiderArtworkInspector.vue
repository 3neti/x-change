<script setup lang="ts">
import { ImageOff } from 'lucide-vue-next';
import type {
    RiderStampArtworkSource,
    RiderStampFit,
    RiderStampPosition,
} from '../riderStampPreview';
import CockpitRiderPreviewFrame from './CockpitRiderPreviewFrame.vue';

withDefaults(
    defineProps<{
        artworkSource: RiderStampArtworkSource;
        fit: RiderStampFit;
        position: RiderStampPosition;
        previewDocument: string;
        previewTestId?: string;
        urlArtworkResolving?: boolean;
        urlArtworkMessage?: string;
    }>(),
    {
        previewTestId: 'cockpit-quick-generate-rider-stamp-preview',
        urlArtworkResolving: false,
        urlArtworkMessage: '',
    },
);
</script>

<template>
    <aside
        class="rounded-2xl border border-sky-200 bg-white p-3 shadow-sm dark:border-sky-900/60 dark:bg-slate-950"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold text-sky-950 dark:text-sky-100">
                    Artwork Preview
                </p>
                <p class="mt-0.5 text-[11px] text-sky-700 dark:text-sky-300">
                    {{
                        artworkSource === 'x_change'
                            ? 'x-change'
                            : artworkSource === 'none'
                              ? 'Text only'
                              : artworkSource === 'url'
                                ? 'Rider Link'
                                : 'Rider Splash'
                    }}
                </p>
            </div>
            <span
                class="rounded-full bg-sky-100 px-2 py-1 text-[10px] font-semibold text-sky-700 dark:bg-sky-950 dark:text-sky-200"
            >
                {{ fit === 'cover' ? 'Cover' : 'Contain' }} ·
                {{ position }}
            </span>
        </div>
        <CockpitRiderPreviewFrame
            v-if="artworkSource === 'url' || artworkSource === 'splash'"
            title="Selected Rider artwork"
            surface="stamp"
            class="mt-3 border-sky-200 dark:border-sky-900/60"
            :data-testid="previewTestId"
            :document="previewDocument"
        />
        <div
            v-else
            class="relative mt-3 aspect-[1200/630] overflow-hidden rounded-lg border border-sky-200 dark:border-sky-900/60"
            :class="
                artworkSource === 'x_change'
                    ? 'bg-[#fffaf0] dark:bg-[#19170f]'
                    : 'bg-slate-100 dark:bg-slate-900'
            "
        >
            <template v-if="artworkSource === 'x_change'">
                <div
                    class="absolute inset-y-0 left-0 w-2 bg-emerald-600"
                    aria-hidden="true"
                />
                <div class="grid h-full place-items-center p-5 text-center">
                    <div>
                        <p
                            class="text-sm font-black tracking-[0.2em] text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            x-change
                        </p>
                        <p
                            class="mt-2 text-xs font-semibold text-slate-600 dark:text-amber-100/70"
                        >
                            Money should adapt to people.
                        </p>
                    </div>
                </div>
            </template>
            <div
                v-else
                class="grid h-full place-items-center gap-2 p-5 text-center text-slate-500 dark:text-slate-400"
            >
                <ImageOff class="size-7" aria-hidden="true" />
                <p class="text-xs font-medium">No artwork selected</p>
            </div>
        </div>
        <p
            v-if="
                artworkSource === 'url' &&
                (urlArtworkResolving || urlArtworkMessage)
            "
            class="mt-2 text-[11px] text-sky-700 dark:text-sky-300"
            data-testid="cockpit-quick-generate-rider-artwork-status"
        >
            {{
                urlArtworkResolving
                    ? 'Loading Rider Link Artwork…'
                    : urlArtworkMessage
            }}
        </p>
    </aside>
</template>
