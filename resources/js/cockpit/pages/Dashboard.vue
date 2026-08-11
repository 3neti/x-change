<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { quickGenerate } from '@/routes/x-change/cockpit';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    BadgePlus,
    Check,
    CircleGauge,
    FileStack,
    Landmark,
    Megaphone,
    Radio,
    ShieldCheck,
    UserPlus,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import CockpitCampaignAdoptionPanel from '../components/CockpitCampaignAdoptionPanel.vue';
import CockpitDiagnosticsDisclosure from '../components/CockpitDiagnosticsDisclosure.vue';
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

type CockpitControl = {
    key: string;
    label: string;
    description: string;
    href: string;
    icon: Component;
};

type CockpitHorizonItem = {
    key: string;
    label: string;
    value: string;
    detail: string;
    tone: 'neutral' | 'healthy' | 'warning';
};

type CockpitLogItem = {
    key: string;
    label: string;
    detail: string;
    meta: string;
    href?: string;
};

type CockpitConnectedServiceCard = {
    key: string;
    label: string;
    source: string;
    status: string;
    count: string;
    boundary: string;
    available: boolean;
};

const readModel = computed(() => props.dashboard_read_model);
const expandedIntegrationDetails = ref<Record<string, boolean>>({});

const headerBalances = computed(() => {
    const balances = props.cockpit_header_read_model?.balances;

    return Array.isArray(balances) && balances.length > 0
        ? balances
        : undefined;
});

const metrics = computed<CockpitDashboardMetric[]>(() => {
    if (
        !readModel.value?.authorized ||
        !Array.isArray(readModel.value.metrics) ||
        readModel.value.metrics.length === 0
    ) {
        return cockpitDashboardMetrics;
    }

    return readModel.value.metrics
        .map((metric) => sanitizeMetric(metric))
        .filter((metric): metric is CockpitDashboardMetric => metric !== null);
});

const pipeline = computed<CockpitPipelineStage[]>(() => {
    if (
        !readModel.value?.authorized ||
        !Array.isArray(readModel.value.pipeline) ||
        readModel.value.pipeline.length === 0
    ) {
        return cockpitRedemptionPipelineStages;
    }

    return readModel.value.pipeline
        .map((stage) => sanitizePipelineStage(stage))
        .filter((stage): stage is CockpitPipelineStage => stage !== null);
});

const riskSignals = computed<CockpitRiskSignal[]>(() => {
    if (
        !readModel.value?.authorized ||
        !Array.isArray(readModel.value.risk_signals) ||
        readModel.value.risk_signals.length === 0
    ) {
        return cockpitRiskSignals;
    }

    return readModel.value.risk_signals
        .map((signal) => sanitizeRiskSignal(signal))
        .filter((signal): signal is CockpitRiskSignal => signal !== null);
});

const activity = computed<CockpitActivityItem[]>(() => {
    if (
        !readModel.value?.authorized ||
        !Array.isArray(readModel.value.activity) ||
        readModel.value.activity.length === 0
    ) {
        return cockpitRecentActivityItems;
    }

    return readModel.value.activity
        .map((item) => sanitizeActivityItem(item))
        .filter((item): item is CockpitActivityItem => item !== null);
});

const controls: CockpitControl[] = [
    {
        key: 'create',
        label: 'Create',
        description: 'Design and issue a Pay Code',
        href: quickGenerate().url,
        icon: BadgePlus,
    },
    {
        key: 'invite',
        label: 'Invite',
        description: 'Issue an Account invitation',
        href: quickGenerate({ query: { intent: 'invite' } }).url,
        icon: UserPlus,
    },
    {
        key: 'funding',
        label: 'Funding',
        description: 'Add and confirm Account funds',
        href: '/x/cockpit/funding',
        icon: Landmark,
    },
    {
        key: 'pay-codes',
        label: 'Pay Codes',
        description: 'Find and inspect issued Pay Codes',
        href: '/x/cockpit/pay-codes',
        icon: FileStack,
    },
    {
        key: 'campaigns',
        label: 'Campaigns',
        description: 'Prepare payments to many recipients',
        href: '/x/cockpit/campaigns',
        icon: Megaphone,
    },
];

