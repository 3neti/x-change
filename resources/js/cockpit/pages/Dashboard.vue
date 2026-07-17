<script setup lang="ts">
import { computed, ref } from 'vue';
import CockpitCampaignAdoptionPanel from '../components/CockpitCampaignAdoptionPanel.vue';
import CockpitLiquidityHero from '../components/CockpitLiquidityHero.vue';
import CockpitOperatorIssuanceActivityPanel from '../components/CockpitOperatorIssuanceActivityPanel.vue';
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
    CockpitDependentReadModel,
    CockpitPipelineStage,
    CockpitReadModelRedactions,
    CockpitRiskSignal,
} from '../types';

const props = defineProps<CockpitDashboardPageProps>();

const readModel = computed(() => props.dashboard_read_model);
const expandedIntegrationDetails = ref<Record<string, boolean>>({});
const headerBalances = computed(() => {
    const balances = props.cockpit_header_read_model?.balances;

    return Array.isArray(balances) && balances.length > 0
        ? balances
        : undefined;
});

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

const integrationSummaries = computed(() => [
    integrationSummary(
        'journal',
        'Journal Evidence',
        props.read_model?.journal,
        'entries',
        'entries',
        'journal-evidence-summary-only',
    ),
    integrationSummary(
        'actions',
        'Action CTAs',
        props.read_model?.actions,
        'actions',
        'actions',
        'safe-action-host-summary-only',
    ),
    integrationSummary(
        'feedback',
        'Feedback Deliveries',
        props.read_model?.feedback,
        'deliveries',
        'deliveries',
        'communication-delivery-summary-only',
    ),
]);

const operatingSummaryCards = computed(() => [
    {
        key: 'pay-codes',
        label: 'Pay Codes',
        value: metricValue('pay-codes-visible') ?? '0',
        description: 'Sanitized Pay Code rows available for operator inspection.',
        href: '/x/cockpit/pay-codes',
        action: 'Open Pay Code Explorer',
    },
    {
        key: 'quick-generate',
        label: 'Quick Generate',
        value: latestIssuanceStatus.value,
        description: 'Template-first issuance handoff through existing x-change generation.',
        href: '/x/cockpit/quick-generate',
        action: 'Generate Pay Code',
    },
    {
        key: 'attention',
        label: 'Needs Attention',
        value: metricValue('needs-attention') ?? riskSignals.value[0]?.value ?? '0',
        description: 'Expired, awaiting approval, or review-oriented summaries only.',
        href: '/x/cockpit/pay-codes?status=expired',
        action: 'Review attention queue',
    },
]);

const latestIssuanceStatus = computed(() => {
    const presentations = Array.isArray(props.operator_issuance_activity_read_model?.presentations)
        ? props.operator_issuance_activity_read_model.presentations
        : [];

    const firstStatus = presentations
        .map((presentation) => stringValue(presentation.status))
        .find((status): status is string => status !== undefined);

    return firstStatus ?? 'ready';
});

const operatingIntegrationStatus = computed(() => {
    const availableCount = integrationSummaries.value.filter((summary) => summary.status === 'available').length;

    if (availableCount === integrationSummaries.value.length) {
        return 'Summaries connected';
    }

    if (availableCount > 0) {
        return `${availableCount}/${integrationSummaries.value.length} summaries connected`;
    }

    return 'Journal, action, and feedback summaries not connected yet';
});

function metricValue(key: string): string | undefined {
    return metrics.value.find((metric) => metric.key === key)?.value;
}

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
        projection_badge: stringValue(item.projection_badge),
        projection_status: stringValue(item.projection_status),
        projection_detail: stringValue(item.projection_detail),
        projection_targets: Array.isArray(item.projection_targets)
            ? item.projection_targets
                  .map((target) => stringValue(target))
                  .filter((target): target is string => target !== undefined)
            : undefined,
        metadata: typeof item.metadata === 'object' && item.metadata !== null ? item.metadata : undefined,
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

function integrationSummary(
    key: string,
    label: string,
    model: CockpitDependentReadModel | undefined,
    collectionKey: 'entries' | 'actions' | 'deliveries',
    noun: string,
    fallbackPolicy: string,
): {
    key: string;
    label: string;
    status: string;
    count: string;
    policy: string;
    reason: string;
} {
    const collection = Array.isArray(model?.[collectionKey]) ? model[collectionKey] : [];

    return {
        key,
        label,
        status: stringValue(model?.status) ?? 'not_wired',
        count: `${collection.length} ${noun}`,
        policy: stringValue((model?.redactions as CockpitReadModelRedactions | undefined)?.payloads) ?? fallbackPolicy,
        reason: stringValue((model?.redactions as CockpitReadModelRedactions | undefined)?.reason) ?? 'read-model-ready',
    };
}

