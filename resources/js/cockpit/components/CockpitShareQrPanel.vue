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
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-share-qr-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Share / QR
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Share asset readiness
        </h3>

        <div
            class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2"
            data-testid="cockpit-share-asset-density-summary"
        >
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Share Assets
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ assets.length }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Deferred Assets
                </p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ deferredAssetCount() }}
                </p>
            </div>
        </div>

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
                        Asset details
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ asset.helper }}
                    </p>
                </details>
            </article>
        </div>
    </section>
</template>
