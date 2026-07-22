<script setup lang="ts">
import type { CockpitShareAsset } from '../types';

const props = defineProps<{
    assets: CockpitShareAsset[];
}>();

function deferredAssetCount(): number {
    return props.assets.filter((asset) => ['deferred', 'blocked', 'planned'].includes(asset.value.toLowerCase())).length;
}
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-share-qr-panel"
    >
        <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                    Share options
                </p>
                <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                    Copy, QR, and short-link readiness
                </h3>
            </div>
            <dl
                class="flex flex-wrap gap-1.5 text-[0.7rem]"
                data-testid="cockpit-share-asset-density-summary"
            >
                <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <dt>Assets</dt>
                    <dd class="font-semibold">{{ assets.length }}</dd>
                </div>
                <div class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <dt>Deferred</dt>
                    <dd class="font-semibold">{{ deferredAssetCount() }}</dd>
                </div>
            </dl>
        </summary>

        <div class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <p class="max-w-2xl text-xs leading-5 text-slate-600 dark:text-slate-300">
                Only the claim URL can be copied today. QR codes and short links are future artifacts and are not generated here.
            </p>
        </div>

        <div class="mt-3 grid gap-2">
            <article
                v-for="asset in assets"
                :key="asset.key"
                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                data-testid="cockpit-share-asset"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ asset.label }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ asset.value }}
                    </span>
                </div>
                <details
                    class="mt-1.5 text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-share-asset-disclosure"
                >
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">
                        What this means
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ asset.helper }}
                    </p>
                </details>
            </article>
        </div>
    </details>
</template>
