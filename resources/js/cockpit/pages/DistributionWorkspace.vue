<script setup lang="ts">
import { computed } from 'vue';
import CockpitDigitalDistributionPanel from '../components/CockpitDigitalDistributionPanel.vue';
import CockpitDistributionAnalyticsPanel from '../components/CockpitDistributionAnalyticsPanel.vue';
import CockpitPrintTemplatePanel from '../components/CockpitPrintTemplatePanel.vue';
import CockpitShareQrPanel from '../components/CockpitShareQrPanel.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitCampaignNavigationContext,
    CockpitDistributionAction,
    CockpitDistributionChannel,
    CockpitDistributionMetric,
    CockpitDistributionWorkspaceItem,
    CockpitDistributionWorkspacePageProps,
    CockpitPrintTemplate,
    CockpitShareAsset,
} from '../types';
import {
    cockpitDistributionActions,
    cockpitDistributionChannels,
    cockpitDistributionMetrics,
    cockpitPrintTemplates,
    cockpitShareAssets,
} from '../distributionWorkspaceDefaults';

const props = defineProps<CockpitDistributionWorkspacePageProps>();

const distributionReadModel = computed(() => props.distribution_workspace_read_model);
const summary = computed<Record<string, unknown>>(() => distributionReadModel.value?.summary ?? {});
const code = computed(() => stringValue(summary.value.code) ?? distributionReadModel.value?.code ?? props.context?.code ?? 'Not wired');
const status = computed(() => stringValue(summary.value.display_status) ?? distributionReadModel.value?.status ?? 'not_wired');
const payloadPolicy = computed(() => stringValue(distributionReadModel.value?.redactions?.payloads) ?? 'not-loaded');
const isHydrated = computed(() => Boolean(distributionReadModel.value?.authorized));
const campaignNavigationContext = computed<CockpitCampaignNavigationContext | null>(() => {
    const context = props.campaign_navigation_context;

    if (!context?.authorized || context.read_only !== true) {
        return null;
    }

    const planningKey = stringValue(context.planning_key);
    const executionId = stringValue(context.execution_id);
    const destination = stringValue(context.destination);

    if (!planningKey || !executionId || destination !== 'distribution_workspace') {
        return null;
    }

    return {
        schema: stringValue(context.schema) ?? 'x-change.cockpit.campaign-navigation.v1',
        status: stringValue(context.status) ?? 'available',
        authorized: true,
        source: stringValue(context.source) ?? 'campaign_cockpit',
        planning_key: planningKey,
        execution_id: executionId,
        campaign_id: stringValue(context.campaign_id),
        audience_id: stringValue(context.audience_id),
        recipient_id: stringValue(context.recipient_id),
        destination,
        read_only: true,
        mutation: {
            enabled: false,
            status: stringValue(objectValue(context.mutation)?.status) ?? 'blocked',
            reason: stringValue(objectValue(context.mutation)?.reason) ?? 'campaign-navigation-read-only',
        },
        redactions: {
            payloads: stringValue(context.redactions?.payloads) ?? 'navigation-context-only',
        },
    };
});

const shareAssets = computed<CockpitShareAsset[]>(() => {
    const assets = distributionReadModel.value?.share_assets;

    if (!Array.isArray(assets) || assets.length === 0) {
        return cockpitShareAssets;
    }

    return assets.map((asset) => ({
        key: asset.key,
        label: asset.label,
        value: asset.status,
        helper: helperWithSource(asset),
    }));
});

const distributionChannels = computed<CockpitDistributionChannel[]>(() => {
    const channels = distributionReadModel.value?.channels;

    if (!Array.isArray(channels) || channels.length === 0) {
        return cockpitDistributionChannels;
    }

    return channels.map((channel) => ({
        key: channel.key,
        label: channel.label,
        status: channel.status,
        helper: helperWithSource(channel),
    }));
});

const printTemplates = computed<CockpitPrintTemplate[]>(() => {
    const templates = distributionReadModel.value?.print_templates;

    if (!Array.isArray(templates) || templates.length === 0) {
        return cockpitPrintTemplates;
    }

    return templates.map((template) => ({
        key: template.key,
        label: template.label,
        format: template.status,
        helper: helperWithSource(template),
    }));
});

const distributionMetrics = computed<CockpitDistributionMetric[]>(() => {
    const metrics = distributionReadModel.value?.analytics;

    if (!Array.isArray(metrics) || metrics.length === 0) {
        return cockpitDistributionMetrics;
    }

    return metrics.map((metric) => ({
        key: metric.key,
        label: metric.label,
        value: metric.status,
        helper: helperWithSource(metric),
    }));
});

const distributionActions = computed<CockpitDistributionAction[]>(() => {
    const actions = distributionReadModel.value?.actions;

    if (!Array.isArray(actions) || actions.length === 0) {
        return cockpitDistributionActions;
    }

    return actions.map((action) => ({
        key: action.key,
        label: action.label,
        disabled: true,
        reason: `${action.status} · ${action.description}`,
    }));
});

function helperWithSource(item: CockpitDistributionWorkspaceItem): string {
    const source = stringValue(item.source);
    const readOnly = item.read_only === false ? 'mutable' : 'read-only';

    return source === null
        ? `${item.description} · ${readOnly}`
        : `${item.description} · ${source} · ${readOnly}`;
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

function objectValue(value: unknown): Record<string, unknown> | null {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return value as Record<string, unknown>;
    }

    return null;
}
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-distribution-workspace-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    {{ isHydrated ? 'Wave 33 · Share surface' : 'Wave 4 · Slice 6' }}
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    {{ isHydrated ? 'Distribution Workspace Runtime' : 'Distribution Workspace Foundation' }}
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen presents read-only distribution and share-surface facts for a Pay Code.
                    It does not dispatch distribution, send feedback, create campaigns, mutate vouchers,
                    execute drivers, write journal entries, call providers, generate artifacts, or move money.
                </p>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Pay Code
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ code }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Distribution status
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ status }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Payload policy
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ payloadPolicy }}
                        </dd>
                    </div>
                </dl>
            </div>

            <section
                v-if="campaignNavigationContext"
                class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/70 dark:bg-indigo-950/40"
                data-testid="cockpit-distribution-campaign-navigation-context"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700 dark:text-indigo-300">
                    Campaign recipient context
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Read-only Distribution context
                </h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This context was carried from a campaign-attributed activity link. It helps operators inspect distribution readiness without dispatching distribution, mutating campaigns, sending feedback, writing journal entries, calling providers, or moving money.
                </p>
                <dl class="mt-5 grid gap-3 text-sm md:grid-cols-3">
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Planning key
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.planning_key }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Execution
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.execution_id }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Recipient
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.recipient_id ?? 'recipient pending' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Destination
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.destination }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Mutation boundary
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.mutation?.reason }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Redaction
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.redactions?.payloads }}
                        </dd>
                    </div>
                </dl>
            </section>

            <CockpitDigitalDistributionPanel
                :channels="distributionChannels"
                :actions="distributionActions"
            />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                <div class="space-y-6">
                    <CockpitPrintTemplatePanel :templates="printTemplates" />
                    <CockpitDistributionAnalyticsPanel :metrics="distributionMetrics" />
                </div>
                <CockpitShareQrPanel :assets="shareAssets" />
            </div>
        </section>
    </CockpitLayout>
</template>
