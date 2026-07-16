<script setup lang="ts">
import type { CockpitActivityItem } from '../types';

defineProps<{
    items: CockpitActivityItem[];
}>();

function displayStatus(value: string | undefined): string {
    const normalized = value?.trim();

    if (!normalized) {
        return 'Not connected';
    }

    if (normalized === 'durable_summary_evidence_available') {
        return 'Summary evidence available';
    }

    if (normalized === 'runtime_handoff_profile_only') {
        return 'Runtime profile only';
    }

    if (normalized === 'not_wired') {
        return 'Not connected';
    }

    if (normalized === 'not-loaded') {
        return 'No data loaded';
    }

    if (normalized === 'read-model-ready') {
        return 'Ready for display';
    }

    return normalized
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function displayTarget(value: string): string {
    if (value === 'handoff_summary_journal') {
        return 'handoff summary';
    }

    return value.replaceAll('_', ' ');
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-recent-activity-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Settlement Activity
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Recent settlement events
        </h3>

        <div class="mt-5 space-y-3">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-100 px-3 py-3 dark:border-slate-800"
                data-testid="cockpit-activity-item"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ item.label }}
                    </p>
                    <span class="inline-flex min-h-5 shrink-0 items-center justify-center whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-center text-[0.65rem] font-semibold uppercase leading-none tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        {{ item.source }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ item.description }}
                </p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ item.timestamp }}
                </p>
                <div
                    v-if="item.projection_status"
                    class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100"
                    data-testid="cockpit-activity-projection-status"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex min-h-5 shrink-0 items-center justify-center whitespace-nowrap rounded-full bg-white px-2 py-0.5 text-center font-semibold uppercase leading-none tracking-wide text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100">
                            {{ item.projection_badge ?? 'Settlement evidence' }}
                        </span>
                        <span class="font-semibold">
                            {{ displayStatus(item.projection_status) }}
                        </span>
                    </div>
                    <p
                        v-if="item.projection_detail"
                        class="mt-2 leading-5 text-emerald-800 dark:text-emerald-200"
                    >
                        {{ item.projection_detail }}
                    </p>
                    <p
                        v-if="item.projection_targets?.length"
                        class="mt-2 text-emerald-700 dark:text-emerald-300"
                    >
                        Evidence sources: {{ item.projection_targets.map(displayTarget).join(', ') }}
                    </p>
                </div>
            </article>
        </div>
    </section>
</template>