const integrationSummaries = computed(() => [
    integrationSummary(
        'journal',
        'Audit Trail',
        props.read_model?.journal,
        'entries',
        'entries',
        'journal-evidence-summary-only',
    ),
    integrationSummary(
        'actions',
        'Follow-Up Actions',
        props.read_model?.actions,
        'actions',
        'actions',
        'safe-action-host-summary-only',
    ),
    integrationSummary(
        'feedback',
        'Notifications',
        props.read_model?.feedback,
        'deliveries',
        'deliveries',
        'communication-delivery-summary-only',
    ),
]);

const campaignSurfaceSummary = computed(() => {
    const surfaces = Array.isArray(props.campaign_read_model?.surfaces)
        ? props.campaign_read_model.surfaces
        : [];
    const availableSurfaces = surfaces.filter((surface) => {
        const status = typeof surface.status === 'string' ? surface.status : '';

        return surface.enabled === true || isStatusAvailable(status);
    });

    if (availableSurfaces.length > 0) {
        return `${availableSurfaces.length} ready`;
    }

    return 'None active';
});

const executionEvidenceCount = computed(
    () => activity.value.filter((item) => item.source === 'execution').length,
);

const connectedServiceCards = computed<CockpitConnectedServiceCard[]>(() => [
    ...integrationSummaries.value.map((summary) => ({
        key: summary.key,
        label: summary.label,
        source: integrationSourceLabel(summary.key),
        status: displayStatus(summary.status),
        count: summary.count,
        boundary: serviceBoundary(summary.key),
        available: isStatusAvailable(summary.status),
    })),
    {
        key: 'campaigns',
        label: 'Campaigns',
        source: 'Campaign package',
        status: displayStatus(
            stringValue(props.campaign_read_model?.status) ?? 'not_wired',
        ),
        count: campaignSurfaceSummary.value,
        boundary: 'Read-only campaign context',
        available:
            props.campaign_read_model?.authorized === true &&
            isStatusAvailable(
                stringValue(props.campaign_read_model.status) ?? '',
            ),
    },
    {
        key: 'balances',
        label: 'Funding Position',
        source: 'Treasury posture',
        status: headerBalances.value ? 'Available' : 'Not connected',
        count: headerBalances.value
            ? `${headerBalances.value.length} positions`
            : '0 positions',
        boundary: 'Read-only Treasury posture',
        available: headerBalances.value !== undefined,
    },
    {
        key: 'execution',
        label: 'Execution Evidence',
        source: 'Execution read model',
        status:
            executionEvidenceCount.value > 0 ? 'Available' : 'Not connected',
        count: `${executionEvidenceCount.value} records`,
        boundary: 'Read-only execution summaries',
        available: executionEvidenceCount.value > 0,
    },
]);

const operatingIntegrationStatus = computed(() => {
    const availableCount = connectedServiceCards.value.filter(
        (summary) => summary.available,
    ).length;

    if (availableCount === connectedServiceCards.value.length) {
        return 'All systems ready';
    }

    if (availableCount > 0) {
        return `${availableCount}/${connectedServiceCards.value.length} systems ready`;
    }

    return 'System details unavailable';
});

const latestIssuanceStatus = computed(() => {
    const presentations = Array.isArray(
        props.operator_issuance_activity_read_model?.presentations,
    )
        ? props.operator_issuance_activity_read_model.presentations
        : [];

    const firstStatus = presentations
        .map((presentation) => stringValue(presentation.status))
        .find((status): status is string => status !== undefined);

    return firstStatus ? displayStatus(firstStatus) : 'No recent issuance';
});

const actionableSignals = computed(() =>
    riskSignals.value.filter((signal) => isActionableSignal(signal.value)),
);

