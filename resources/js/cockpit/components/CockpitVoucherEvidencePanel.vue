<script setup lang="ts">
import type { CockpitVoucherEvidenceItem } from '../types';

const props = defineProps<{
    items: CockpitVoucherEvidenceItem[];
    heading?: string;
}>();

function statusCount(status: string): number {
    return props.items.filter((item) => item.status === status).length;
}

function statusSummary(): string {
    const statuses = Array.from(new Set(props.items.map((item) => item.status))).filter((status) => status !== '');

    if (statuses.length === 0) {
        return 'No evidence facts';
    }

    return statuses
        .map((status) => `${status}: ${statusCount(status)}`)
        .join(' · ');
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-evidence-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Evidence
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            {{ heading ?? 'Evidence status' }}
        </h3>
        <dl
            class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-950 sm:grid-cols-2"
            data-testid="cockpit-voucher-evidence-density-summary"
        >
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Evidence Facts
                </dt>
                <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ items.length }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Status Summary
                </dt>
                <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                    {{ statusSummary() }}
                </dd>
            </div>
        </dl>

        <div class="mt-5 grid gap-3">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-voucher-evidence-item"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ item.label }}
                    </p>
                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ item.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ item.helper }}
                </p>
                <details
                    v-if="item.source || item.read_only !== undefined"
                    class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-950 dark:text-slate-400"
                    data-testid="cockpit-voucher-evidence-item-metadata"
                >
                    <summary class="cursor-pointer font-semibold uppercase tracking-wide">
                        Evidence metadata
                    </summary>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div v-if="item.source">
                            <dt class="font-semibold uppercase tracking-wide">
                                Source
                            </dt>
                            <dd>{{ item.source }}</dd>
                        </div>
                        <div v-if="item.read_only !== undefined">
                            <dt class="font-semibold uppercase tracking-wide">
                                Read-only
                            </dt>
                            <dd>{{ item.read_only ? 'yes' : 'no' }}</dd>
                        </div>
                    </dl>
                </details>
            </article>
        </div>
    </section>
</template>
