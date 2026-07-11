<script setup lang="ts">
import type {
    CockpitDistributionAction,
    CockpitDistributionChannel,
} from '../types';

defineProps<{
    channels: CockpitDistributionChannel[];
    actions: CockpitDistributionAction[];
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-digital-distribution-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Digital Distribution
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Channel planning placeholder
        </h3>

        <div class="mt-5 grid gap-3">
            <article
                v-for="channel in channels"
                :key="channel.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-distribution-channel"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ channel.label }}
                    </p>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ channel.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ channel.helper }}
                </p>
            </article>
        </div>

        <div class="mt-5 grid gap-2 sm:grid-cols-2">
            <div
                v-for="action in actions"
                :key="action.key"
                class="rounded-md border border-slate-200 p-3 dark:border-slate-700"
            >
                <button
                    :disabled="action.disabled"
                    :title="action.reason"
                    type="button"
                    class="rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:text-slate-300"
                    data-testid="cockpit-distribution-action"
                >
                    {{ action.label }}
                </button>
                <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                    {{ action.reason }}
                </p>
            </div>
        </div>
    </section>
</template>