const attentionValue = computed(() => {
    if (actionableSignals.value.length === 0) {
        return 'Clear';
    }

    return (
        metricValue('needs-attention') ??
        actionableSignals.value[0]?.value ??
        `${actionableSignals.value.length}`
    );
});

const horizonItems = computed<CockpitHorizonItem[]>(() => [
    {
        key: 'pay-codes',
        label: 'Pay Codes',
        value: metricValue('pay-codes-visible') ?? '0',
        detail: 'Visible to this Account',
        tone: 'neutral',
    },
    {
        key: 'claims',
        label: 'Claim Progress',
        value: pipelineValue('redeemed') ?? pipelineValue('issued') ?? '—',
        detail: pipelineValue('redeemed')
            ? 'Redeemed'
            : 'Latest lifecycle count',
        tone: 'healthy',
    },
    {
        key: 'campaigns',
        label: 'Campaigns',
        value: campaignSurfaceSummary.value,
        detail:
            props.campaign_read_model?.authorized === true
                ? 'Campaign context available'
                : 'No selected campaign',
        tone: 'neutral',
    },
    {
        key: 'attention',
        label: 'Needs Attention',
        value: attentionValue.value,
        detail:
            actionableSignals.value.length > 0
                ? `${actionableSignals.value.length} signal${actionableSignals.value.length === 1 ? '' : 's'}`
                : 'No current signals',
        tone: actionableSignals.value.length > 0 ? 'warning' : 'healthy',
    },
]);

const logItems = computed<CockpitLogItem[]>(() => {
    const issuance = (
        Array.isArray(
            props.operator_issuance_activity_read_model?.presentations,
        )
            ? props.operator_issuance_activity_read_model.presentations
            : []
    ).map((presentation, index) => ({
        key:
            stringValue(presentation.id) ??
            stringValue(presentation.code) ??
            `issuance-${index}`,
        label: stringValue(presentation.title) ?? 'Pay Code issued',
        detail:
            stringValue(presentation.subtitle) ??
            'Issuance activity is available.',
        meta: displayStatus(stringValue(presentation.status) ?? 'available'),
        href: stringValue(presentation.detail_href),
    }));

    const system = activity.value.map((item) => ({
        key: `activity-${item.id}`,
        label: item.label,
        detail: item.description,
        meta: displayStatus(item.source),
    }));

    return [...issuance, ...system].slice(0, 5);
});

const hasOperationalHistory = computed(() => {
    if (readModel.value?.authorized !== true) {
        return false;
    }

    return (
        numericValue(metricValue('pay-codes-visible')) > 0 ||
        (props.operator_issuance_activity_read_model?.authorized === true &&
            Array.isArray(
                props.operator_issuance_activity_read_model.presentations,
            ) &&
            props.operator_issuance_activity_read_model.presentations.length >
                0)
    );
});

const gettingStartedSteps = computed(() => [
    {
        key: 'fund',
        label: 'Establish funds',
        href: '/x/cockpit/funding',
        complete: hasClientFunds(),
    },
    {
        key: 'create',
        label: 'Create a Pay Code',
        href: '/x/cockpit/quick-generate',
        complete: numericValue(metricValue('pay-codes-visible')) > 0,
    },
    {
        key: 'deliver',
        label: 'Share or deliver',
        href: '/x/cockpit/pay-codes',
        complete:
            numericValue(pipelineValue('shared')) > 0 ||
            numericValue(pipelineValue('redeemed')) > 0,
    },
    {
        key: 'monitor',
        label: 'Monitor results',
        href: '/x/cockpit/pay-codes',
        complete:
            readModel.value?.authorized === true && activity.value.length > 0,
    },
]);

