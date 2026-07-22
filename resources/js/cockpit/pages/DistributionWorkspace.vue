<script setup lang="ts">
import { computed } from 'vue';
import CockpitDigitalDistributionPanel from '../components/CockpitDigitalDistributionPanel.vue';
import CockpitDistributionAnalyticsPanel from '../components/CockpitDistributionAnalyticsPanel.vue';
import CockpitManualCopyButton from '../components/CockpitManualCopyButton.vue';
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
const distributionLinks = computed<Record<string, unknown> | null>(() => {
    const links = objectValue(distributionReadModel.value?.distribution_links);

    if (links === null || links.read_only !== true) {
        return null;
    }

    return links;
});
const beneficiaryRedeemUrl = computed(() => stringValue(distributionLinks.value?.redeem_url));
const beneficiaryRedeemPath = computed(() => stringValue(distributionLinks.value?.redeem_path));
const distributionLinksPolicy = computed(() => {
    const linkRedactions = objectValue(distributionLinks.value?.redactions);

    return stringValue(linkRedactions?.payloads) ?? 'distribution-links-only';
});
const distributionLinksAvailable = computed(() => beneficiaryRedeemUrl.value !== null || beneficiaryRedeemPath.value !== null);
const detailHref = computed(() => `/x/cockpit/pay-codes/${encodeURIComponent(code.value)}`);
const explorerHref = computed(() => '/x/cockpit/pay-codes');
const primaryDistributionStep = computed(() => {
    if (distributionLinksAvailable.value) {
        return {
            label: 'Copy or inspect the beneficiary claim URL',
            description: 'Manual distribution can proceed outside Cockpit after recipient verification.',
        };
    }

    return {
        label: 'Review distribution readiness',
        description: 'No beneficiary URL is available from the current read model.',
    };
});
const readinessSummary = computed(() => [
    {
        key: 'claim-url',
        label: 'Claim URL',
        value: distributionLinksAvailable.value ? 'ready' : 'not available',
        helper: distributionLinksAvailable.value ? 'Manual copy/inspection only.' : 'Waiting for distribution links.',
    },
    {
        key: 'delivery',
        label: 'Delivery',
        value: 'disabled',
        helper: 'Cockpit does not send SMS, email, webhook, or in-app messages here.',
    },
    {
        key: 'artifacts',
        label: 'Artifacts',
        value: 'deferred',
        helper: 'QR, short links, and print generation remain explicitly gated.',
    },
    {
        key: 'payload-policy',
        label: 'Payload Policy',
        value: payloadPolicy.value,
        helper: 'Read-model summary only.',
    },
]);
const connectedContextSummary = computed(() => [
    {
        key: 'claim-url',
        label: 'Claim URL',
        value: distributionLinksAvailable.value ? 'Ready' : 'Not Available',
        helper: distributionLinksAvailable.value ? 'Canonical beneficiary URL is available for manual copy.' : 'No claim URL is available yet.',
    },
    {
        key: 'delivery-evidence',
        label: 'Delivery Evidence',
        value: `${distributionChannels.value.filter((channel) => channel.metadata?.communication_state_only === true).length}`,
        helper: 'Read-only x-feedback delivery summaries shown as communication state.',
    },
    {
        key: 'follow-up-guidance',
        label: 'Follow-Up Guidance',
        value: `${distributionActions.value.filter((action) => action.metadata?.presentation_run === true).length}`,
        helper: 'Disabled x-action guidance rows; Cockpit does not execute them.',
    },
    {
        key: 'audit-evidence',
        label: 'Audit Evidence',
        value: `${distributionMetrics.value.filter((metric) => metric.metadata?.evidence_only === true).length}`,
        helper: 'Read-only x-journal evidence rows shown as audit guidance.',
    },
]);
const manualDistributionChecklist = [
    'Verify the intended recipient outside Cockpit.',
    'Copy the beneficiary claim URL from this page.',
    'Send it only through an approved external workflow.',
    'Do not treat copy as delivery confirmation.',
    'Return to Pay Code Detail for lifecycle and evidence review.',
];
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
const campaignDetailReturnHref = computed(() => {
    if (!campaignNavigationContext.value) {
        return null;
    }

    return `/x/cockpit/pay-codes/${encodeURIComponent(code.value)}?${campaignQueryString()}`;
});
const campaignExplorerReturnHref = computed(() => {
    if (!campaignNavigationContext.value) {
        return null;
    }

    return `/x/cockpit/pay-codes?${campaignQueryString({
        activity_code: code.value,
        activity_source: 'operator_issuance_activity',
    })}`;
});
const campaignDashboardReturnHref = computed(() => {
    if (!campaignNavigationContext.value) {
        return null;
    }

    return `/x/cockpit?${campaignQueryString()}`;
});
const campaignNavigationSourceLabel = computed(() => campaignValueLabel(campaignNavigationContext.value?.source, {
    campaign_cockpit: 'Campaign Cockpit',
    x_campaign_adapter: 'Campaign package adapter',
}));
const campaignNavigationDestinationLabel = computed(() => campaignValueLabel(campaignNavigationContext.value?.destination, {
    pay_code_detail: 'Pay Code Detail',
    distribution_workspace: 'Distribution Workspace',
}));
const campaignNavigationSafetyLabel = computed(() => campaignValueLabel(campaignNavigationContext.value?.mutation?.reason, {
    'campaign-navigation-read-only': 'Campaign navigation only',
}));
const campaignNavigationPayloadLabel = computed(() => campaignValueLabel(campaignNavigationContext.value?.redactions?.payloads, {
    'navigation-context-only': 'Navigation context only',
}));

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
        metadata: channel.metadata,
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
        metadata: metric.metadata,
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
        metadata: action.metadata,
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