function displayStatus(value: string): string {
    const normalized = value.trim();

    if (normalized === 'not_wired') {
        return 'Not connected';
    }

    if (normalized === 'not-loaded') {
        return 'No data loaded';
    }

    if (normalized === 'read-model-ready') {
        return 'Ready for display';
    }

    if (normalized === 'presentation_only') {
        return 'Read-only';
    }

    if (normalized === 'available') {
        return 'Available';
    }

    return normalized
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

const integrationReadinessNote = computed(() => {
    const availableCount = integrationSummaries.value.filter((summary) => summary.status === 'available').length;

    if (availableCount === integrationSummaries.value.length) {
        return 'Audit, follow-up, and notification summaries are available for read-only display.';
    }

    if (availableCount > 0) {
        return 'Some service summaries are connected; unavailable services stay visibly marked as not connected.';
    }

    return 'Service cards stay as placeholders until audit, follow-up, and notification summaries are connected.';
});

const activityReadiness = computed(() => {
    if (props.operator_issuance_activity_read_model?.authorized === true) {
        return {
            status: stringValue(props.operator_issuance_activity_read_model.status) ?? 'available',
            label: 'Durable activity read model available',
            description: 'Quick Generate activity can be inspected as an operator-safe summary.',
        };
    }

    return {
        status: stringValue(props.operator_issuance_activity_read_model?.status) ?? 'not_wired',
        label: 'Activity recording not connected',
        description: 'Quick Generate can still issue Pay Codes; activity summaries appear after durable activity storage is enabled.',
    };
});

const operatorFocusItems = computed(() => [
    {
        key: 'generate',
        label: 'Generate a Pay Code',
        status: 'available',
        description: 'Use Quick Generate when you need a new template-first Pay Code.',
        href: '/x/cockpit/quick-generate',
        action: 'Open Quick Generate',
    },
    {
        key: 'inspect',
        label: 'Inspect Pay Codes',
        status: `${metricValue('pay-codes-visible') ?? '0'} visible`,
        description: 'Review sanitized lifecycle state, claim URL readiness, and distribution guidance.',
        href: '/x/cockpit/pay-codes',
        action: 'Open Explorer',
    },
    {
        key: 'attention',
        label: 'Review attention queue',
        status: metricValue('needs-attention') ?? riskSignals.value[0]?.value ?? 'pending',
        description: 'Start with expired or awaiting-approval summaries. This guidance does not mutate lifecycle truth.',
        href: '/x/cockpit/pay-codes?status=expired',
        action: 'Review now',
    },
]);

function integrationSourceLabel(key: string): string {
    if (key === 'journal') {
        return 'Audit trail source';
    }

    if (key === 'actions') {
        return 'Follow-up action source';
    }

    return 'Notification source';
}

function toggleIntegrationDetails(key: string): void {
    expandedIntegrationDetails.value = {
        ...expandedIntegrationDetails.value,
        [key]: expandedIntegrationDetails.value[key] !== true,
    };
}

function areIntegrationDetailsExpanded(key: string): boolean {
    return expandedIntegrationDetails.value[key] === true;
}
</script>

<template>
    <CockpitLayout active-navigation="dashboard" :balances="headerBalances">
        <section class="space-y-6" data-testid="cockpit-dashboard-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Operator Dashboard
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Settlement OS Operating Overview
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This dashboard now prioritizes operator navigation and read-only system posture. It does not
                    execute vouchers, write journal entries, resolve workflow authority, send feedback, call providers,
                    dispatch campaigns, or move money.
                </p>
            </div>

            <section
                class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-6 shadow-sm dark:border-indigo-900/70 dark:bg-indigo-950/30"
                data-testid="cockpit-operating-summary-panel"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-indigo-700 dark:text-indigo-300">
                            Operator Console
                        </p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Start here for generation, inspection, and attention queues
                        </h3>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-700 dark:text-slate-300">
                            The dashboard aggregates existing Cockpit read models into safe links. Journal,
                            action, feedback, provider, campaign, voucher, and wallet mutations remain outside this
                            page.
                        </p>
                    </div>

                    <span class="inline-flex min-h-7 shrink-0 items-center justify-center rounded-full border border-indigo-200 bg-white px-3 py-1 text-center text-xs font-semibold leading-none text-indigo-700 dark:border-indigo-800 dark:bg-slate-950 dark:text-indigo-300">
                        {{ operatingIntegrationStatus }}
                    </span>
                </div>

                <div class="mt-6 grid gap-3 lg:grid-cols-3">
                    <article
                        v-for="card in operatingSummaryCards"
                        :key="card.key"
                        class="rounded-xl border border-white/80 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        data-testid="cockpit-operating-summary-card"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                    {{ card.label }}
                                </p>
                                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                                    {{ card.value }}
                                </p>
                            </div>
                            <a
                                :href="card.href"
                                class="inline-flex min-h-7 shrink-0 items-center justify-center whitespace-nowrap rounded-full bg-indigo-600 px-3 py-1.5 text-center text-xs font-semibold leading-none text-white shadow-sm hover:bg-indigo-500"
                                data-testid="cockpit-operating-summary-link"
                            >
                                {{ card.action }}
                            </a>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-slate-600 dark:text-slate-400">
                            {{ card.description }}
                        </p>
                    </article>
                </div>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-operator-focus-panel"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                            Operator Focus
                        </p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Next safe actions
                        </h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            These are navigation and inspection actions only. They do not execute money movement,
                            dispatch feedback, write journal entries, run action continuations, or mutate campaign state.
                        </p>
                    </div>
                    <span class="inline-flex min-h-7 shrink-0 items-center justify-center whitespace-nowrap rounded-full border border-slate-200 px-3 py-1 text-center text-xs font-semibold leading-none text-slate-600 dark:border-slate-700 dark:text-slate-300">
                        Links only
                    </span>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-3">
                    <article
                        v-for="item in operatorFocusItems"
                        :key="item.key"
                        class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
                        data-testid="cockpit-operator-focus-item"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950 dark:text-slate-50">
                                    {{ item.label }}
                                </p>
                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    {{ item.status }}
                                </p>
                            </div>
                            <a
                                :href="item.href"
                                class="inline-flex min-h-7 shrink-0 items-center justify-center whitespace-nowrap rounded-full border border-slate-200 px-3 py-1.5 text-center text-xs font-semibold leading-none text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                data-testid="cockpit-operator-focus-link"
                            >
                                {{ item.action }}
                            </a>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            {{ item.description }}
                        </p>
                    </article>
                </div>
            </section>

            <CockpitOperatorIssuanceActivityPanel :read-model="props.operator_issuance_activity_read_model" />

            <CockpitRecentActivityPanel :items="activity" />

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-integration-summary-panel"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                            Connected Services
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            Audit, follow-up, and notification status
                        </h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ integrationReadinessNote }}
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                        data-testid="cockpit-activity-readiness-summary"
                    >
                        <p class="font-semibold uppercase tracking-wide">
                            {{ displayStatus(activityReadiness.status) }}
                        </p>
                        <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">
                            {{ activityReadiness.label }}
                        </p>
                        <p class="mt-1 max-w-xs leading-5">
                            {{ activityReadiness.description }}
                        </p>
                    </div>
                </div>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <article
                        v-for="summary in integrationSummaries"
                        :key="summary.key"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                        data-testid="cockpit-integration-summary-card"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950 dark:text-slate-50">
                                {{ summary.label }}
                                </p>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                    {{ integrationSourceLabel(summary.key) }}
                                </p>
                            </div>
                            <span class="inline-flex min-h-6 shrink-0 items-center justify-center whitespace-nowrap rounded-full bg-slate-100 px-2 py-1 text-center text-xs font-semibold leading-none text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ displayStatus(summary.status) }}
                            </span>
                        </div>
                        <p class="mt-4 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                            {{ summary.count }}
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex min-h-7 items-center justify-center rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold leading-none text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            data-testid="cockpit-integration-summary-details-toggle"
                            :aria-expanded="areIntegrationDetailsExpanded(summary.key)"
                            @click="toggleIntegrationDetails(summary.key)"
                        >
                            {{ areIntegrationDetailsExpanded(summary.key) ? 'Hide connection details' : 'Connection details' }}
                        </button>
                        <div
                            v-if="areIntegrationDetailsExpanded(summary.key)"
                            class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-300"
                            data-testid="cockpit-integration-summary-details"
                        >
                            <dl class="mt-3 space-y-2">
                                <div>
                                    <dt class="font-semibold text-slate-700 dark:text-slate-200">
                                        Payload policy
                                    </dt>
                                    <dd>{{ displayStatus(summary.policy) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-slate-700 dark:text-slate-200">
                                        Display readiness
                                    </dt>
                                    <dd>{{ displayStatus(summary.reason) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </article>
                </div>
            </section>

            <CockpitLiquidityHero :metrics="metrics" />

            <div class="grid gap-4 xl:grid-cols-3">
                <CockpitRedemptionPipeline
                    class="xl:col-span-2"
                    :stages="pipeline"
                />
                <CockpitRiskExpiryPanel :signals="riskSignals" />
            </div>

            <CockpitCampaignAdoptionPanel :read-model="props.campaign_read_model" />
        </section>
    </CockpitLayout>
</template>