const pinnedCampaign = computed(() => {
    if (props.campaign_read_model?.authorized !== true) {
        return null;
    }

    const facts = props.campaign_read_model.facts;
    const cards =
        facts && typeof facts === 'object' && !Array.isArray(facts)
            ? facts.cards
            : undefined;
    const campaign =
        cards && typeof cards === 'object' && !Array.isArray(cards)
            ? cards.campaign
            : undefined;
    const name =
        campaign && typeof campaign === 'object' && !Array.isArray(campaign)
            ? stringValue(campaign.name)
            : undefined;
    const count =
        campaign && typeof campaign === 'object' && !Array.isArray(campaign)
            ? numericValue(campaign.recipient_count)
            : 0;

    return {
        name: name ?? 'Selected campaign',
        detail:
            count > 0
                ? `${count.toLocaleString()} beneficiaries`
                : 'Campaign context available',
        href: '/x/cockpit/campaigns',
    };
});

const integrationReadinessNote = computed(() => {
    const availableCount = integrationSummaries.value.filter(
        (summary) => summary.status === 'available',
    ).length;

    if (availableCount === integrationSummaries.value.length) {
        return 'Audit, follow-up, and notification summaries are available.';
    }

    if (availableCount > 0) {
        return 'Some supporting service summaries are available.';
    }

    return 'Supporting service summaries are not connected.';
});

const activityReadiness = computed(() => {
    if (props.operator_issuance_activity_read_model?.authorized === true) {
        return {
            status:
                stringValue(
                    props.operator_issuance_activity_read_model.status,
                ) ?? 'available',
            label: 'Activity available',
            description: 'Recent Pay Code issuance can be inspected.',
        };
    }

    return {
        status:
            stringValue(props.operator_issuance_activity_read_model?.status) ??
            'not_wired',
        label: 'Activity unavailable',
        description: 'Activity appears after durable storage is enabled.',
    };
});

function metricValue(key: string): string | undefined {
    return metrics.value.find((metric) => metric.key === key)?.value;
}

function pipelineValue(key: string): string | undefined {
    return pipeline.value.find((stage) => stage.key === key)?.value;
}

function hasClientFunds(): boolean {
    const clientFunds = headerBalances.value?.find(
        (balance) =>
            balance.key === 'internal' ||
            balance.label.toLowerCase() === 'client funds',
    );

    if (!clientFunds) {
        return false;
    }

    if (
        typeof clientFunds.amount_minor === 'number' &&
        clientFunds.amount_minor > 0
    ) {
        return true;
    }

    return numericValue(clientFunds.value) > 0;
}

function numericValue(value: unknown): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value !== 'string') {
        return 0;
    }

    const normalized = value.replace(/[^\d.-]/g, '');
    const parsed = Number.parseFloat(normalized);

    return Number.isFinite(parsed) ? parsed : 0;
}

function isActionableSignal(value: string): boolean {
    const normalized = value.trim().toLowerCase();

    if (
        normalized === '' ||
        normalized === '—' ||
        normalized.includes('not connected') ||
        normalized.includes('not available') ||
        normalized.includes('deferred')
    ) {
        return false;
    }

    return numericValue(normalized) > 0;
}