function campaignValueLabel(value: unknown, labels: Record<string, string>): string {
    const normalized = stringValue(value);

    if (!normalized) {
        return 'Not provided';
    }

    return labels[normalized] ?? normalized;
}

function objectValue(value: unknown): Record<string, unknown> | null {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return value as Record<string, unknown>;
    }

    return null;
}

function campaignQueryString(extra: Record<string, string | null> = {}): string {
    const context = campaignNavigationContext.value;
    const params = new URLSearchParams();

    if (!context) {
        return '';
    }

    setParam(params, 'campaign_planning_key', context.planning_key);
    setParam(params, 'campaign_execution_id', context.execution_id);
    setParam(params, 'campaign_id', stringValue(context.campaign_id));
    setParam(params, 'campaign_audience_id', stringValue(context.audience_id));
    setParam(params, 'campaign_recipient_id', stringValue(context.recipient_id));
    setParam(params, 'campaign_source', stringValue(context.source));

    for (const [key, value] of Object.entries(extra)) {
        setParam(params, key, value);
    }

    return params.toString();
}

function setParam(params: URLSearchParams, key: string, value: string | null | undefined): void {
    if (value && value.trim() !== '') {
        params.set(key, value.trim());
    }
}
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-distribution-workspace-shell">
            <div
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-distribution-workspace-header"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center"
                    data-testid="cockpit-distribution-workspace-header-row"
                >
                    <div class="min-w-0 lg:w-64 lg:shrink-0 xl:w-72">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                Distribution inspection
                            </p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                read-only
                            </span>
                        </div>
                        <h2 class="mt-1 text-lg font-semibold leading-6 text-slate-950 dark:text-slate-50">
                            Distribution Workspace
                        </h2>
                        <p class="mt-0.5 text-xs leading-4 text-slate-600 dark:text-slate-300">
                            Inspect manual distribution readiness and beneficiary URL availability.
                        </p>
                    </div>
                    <dl
                        class="grid w-full gap-1.5 rounded-lg bg-slate-50 p-1.5 text-xs sm:grid-cols-3 lg:min-w-0 lg:flex-1 dark:bg-slate-950"
                        data-testid="cockpit-distribution-workspace-header-facts"
                    >
                        <div
                            class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                            data-testid="cockpit-distribution-workspace-header-fact"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Pay Code
                            </dt>
                            <dd class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50">
                                {{ code }}
                            </dd>
                        </div>
                        <div
                            class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                            data-testid="cockpit-distribution-workspace-header-fact"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Distribution status
                            </dt>
                            <dd class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50">
                                {{ status }}
                            </dd>
                        </div>
                        <div
                            class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                            data-testid="cockpit-distribution-workspace-header-fact"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Payload policy
                            </dt>
                            <dd class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50">
                                {{ payloadPolicy }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <details
                    class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-800"
                    data-testid="cockpit-distribution-workspace-boundary"
                >
                    <summary class="cursor-pointer text-[0.7rem] font-semibold text-slate-500 dark:text-slate-400">
                        Read-only limits
                    </summary>
                    <p class="mt-2 max-w-4xl text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Inspection only. This page can display and copy the claim URL, but it cannot
                        send messages, change the Pay Code, generate distribution assets, or move money.
                    </p>
                </details>
            </div>

            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-distribution-primary-summary"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Manual distribution summary
                        </p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Pay Code {{ code }}
                        </h3>
                        <p class="mt-1 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                            Copy the beneficiary URL only after recipient verification. Cockpit does not deliver messages; delivery remains external.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-slate-950 dark:text-emerald-200 dark:ring-emerald-800">
                        read-only
                    </span>
                </div>

                <dl
                    class="mt-4 grid gap-2 rounded-xl bg-white/50 p-2 text-sm md:grid-cols-2 xl:grid-cols-4 dark:bg-slate-950/30"
                    data-testid="cockpit-distribution-primary-readiness-strip"
                >
                    <div
                        v-for="item in readinessSummary"
                        :key="item.key"
                        class="rounded-xl bg-white/80 px-3 py-2 dark:bg-slate-950/70"
                        data-testid="cockpit-distribution-primary-readiness-item"
                    >
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ item.label }}
                        </dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            {{ item.value }}
                        </dd>
                        <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            {{ item.helper }}
                        </p>
                    </div>
                </dl>

                <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.95fr)]">
                    <div class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/60 dark:bg-slate-950/70">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                            Manual next step
                        </p>
                        <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ primaryDistributionStep.label }}
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ primaryDistributionStep.description }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <a
                                v-if="beneficiaryRedeemUrl"
                                :href="beneficiaryRedeemUrl"
                                class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-800"
                                data-testid="cockpit-distribution-primary-claim-url-link"
                            >
                                Open claim URL
                            </a>
                            <CockpitManualCopyButton
                                v-if="distributionLinksAvailable"
                                :value="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
                                label="Copy claim URL"
                            />
                            <a
                                :href="detailHref"
                                class="inline-flex items-center rounded-full border border-emerald-300 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-200 dark:hover:bg-emerald-900/50"
                                data-testid="cockpit-distribution-primary-detail-link"
                            >
                                Back to Pay Code Detail
                            </a>
                            <a
                                :href="explorerHref"
                                class="inline-flex items-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                                data-testid="cockpit-distribution-primary-explorer-link"
                            >
                                Back to Pay Codes
                            </a>
                        </div>
                    </div>

                    <details
                        class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/60 dark:bg-slate-950/70"
                        data-testid="cockpit-distribution-manual-checklist"
                    >
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                            <span>Manual distribution checklist</span>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-emerald-950 dark:text-emerald-200">
                                5 steps
                            </span>
                        </summary>
                        <ol class="mt-3 grid gap-2 text-sm">
                            <li
                                v-for="(item, index) in manualDistributionChecklist"
                                :key="item"
                                class="flex gap-2 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-800"
                                data-testid="cockpit-distribution-manual-checklist-item"
                            >
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                                    {{ index + 1 }}
                                </span>
                                <span class="leading-6 text-slate-600 dark:text-slate-300">
                                    {{ item }}
                                </span>
                            </li>
                        </ol>
                    </details>
                </div>

                <details
                    class="mt-4 border-t border-emerald-200 pt-3 dark:border-emerald-900/60"
                    data-testid="cockpit-distribution-connected-context-summary"
                >
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        <span class="flex flex-wrap items-center gap-2">
                            <span>Connected context</span>
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-slate-950/70 dark:text-emerald-200">
                                4 read-only facts
                            </span>
                        </span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-emerald-950 dark:text-emerald-200">
                            inspection only
                        </span>
                    </summary>
                    <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Quick scan of the read-only distribution context currently available for this Pay Code.
                    </p>
                    <dl class="mt-2 grid gap-1.5 text-xs sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="item in connectedContextSummary"
                            :key="item.key"
                            class="rounded-lg bg-white/70 px-2.5 py-2 ring-1 ring-slate-200 dark:bg-slate-950/60 dark:ring-slate-800"
                            data-testid="cockpit-distribution-connected-context-item"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ item.label }}
                            </dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-950 dark:text-slate-50">
                                {{ item.value }}
                            </dd>
                            <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                                {{ item.helper }}
                            </p>
                        </div>
                    </dl>
                </details>

                <details
                    class="mt-3 border-t border-emerald-200 pt-3 dark:border-emerald-900/60"
                    data-testid="cockpit-distribution-readiness-panel-guide"
                >
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        <span>Detailed readiness panels</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-emerald-950 dark:text-emerald-200">
                            details below
                        </span>
                    </summary>
                    <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Notification, print, evidence, and share details are grouped below. Those panels remain read-only and cannot send messages, generate QR assets, create short links, or print artifacts.
                    </p>
                </details>
            </section>

            <section
                v-if="campaignNavigationContext"
                class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/70 dark:bg-indigo-950/40"
                data-testid="cockpit-distribution-campaign-navigation-context"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700 dark:text-indigo-300">
                    Campaign context
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Inspecting distribution from campaign activity
                </h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This Distribution Workspace is being inspected with campaign-recipient context preserved. These links only move between read-only Cockpit views; they do not dispatch delivery, update campaign state, send feedback, write journal entries, call providers, or move money.
                </p>
                <dl class="mt-5 grid gap-3 text-sm md:grid-cols-3">
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Planning
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
                            Campaign
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.campaign_id ?? 'campaign pending' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Audience
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.audience_id ?? 'audience pending' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Source
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationSourceLabel }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Current page
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationDestinationLabel }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Safety
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationSafetyLabel }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Payload visibility
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationPayloadLabel }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a
                        v-if="campaignDetailReturnHref"
                        :href="campaignDetailReturnHref"
                        class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
                        data-testid="cockpit-distribution-campaign-detail-return-link"
                    >
                        Back to Pay Code Detail · read-only
                    </a>
                    <a
                        v-if="campaignExplorerReturnHref"
                        :href="campaignExplorerReturnHref"
                        class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
                        data-testid="cockpit-distribution-campaign-explorer-return-link"
                    >
                        Back to Explorer · read-only
                    </a>
                    <a
                        v-if="campaignDashboardReturnHref"
                        :href="campaignDashboardReturnHref"
                        class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
                        data-testid="cockpit-distribution-campaign-dashboard-return-link"
                    >
                        Back to Campaign Dashboard · read-only
                    </a>
                </div>
            </section>

            <details
                v-if="distributionLinksAvailable"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-distribution-workspace-links-panel"
            >
                <summary class="cursor-pointer list-none">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                                Read-only claim link
                            </p>
                            <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                                Beneficiary Pay Code URL
                            </h3>
                        </div>
                        <span class="rounded-full bg-white/80 px-2 py-0.5 text-[0.65rem] font-semibold text-emerald-700 dark:bg-slate-950/70 dark:text-emerald-200">
                            URL details
                        </span>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Inspect the canonical claim URL, source metadata, and manual distribution guidance.
                    </p>
                </summary>
                <div class="mt-3 border-t border-emerald-200 pt-3 dark:border-emerald-900/60">
                    <p class="text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Copying is browser-local. Recipient verification and delivery remain external.
                    </p>
                </div>
                <dl class="mt-3 grid gap-2 text-xs md:grid-cols-3">
                    <div class="rounded-lg bg-white/80 p-2.5 dark:bg-slate-950/70 md:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Full URL
                        </dt>
                        <dd class="mt-1 break-all font-semibold text-slate-950 dark:text-slate-50">
                            <a
                                v-if="beneficiaryRedeemUrl"
                                :href="beneficiaryRedeemUrl"
                                class="text-emerald-700 underline decoration-emerald-300 underline-offset-4 transition hover:text-emerald-900 dark:text-emerald-200 dark:decoration-emerald-700 dark:hover:text-emerald-100"
                                data-testid="cockpit-distribution-workspace-beneficiary-url-link"
                            >
                                {{ beneficiaryRedeemUrl }}
                            </a>
                            <span v-else>
                                {{ beneficiaryRedeemPath }}
                            </span>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-2.5 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Path
                        </dt>
                        <dd class="mt-1 break-all font-semibold text-slate-950 dark:text-slate-50">
                            {{ beneficiaryRedeemPath ?? 'path unavailable' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-2.5 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Source
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ distributionLinks?.source ?? 'x-change.claim.experience' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-2.5 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Delivery
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            delivery disabled
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-2.5 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Payload policy
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ distributionLinksPolicy }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-3">
                    <CockpitManualCopyButton
                        :value="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
                        label="Copy beneficiary URL"
                    />
                </div>
                <div
                    class="mt-3 rounded-lg border border-emerald-200 bg-white/80 p-3 text-xs leading-5 text-slate-600 dark:border-emerald-900/60 dark:bg-slate-950/70 dark:text-slate-300"
                    data-testid="cockpit-distribution-workspace-manual-distribution-guidance"
                >
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        Manual distribution guidance
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Use this copied link for manual distribution only.</li>
                        <li>Share it only through an approved external workflow after verifying the recipient.</li>
                        <li>Cockpit does not send SMS, email, webhook, in-app notification, or campaign delivery from this panel.</li>
                        <li>Cockpit does not record copy telemetry, create short links, or generate QR assets here.</li>
                        <li>Treat this beneficiary URL as sensitive settlement access material.</li>
                    </ul>
                </div>
            </details>

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
