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
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-share-qr-panel"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Share options
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Copy, QR, and short-link readiness
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                        Only the claim URL can be copied today. QR codes and short links are future artifacts and are not generated here.
                    </p>
                </div>
                <dl
                    class="flex flex-wrap gap-2 text-xs"
                    data-testid="cockpit-share-asset-density-summary"
                >
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <dt>Assets</dt>
                        <dd class="font-semibold">{{ assets.length }}</dd>
                    </div>
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <dt>Deferred</dt>
                        <dd class="font-semibold">{{ deferredAssetCount() }}</dd>
                    </div>
                </dl>
            </div>
        </summary>

        <div class="mt-5 grid gap-3">
            <article
                v-for="asset in assets"
                :key="asset.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-share-asset"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ asset.label }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ asset.value }}
                    </span>
                </div>
                <details
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
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
