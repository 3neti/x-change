<script setup lang="ts">
import { computed } from 'vue';
import CockpitLiquidityHero from '../components/CockpitLiquidityHero.vue';
import CockpitRecentActivityPanel from '../components/CockpitRecentActivityPanel.vue';
import CockpitRedemptionPipeline from '../components/CockpitRedemptionPipeline.vue';
import CockpitRiskExpiryPanel from '../components/CockpitRiskExpiryPanel.vue';
import {
    cockpitDashboardMetrics,
    cockpitRecentActivityItems,
    cockpitRedemptionPipelineStages,
    cockpitRiskSignals,
} from '../dashboardDefaults';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitActivityItem,
    CockpitDashboardMetric,
    CockpitDashboardPageProps,
    CockpitPipelineStage,
    CockpitRiskSignal,
} from '../types';

const props = defineProps<CockpitDashboardPageProps>();

const readModel = computed(() => props.dashboard_read_model);

const metrics = computed<CockpitDashboardMetric[]>(() => {
    if (!readModel.value?.authorized || !Array.isArray(readModel.value.metrics) || readModel.value.metrics.length === 0) {
        return cockpitDashboardMetrics;
    }

    return readModel.value.metrics
        .map((metric) => sanitizeMetric(metric))
        .filter((metric): metric is CockpitDashboardMetric => metric !== null);
});

const pipeline = computed<CockpitPipelineStage[]>(() => {
    if (!readModel.value?.authorized || !Array.isArray(readModel.value.pipeline) || readModel.value.pipeline.length === 0) {
        return cockpitRedemptionPipelineStages;
    }

    return readModel.value.pipeline
        .map((stage) => sanitizePipelineStage(stage))
        .filter((stage): stage is CockpitPipelineStage => stage !== null);
});

const riskSignals = computed<CockpitRiskSignal[]>(() => {
    if (!readModel.value?.authorized || !Array.isArray(readModel.value.risk_signals) || readModel.value.risk_signals.length === 0) {
        return cockpitRiskSignals;
    }

    return readModel.value.risk_signals
        .map((signal) => sanitizeRiskSignal(signal))
        .filter((signal): signal is CockpitRiskSignal => signal !== null);
});

const activity = computed<CockpitActivityItem[]>(() => {
    if (!readModel.value?.authorized || !Array.isArray(readModel.value.activity) || readModel.value.activity.length === 0) {
        return cockpitRecentActivityItems;
    }

    return readModel.value.activity
        .map((item) => sanitizeActivityItem(item))
        .filter((item): item is CockpitActivityItem => item !== null);
});

function sanitizeMetric(metric: CockpitDashboardMetric): CockpitDashboardMetric | null {
    const key = stringValue(metric.key);
    const label = stringValue(metric.label);
    const value = stringValue(metric.value);

    if (!key || !label || !value) {
        return null;
    }

    return {
        key,
        label,
        value,
        helper: stringValue(metric.helper),
        tone: toneValue(metric.tone),
    };
}

function sanitizePipelineStage(stage: CockpitPipelineStage): CockpitPipelineStage | null {
    const key = stringValue(stage.key);
    const label = stringValue(stage.label);
    const value = stringValue(stage.value);

    if (!key || !label || !value) {
        return null;
    }

    return {
        key,
        label,
        value,
        tone: toneValue(stage.tone),
    };
}

function sanitizeRiskSignal(signal: CockpitRiskSignal): CockpitRiskSignal | null {
    const key = stringValue(signal.key);
    const label = stringValue(signal.label);
    const value = stringValue(signal.value);
    const severity = severityValue(signal.severity);

    if (!key || !label || !value || !severity) {
        return null;
    }

    return {
        key,
        label,
        value,
        severity,
    };
}

function sanitizeActivityItem(item: CockpitActivityItem): CockpitActivityItem | null {
    const id = stringValue(item.id);
    const label = stringValue(item.label);
    const description = stringValue(item.description);
    const timestamp = stringValue(item.timestamp);
    const source = sourceValue(item.source);

    if (!id || !label || !description || !timestamp || !source) {
        return null;
    }

    return {
        id,
        label,
        description,
        timestamp,
        source,
    };
}

function stringValue(value: unknown): string | undefined {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return undefined;
}

function toneValue(value: unknown): CockpitDashboardMetric['tone'] {
    return value === 'healthy' || value === 'warning' || value === 'critical' ? value : 'neutral';
}

function severityValue(value: unknown): CockpitRiskSignal['severity'] | undefined {
    return value === 'watch' || value === 'warning' || value === 'critical' ? value : undefined;
}

function sourceValue(value: unknown): CockpitActivityItem['source'] | undefined {
    return value === 'execution' || value === 'journal' || value === 'action' || value === 'feedback' || value === 'system'
        ? value
        : undefined;
}
</script>

<template>
    <CockpitLayout active-navigation="dashboard">
        <section class="space-y-6" data-testid="cockpit-dashboard-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 2
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Cockpit Dashboard Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This dashboard foundation composes read-only operator panels. It does not execute vouchers,
                    write journal entries, resolve workflow authority, send feedback, call providers, or move money.
                </p>
            </div>

            <CockpitLiquidityHero :metrics="metrics" />

            <div class="grid gap-4 xl:grid-cols-3">
                <CockpitRedemptionPipeline
                    class="xl:col-span-2"
                    :stages="pipeline"
                />
                <CockpitRiskExpiryPanel :signals="riskSignals" />
            </div>

            <CockpitRecentActivityPanel :items="activity" />
        </section>
    </CockpitLayout>
</template>
