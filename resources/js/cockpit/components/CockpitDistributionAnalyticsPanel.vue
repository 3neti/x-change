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
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-distribution-analytics-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Operational Analytics
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            Distribution status summary
        </h3>

        <div
            class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-950/40"
            data-testid="cockpit-distribution-analytics-density-summary"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Analytics Facts
            </p>
            <p class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                {{ metricSummary() }}
            </p>
        </div>

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
                        Metric details
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
    </section>
</template>
