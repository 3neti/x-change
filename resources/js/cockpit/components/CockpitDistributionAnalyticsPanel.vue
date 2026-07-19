<script setup lang="ts">
import type { CockpitDistributionMetric } from '../types';

const props = defineProps<{
    metrics: CockpitDistributionMetric[];
}>();

function metricSummary(): string {
    return `${props.metrics.length} read-only facts`;
}

function stringValue(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function metricMetadata(metric: CockpitDistributionMetric): Array<{ label: string; value: string }> {
    const metadata = metric.metadata ?? {};
    const eventType = stringValue(metadata.event_type);
    const payloadPolicy = stringValue(metadata.payload_policy);
    const evidenceOnly = stringValue(metadata.evidence_only);
    const writesJournal = stringValue(metadata.writes_journal);

    return [
        eventType === null ? null : { label: 'Event Type', value: eventType },
        payloadPolicy === null ? null : { label: 'Payload Policy', value: payloadPolicy },
        evidenceOnly === null ? null : { label: 'Evidence Only', value: evidenceOnly },
        writesJournal === null ? null : { label: 'Writes Journal', value: writesJournal },
    ].filter((item): item is { label: string; value: string } => item !== null);
}
</script>

<template>
    <details
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-distribution-analytics-panel"
    >
        <summary class="cursor-pointer list-none">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Status evidence
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Delivery and campaign signals
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                        These are read-only status facts from connected summaries. Open a row only if you need source details.
                    </p>
                </div>
                <dl
                    class="flex flex-wrap gap-2 text-xs"
                    data-testid="cockpit-distribution-analytics-density-summary"
                >
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <dt>Evidence Facts</dt>
                        <dd class="font-semibold">{{ metricSummary() }}</dd>
                    </div>
                </dl>
            </div>
        </summary>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <article
                v-for="metric in metrics"
                :key="metric.key"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-distribution-metric"
            >
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ metric.label }}
                </p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    {{ metric.value }}
                </p>
                <details
                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                    data-testid="cockpit-distribution-metric-disclosure"
                >
                    <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">
                        Why this status appears
                    </summary>
                    <p class="mt-2 leading-5">
                        {{ metric.helper }}
                    </p>
                    <dl
                        v-if="metricMetadata(metric).length > 0"
                        class="mt-3 grid gap-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-950/50 sm:grid-cols-2"
                        data-testid="cockpit-distribution-metric-metadata"
                    >
                        <div
                            v-for="item in metricMetadata(metric)"
                            :key="`${metric.key}-${item.label}`"
                        >
                            <dt class="font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ item.label }}
                            </dt>
                            <dd class="mt-1 break-words text-slate-700 dark:text-slate-200">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </article>
        </div>
    </details>
</template>