function sanitizeMetric(
    metric: CockpitDashboardMetric,
): CockpitDashboardMetric | null {
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

function sanitizePipelineStage(
    stage: CockpitPipelineStage,
): CockpitPipelineStage | null {
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

function sanitizeRiskSignal(
    signal: CockpitRiskSignal,
): CockpitRiskSignal | null {
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

function sanitizeActivityItem(
    item: CockpitActivityItem,
): CockpitActivityItem | null {
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
        metadata:
            typeof item.metadata === 'object' && item.metadata !== null
                ? item.metadata
                : undefined,
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
    return value === 'healthy' || value === 'warning' || value === 'critical'
        ? value
        : 'neutral';
}

function severityValue(
    value: unknown,
): CockpitRiskSignal['severity'] | undefined {
    return value === 'watch' || value === 'warning' || value === 'critical'
        ? value
        : undefined;
}

function sourceValue(
    value: unknown,
): CockpitActivityItem['source'] | undefined {
    return value === 'execution' ||
        value === 'journal' ||
        value === 'action' ||
        value === 'feedback' ||
        value === 'system'
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
    const collection = Array.isArray(model?.[collectionKey])
        ? model[collectionKey]
        : [];

    return {
        key,
        label,
        status: stringValue(model?.status) ?? 'not_wired',
        count: `${collection.length} ${noun}`,
        policy:
            stringValue(
                (model?.redactions as CockpitReadModelRedactions | undefined)
                    ?.payloads,
            ) ?? fallbackPolicy,
        reason:
            stringValue(
                (model?.redactions as CockpitReadModelRedactions | undefined)
                    ?.reason,
            ) ?? 'read-model-ready',
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
        return 'Ready';
    }

    if (normalized === 'presentation_only') {
        return 'Read-only';
    }

    return normalized
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function isStatusAvailable(value: string): boolean {
    return value.trim() === 'available';
}

function integrationSourceLabel(key: string): string {
    if (key === 'journal') {
        return 'x-journal';
    }

    if (key === 'actions') {
        return 'x-action';
    }

    return 'x-feedback';
}

function serviceBoundary(key: string): string {
    if (key === 'journal') {
        return 'Audit summaries';
    }

    if (key === 'actions') {
        return 'Follow-up summaries';
    }

    return 'Delivery summaries';
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
    <CockpitLayout
        active-navigation="dashboard"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <section class="space-y-4" data-testid="cockpit-dashboard-shell">
            <header
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-page-heading"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                    >
                        Settlement Operations
                    </p>
                    <h1
                        class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white"
                    >
                        Cockpit
                    </h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="actionableSignals.length > 0"
                        href="/x/cockpit/pay-codes?status=expired"
                        prefetch
                        class="inline-flex h-9 items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 text-sm font-semibold text-amber-800 transition hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                        data-testid="cockpit-attention-summary-link"
                    >
                        <AlertTriangle class="size-4" aria-hidden="true" />
                        {{ attentionValue }} need review
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                        data-testid="cockpit-attention-clear"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        No current alerts
                    </span>
                    <span
                        class="inline-flex h-9 items-center gap-2 rounded-full border border-slate-200 px-3 text-sm font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300"
                    >
                        <Radio class="size-4" aria-hidden="true" />
                        {{ operatingIntegrationStatus }}
                    </span>
                </div>
            </header>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-controls-panel"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2
                            class="text-base font-semibold text-slate-950 dark:text-white"
                        >
                            Controls
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Choose a workspace.
                        </p>
                    </div>
                    <CircleGauge
                        class="size-5 text-slate-400"
                        aria-hidden="true"
                    />
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <Link
                        v-for="control in controls"
                        :key="control.key"
                        :href="control.href"
                        prefetch
                        class="group flex min-h-20 items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 dark:border-slate-700 dark:hover:border-slate-600 dark:hover:bg-slate-800/70 dark:focus-visible:outline-white"
                        data-testid="cockpit-control-link"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-slate-900 group-hover:text-white dark:bg-slate-800 dark:text-slate-200 dark:group-hover:bg-white dark:group-hover:text-slate-950"
                        >
                            <component
                                :is="control.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span
                                class="block font-semibold text-slate-950 dark:text-white"
                            >
                                {{ control.label }}
                            </span>
                            <span
                                class="mt-0.5 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{ control.description }}
                            </span>
                        </span>
                        <ArrowRight
                            class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-slate-700 dark:group-hover:text-slate-200"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-operational-horizon"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2
                            class="text-base font-semibold text-slate-950 dark:text-white"
                        >
                            Operational Horizon
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Current work at a glance.
                        </p>
                    </div>
                    <Activity
                        class="size-5 text-slate-400"
                        aria-hidden="true"
                    />
                </div>

                <div
                    class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 lg:grid-cols-4 dark:border-slate-700 dark:bg-slate-700"
                >
                    <article
                        v-for="item in horizonItems"
                        :key="item.key"
                        class="bg-white px-4 py-3 dark:bg-slate-900"
                        data-testid="cockpit-horizon-item"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="size-2 rounded-full"
                                :class="{
                                    'bg-slate-400': item.tone === 'neutral',
                                    'bg-emerald-500': item.tone === 'healthy',
                                    'bg-amber-500': item.tone === 'warning',
                                }"
                            />
                            <p
                                class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                {{ item.label }}
                            </p>
                        </div>
                        <p
                            class="mt-2 truncate text-xl font-semibold text-slate-950 tabular-nums dark:text-white"
                        >
                            {{ item.value }}
                        </p>
                        <p
                            class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ item.detail }}
                        </p>
                    </article>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-5">
                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-attention-panel"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Attention
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Items that may need review.
                            </p>
                        </div>
                        <AlertTriangle
                            class="size-5"
                            :class="
                                actionableSignals.length > 0
                                    ? 'text-amber-500'
                                    : 'text-emerald-500'
                            "
                            aria-hidden="true"
                        />
                    </div>

                    <div
                        v-if="actionableSignals.length > 0"
                        class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <Link
                            v-for="signal in actionableSignals"
                            :key="signal.key"
                            href="/x/cockpit/pay-codes"
                            prefetch
                            class="group flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                            data-testid="cockpit-attention-item"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ signal.label }}
                                </span>
                                <span
                                    class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ signal.value }}
                                </span>
                            </span>
                            <ArrowRight
                                class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </Link>
                    </div>

                    <div
                        v-else
                        class="mt-4 flex min-h-28 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 px-4 text-center dark:border-slate-700"
                        data-testid="cockpit-attention-empty"
                    >
                        <ShieldCheck
                            class="size-7 text-emerald-500"
                            aria-hidden="true"
                        />
                        <p
                            class="mt-2 text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            Nothing needs review
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            New exceptions will appear here.
                        </p>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-3 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-recent-log"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Recent Activity
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Issuance and settlement signals.
                            </p>
                        </div>
                        <Radio
                            class="size-5 text-slate-400"
                            aria-hidden="true"
                        />
                    </div>

                    <div
                        class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <template v-for="item in logItems" :key="item.key">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                prefetch
                                class="group flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                data-testid="cockpit-log-item"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full bg-slate-400"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ item.label }}
                                    </span>
                                    <span
                                        class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ item.detail }}
                                    </span>
                                </span>
                                <span
                                    class="shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.meta }}
                                </span>
                            </Link>
                            <div
                                v-else
                                class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                data-testid="cockpit-log-item"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full bg-slate-400"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ item.label }}
                                    </span>
                                    <span
                                        class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ item.detail }}
                                    </span>
                                </span>
                                <span
                                    class="shrink-0 text-xs font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.meta }}
                                </span>
                            </div>
                        </template>
                    </div>
                </section>
            </div>

            <section
                v-if="!hasOperationalHistory"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-getting-started"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-slate-950 dark:text-white"
                        >
                            Getting Started
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Establish funds, issue, deliver, then monitor.
                        </p>
                    </div>
                    <ol
                        class="grid flex-1 gap-2 sm:grid-cols-2 lg:max-w-3xl lg:grid-cols-4"
                    >
                        <li
                            v-for="(step, index) in gettingStartedSteps"
                            :key="step.key"
                        >
                            <Link
                                :href="step.href"
                                prefetch
                                class="flex min-h-14 items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 dark:border-slate-700 dark:hover:bg-slate-800 dark:focus-visible:outline-white"
                                data-testid="cockpit-getting-started-step"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                    :class="
                                        step.complete
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                >
                                    <Check
                                        v-if="step.complete"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span v-else>{{ index + 1 }}</span>
                                </span>
                                <span
                                    class="text-sm font-semibold text-slate-800 dark:text-slate-100"
                                >
                                    {{ step.label }}
                                </span>
                            </Link>
                        </li>
                    </ol>
                </div>
            </section>

            <section
                v-if="pinnedCampaign"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pinned-work"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <Megaphone class="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                        >
                            Pinned Campaign
                        </p>
                        <p
                            class="mt-0.5 font-semibold text-slate-950 dark:text-white"
                        >
                            {{ pinnedCampaign.name }}
                        </p>
                        <p
                            class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ pinnedCampaign.detail }}
                        </p>
                    </div>
                </div>
                <Link
                    :href="pinnedCampaign.href"
                    prefetch
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:outline-white"
                >
                    Open Campaigns
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </section>

            <CockpitDiagnosticsDisclosure
                title="System Status"
                :summary="operatingIntegrationStatus"
                eyebrow="Accounts, services, and diagnostics"
                action-label="Show system status"
            >
                <section
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-connected-services-overview"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Connected Services
                            </h3>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ integrationReadinessNote }}
                            </p>
                        </div>
                        <span
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >
                            {{ operatingIntegrationStatus }}
                        </span>
                    </div>

                    <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="service in connectedServiceCards"
                            :key="service.key"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            data-testid="cockpit-connected-service-card"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p
                                        class="text-sm font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ service.label }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ service.source }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ service.status }}
                                </span>
                            </div>
                            <p
                                class="mt-3 text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                {{ service.count }}
                            </p>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ service.boundary }}
                            </p>
                        </article>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-integration-summary-panel"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <h3
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Service Connection Details
                            </h3>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ integrationReadinessNote }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-300"
                            data-testid="cockpit-activity-readiness-summary"
                        >
                            <p
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ activityReadiness.label }}
                            </p>
                            <p class="mt-1">
                                {{ activityReadiness.description }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 md:grid-cols-3">
                        <article
                            v-for="summary in integrationSummaries"
                            :key="summary.key"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            data-testid="cockpit-integration-summary-card"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p
                                    class="text-sm font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ summary.label }}
                                </p>
                                <span
                                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                                >
                                    {{ displayStatus(summary.status) }}
                                </span>
                            </div>
                            <p
                                class="mt-2 text-lg font-semibold text-slate-950 dark:text-white"
                            >
                                {{ summary.count }}
                            </p>
                            <button
                                type="button"
                                class="mt-2 text-xs font-semibold text-slate-600 underline-offset-4 hover:underline dark:text-slate-300"
                                data-testid="cockpit-integration-summary-details-toggle"
                                :aria-expanded="
                                    areIntegrationDetailsExpanded(summary.key)
                                "
                                @click="toggleIntegrationDetails(summary.key)"
                            >
                                {{
                                    areIntegrationDetailsExpanded(summary.key)
                                        ? 'Hide details'
                                        : 'Connection details'
                                }}
                            </button>
                            <dl
                                v-if="
                                    areIntegrationDetailsExpanded(summary.key)
                                "
                                class="mt-2 space-y-1 rounded-lg bg-slate-50 p-2 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-300"
                                data-testid="cockpit-integration-summary-details"
                            >
                                <div>
                                    <dt class="font-semibold">
                                        Payload policy
                                    </dt>
                                    <dd>{{ displayStatus(summary.policy) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold">
                                        Display readiness
                                    </dt>
                                    <dd>{{ displayStatus(summary.reason) }}</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </section>

                <CockpitOperatorIssuanceActivityPanel
                    :read-model="props.operator_issuance_activity_read_model"
                />

                <CockpitRecentActivityPanel :items="activity" />

                <CockpitLiquidityHero
                    :metrics="metrics"
                    :vocabulary="props.cockpit_header_read_model?.vocabulary"
                />

                <div class="grid gap-4 xl:grid-cols-3">
                    <CockpitRedemptionPipeline
                        class="xl:col-span-2"
                        :stages="pipeline"
                    />
                    <CockpitRiskExpiryPanel :signals="riskSignals" />
                </div>

                <CockpitCampaignAdoptionPanel
                    :read-model="props.campaign_read_model"
                />
            </CockpitDiagnosticsDisclosure>
        </section>
    </CockpitLayout>
</template>
