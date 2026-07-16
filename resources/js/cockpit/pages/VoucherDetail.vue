<script setup lang="ts">
import { computed } from 'vue';
import CockpitManualCopyButton from '../components/CockpitManualCopyButton.vue';
import CockpitVoucherAuditPanel from '../components/CockpitVoucherAuditPanel.vue';
import CockpitVoucherDistributionPanel from '../components/CockpitVoucherDistributionPanel.vue';
import CockpitVoucherEvidencePanel from '../components/CockpitVoucherEvidencePanel.vue';
import CockpitVoucherOverviewPanel from '../components/CockpitVoucherOverviewPanel.vue';
import CockpitVoucherTimelinePanel from '../components/CockpitVoucherTimelinePanel.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitCampaignNavigationContext,
    CockpitDependentReadModel,
    CockpitReadModelRedactions,
    CockpitVoucherAuditItem,
    CockpitVoucherDetailAction,
    CockpitVoucherDetailPageProps,
    CockpitVoucherDistributionItem,
    CockpitVoucherEvidenceItem,
    CockpitVoucherEvidenceSummary,
    CockpitVoucherOverviewItem,
    CockpitVoucherTimelineItem,
} from '../types';
import {
    cockpitVoucherDetailActions,
    cockpitVoucherOverviewItems,
} from '../voucherDetailDefaults';

const props = defineProps<CockpitVoucherDetailPageProps>();

const voucher = computed(() => props.read_model?.voucher);
const summary = computed<Record<string, unknown>>(() => voucher.value?.summary ?? {});
const code = computed(() => stringValue(summary.value.code) ?? voucher.value?.code ?? props.read_model?.code ?? props.context?.code ?? 'Not wired');
const status = computed(() => stringValue(summary.value.display_status) ?? stringValue(summary.value.status) ?? voucher.value?.status ?? 'not_wired');
const redactions = computed<CockpitReadModelRedactions>(() => voucher.value?.redactions ?? { payloads: 'not-loaded' });
const distributionLinks = computed<Record<string, unknown> | null>(() => {
    const links = objectValue(voucher.value?.distribution_links);

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
const amountDisplay = computed(() => moneyValue(summary.value.amount, stringValue(summary.value.currency)));
const claimStateDisplay = computed(() => claimState(summary.value.claimed, summary.value.fully_claimed));
const availabilityDisplay = computed(() => availabilityWindow(summary.value.starts_at, summary.value.expires_at));
const distributionWorkspaceHref = computed(() => `/x/cockpit/pay-codes/${encodeURIComponent(code.value)}/distribution`);
const explorerHref = computed(() => '/x/cockpit/pay-codes');
const primaryNextStep = computed(() => {
    if (distributionLinksAvailable.value) {
        return {
            label: 'Copy or inspect the beneficiary claim URL',
            description: 'Manual distribution is available from this read-only detail screen.',
        };
    }

    return {
        label: 'Review sanitized lifecycle evidence',
        description: 'Distribution links are not available from the current read model.',
    };
});

const overviewItems = computed<CockpitVoucherOverviewItem[]>(() => {
    if (!props.read_model?.voucher?.authorized) {
        return cockpitVoucherOverviewItems;
    }

    return [
        {
            key: 'pay-code',
            label: 'Pay Code',
            value: code.value,
            helper: 'Hydrated from the sanitized voucher summary read model.',
        },
        {
            key: 'status',
            label: 'Status',
            value: status.value,
            helper: 'Display status is read-only lifecycle presentation state.',
        },
        {
            key: 'amount',
            label: 'Amount',
            value: moneyValue(summary.value.amount, stringValue(summary.value.currency)),
            helper: 'Amount is displayed only from the voucher summary whitelist.',
        },
        {
            key: 'claim-state',
            label: 'Claim state',
            value: claimState(summary.value.claimed, summary.value.fully_claimed),
            helper: 'Claim booleans are summary facts; claim payloads remain redacted.',
        },
        {
            key: 'availability-window',
            label: 'Availability window',
            value: availabilityWindow(summary.value.starts_at, summary.value.expires_at),
            helper: 'Date fields come from the sanitized voucher summary.',
        },
        {
            key: 'redaction',
            label: 'Redaction',
            value: stringValue(redactions.value.payloads) ?? 'not-loaded',
            helper: 'Sensitive lifecycle detail fields remain excluded from the UI.',
        },
    ];
});

const timelineItems = computed<CockpitVoucherTimelineItem[]>(() => [
    {
        id: 'created',
        label: 'Created',
        description: props.read_model?.voucher?.authorized
            ? 'Voucher creation timestamp from sanitized summary.'
            : 'Voucher creation timestamp pending an authorized read model.',
        timestamp: stringValue(summary.value.created_at) ?? 'Read model pending',
        source: 'system',
    },
    {
        id: 'availability',
        label: 'Availability',
        description: 'Voucher starts/expires timestamps are rendered without claim or instruction payloads.',
        timestamp: availabilityWindow(summary.value.starts_at, summary.value.expires_at),
        source: 'system',
    },
    {
        id: 'execution',
        label: 'Execution read model',
        description: 'No execution driver is invoked by this screen.',
        timestamp: readModelStatus(props.read_model?.execution),
        source: 'execution',
    },
    {
        id: 'feedback',
        label: 'Feedback delivery',
        description: 'Feedback deliveries remain unavailable until an authorized feedback read model is wired.',
        timestamp: readModelStatus(props.read_model?.feedback),
        source: 'feedback',
    },
]);

const evidenceItems = computed<CockpitVoucherEvidenceItem[]>(() => [
    ...hydratedEvidenceItems(props.read_model?.voucher?.evidence_summary),
]);

const evidenceHeading = computed(() => (
    Array.isArray(props.read_model?.voucher?.evidence_summary)
    && props.read_model.voucher.evidence_summary.length > 0
        ? 'Evidence summary'
        : 'Evidence tab placeholder'
));

const distributionItems = computed<CockpitVoucherDistributionItem[]>(() => [
    ...feedbackDistributionItems(props.read_model?.feedback),
]);

const auditItems = computed<CockpitVoucherAuditItem[]>(() => [
    {
        id: 'execution',
        label: 'Execution read model',
        status: readModelStatus(props.read_model?.execution),
        helper: 'Execution results remain not wired; Cockpit does not invoke drivers.',
    },
    ...journalAuditItems(props.read_model?.journal),
    {
        id: 'actions',
        label: 'Action handoff',
        status: readModelStatus(props.read_model?.actions),
        helper: 'x-action can describe next steps later, but this page does not execute actions.',
    },
    {
        id: 'feedback',
        label: 'Feedback delivery',
        status: readModelStatus(props.read_model?.feedback),
        helper: 'Feedback delivery remains unavailable until an authorized feedback read model is wired.',
    },
]);

const detailActions = computed<CockpitVoucherDetailAction[]>(() => {
    const actions = Array.isArray(props.read_model?.actions?.actions)
        ? props.read_model.actions.actions
        : [];

    if (readModelStatus(props.read_model?.actions) !== 'available' || actions.length === 0) {
        return cockpitVoucherDetailActions;
    }

    return actions
        .map((action, index) => actionDetailItem(action, index, props.read_model?.actions?.redactions))
        .filter((action): action is CockpitVoucherDetailAction => action !== null)
        .slice(0, 5);
});

const integrationSummaries = computed(() => [
    integrationSummary('journal', 'Journal Evidence', props.read_model?.journal, 'entries', 'entries'),
    integrationSummary('actions', 'Action CTAs', props.read_model?.actions, 'actions', 'actions'),
    integrationSummary('feedback', 'Feedback Deliveries', props.read_model?.feedback, 'deliveries', 'deliveries'),
]);
const primaryEvidenceReadiness = computed(() => integrationSummaries.value.map((summary) => ({
    ...summary,
    label: summary.label.replace(' Evidence', '').replace(' Deliveries', '').replace(' CTAs', ''),
})));
const campaignNavigationContext = computed<CockpitCampaignNavigationContext | null>(() => {
    const context = props.campaign_navigation_context;

    if (!context?.authorized || context.read_only !== true) {
        return null;
    }

    const planningKey = stringValue(context.planning_key);
    const executionId = stringValue(context.execution_id);
    const destination = stringValue(context.destination);

    if (!planningKey || !executionId || destination !== 'pay_code_detail') {
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

function stringValue(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function moneyValue(value: unknown, currency: string | null = 'PHP'): string {
    const amount = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(amount)) {
        return 'Pending summary';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency ?? 'PHP',
    }).format(amount);
}

function claimState(claimed: unknown, fullyClaimed: unknown): string {
    if (fullyClaimed === true) {
        return 'Fully claimed';
    }

    if (claimed === true) {
        return 'Partially claimed';
    }

    if (claimed === false || fullyClaimed === false) {
        return 'Not claimed';
    }

    return 'Pending summary';
}

function availabilityWindow(startsAt: unknown, expiresAt: unknown): string {
    const starts = stringValue(startsAt) ?? 'start pending';
    const expires = stringValue(expiresAt) ?? 'expiry pending';

    return `${starts} → ${expires}`;
}

function readModelStatus(model?: CockpitDependentReadModel): string {
    return stringValue(model?.status) ?? 'not_wired';
}

function hydratedEvidenceItems(
    evidenceSummary?: CockpitVoucherEvidenceSummary[],
): CockpitVoucherEvidenceItem[] {
    if (!Array.isArray(evidenceSummary) || evidenceSummary.length === 0) {
        return [
            {
                id: 'claim-evidence',
                label: 'Claim evidence',
                status: 'not_wired',
                helper: 'Claim evidence and uploaded artifacts are not exposed by the voucher summary read model.',
            },
            {
                id: 'approval-evidence',
                label: 'Approval evidence',
                status: 'not_wired',
                helper: 'Approval references and OTP metadata remain redacted from Cockpit voucher hydration.',
            },
            {
                id: 'settlement-envelope',
                label: 'Settlement envelope evidence',
                status: 'not_wired',
                helper: 'Settlement Envelope readiness requires a future authorized read model.',
            },
        ];
    }

    return evidenceSummary.map((item) => ({
        id: item.key,
        label: item.label,
        status: item.status,
        helper: item.description,
        source: item.source,
        read_only: item.read_only ?? true,
        available: item.available ?? false,
    }));
}

function journalAuditItems(model?: CockpitDependentReadModel): CockpitVoucherAuditItem[] {
    const entries = Array.isArray(model?.entries) ? model.entries : [];

    if (readModelStatus(model) !== 'available' || entries.length === 0) {
        return [
            {
                id: 'journal',
                label: 'Journal read model',
                status: readModelStatus(model),
                helper: 'Journal entries remain unavailable until an authorized journal read model is wired.',
            },
        ];
    }

    return entries
        .map((entry, index) => journalAuditItem(entry, index, model?.redactions))
        .filter((item): item is CockpitVoucherAuditItem => item !== null)
        .slice(0, 5);
}

function journalAuditItem(
    entry: unknown,
    index: number,
    journalRedactions?: CockpitReadModelRedactions,
): CockpitVoucherAuditItem | null {
    const journalEntry = objectValue(entry);

    if (journalEntry === null) {
        return null;
    }

    const event = stringValue(journalEntry.event_type)
        ?? stringValue(journalEntry.type)
        ?? stringValue(journalEntry.event)
        ?? 'journal.entry';
    const summary = stringValue(journalEntry.summary)
        ?? stringValue(journalEntry.description)
        ?? 'Journal evidence summary available.';
    const occurredAt = stringValue(journalEntry.occurred_at)
        ?? stringValue(journalEntry.timestamp)
        ?? stringValue(journalEntry.created_at)
        ?? 'timestamp redacted';
    const payloadPolicy = stringValue(journalRedactions?.payloads) ?? 'journal-evidence-summary-only';

    return {
        id: stringValue(journalEntry.id) ?? `journal-${index + 1}`,
        label: `Journal: ${event}`,
        status: 'available',
        helper: `${summary} · ${occurredAt} · ${payloadPolicy}`,
    };
}

function objectValue(value: unknown): Record<string, unknown> | null {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
        return value as Record<string, unknown>;
    }

    return null;
}

function actionDetailItem(
    action: unknown,
    index: number,
    actionRedactions?: CockpitReadModelRedactions,
): CockpitVoucherDetailAction | null {
    const actionItem = objectValue(action);

    if (actionItem === null) {
        return null;
    }

    const key = stringValue(actionItem.key)
        ?? stringValue(actionItem.id)
        ?? `action-${index + 1}`;
    const label = stringValue(actionItem.label)
        ?? stringValue(actionItem.name)
        ?? key;
    const status = stringValue(actionItem.status) ?? 'available';
    const payloadPolicy = stringValue(actionRedactions?.payloads) ?? 'safe-action-host-summary-only';

    return {
        key,
        label,
        disabled: true,
        reason: `${status} · ${payloadPolicy} · Action execution remains disabled from Cockpit.`,
    };
}

function feedbackDistributionItems(model?: CockpitDependentReadModel): CockpitVoucherDistributionItem[] {
    const deliveries = Array.isArray(model?.deliveries) ? model.deliveries : [];

    if (readModelStatus(model) !== 'available' || deliveries.length === 0) {
        return ['sms', 'email', 'in-app'].map((channel) => ({
            id: channel,
            channel: channelLabel(channel),
            status: readModelStatus(model),
            helper: `${channelLabel(channel)} delivery status remains owned by x-feedback read models.`,
        }));
    }

    return deliveries
        .map((delivery, index) => feedbackDistributionItem(delivery, index, model?.redactions))
        .filter((item): item is CockpitVoucherDistributionItem => item !== null)
        .slice(0, 5);
}

function feedbackDistributionItem(
    delivery: unknown,
    index: number,
    feedbackRedactions?: CockpitReadModelRedactions,
): CockpitVoucherDistributionItem | null {
    const deliveryItem = objectValue(delivery);

    if (deliveryItem === null) {
        return null;
    }

    const channel = stringValue(deliveryItem.channel)
        ?? stringValue(deliveryItem.type)
        ?? 'feedback';
    const status = stringValue(deliveryItem.status) ?? 'available';
    const payloadPolicy = stringValue(feedbackRedactions?.payloads) ?? 'communication-delivery-summary-only';

    return {
        id: stringValue(deliveryItem.id) ?? `feedback-${index + 1}`,
        channel: channelLabel(channel),
        status,
        helper: `${payloadPolicy} · Feedback delivery remains read-only from Cockpit.`,
    };
}

function channelLabel(channel: string): string {
    if (channel.toLowerCase() === 'sms') {
        return 'SMS';
    }

    if (channel.toLowerCase() === 'in-app') {
        return 'In-app';
    }

    return channel.charAt(0).toUpperCase() + channel.slice(1);
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

function integrationSummary(
    key: string,
    label: string,
    model: CockpitDependentReadModel | undefined,
    collectionKey: 'entries' | 'actions' | 'deliveries',
    noun: string,
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
        status: readModelStatus(model),
        count: `${collection.length} ${noun}`,
        policy: stringValue(model?.redactions?.payloads) ?? 'not-loaded',
        reason: stringValue(model?.redactions?.reason) ?? 'read-model-ready',
    };
}
</script>

<template>
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-voucher-detail-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Wave 4 · Slice 12
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Voucher Detail Read Model
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This screen hydrates the Voucher Detail Foundation from sanitized voucher summary
                    facts only. It does not mutate vouchers, execute drivers, write journal entries, send
                    feedback, call providers, or move money.
                </p>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Code
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ code }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Status
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
                            {{ redactions.payloads ?? 'not-loaded' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-voucher-detail-primary-summary"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Operator detail summary
                        </p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Pay Code {{ code }}
                        </h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            This summary is built from sanitized voucher read-model facts. It gives operators the current
                            lifecycle state, beneficiary URL readiness, and safe next steps without mutating the Pay Code
                            or triggering delivery.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-slate-950 dark:text-emerald-200 dark:ring-emerald-800">
                        read-only
                    </span>
                </div>

                <dl class="mt-5 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl bg-white/80 p-4 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Lifecycle
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ status }}
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Display state only; no execution is invoked.
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/80 p-4 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Amount
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ amountDisplay }}
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Sanitized summary amount only.
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/80 p-4 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Claim State
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ claimStateDisplay }}
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Claim payloads remain redacted.
                        </p>
                    </div>
                    <div class="rounded-xl bg-white/80 p-4 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Claim URL
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ distributionLinksAvailable ? 'ready' : 'not available' }}
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            {{ distributionLinksAvailable ? 'Manual copy/inspection only.' : 'Waiting for distribution link read model.' }}
                        </p>
                    </div>
                </dl>

                <div class="mt-5 rounded-xl border border-emerald-200 bg-white/80 p-4 dark:border-emerald-900/60 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        Primary next step
                    </p>
                    <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ primaryNextStep.label }}
                    </p>
                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {{ primaryNextStep.description }}
                    </p>
                    <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Availability: {{ availabilityDisplay }} · Payload policy: {{ redactions.payloads ?? 'not-loaded' }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a
                            v-if="beneficiaryRedeemUrl"
                            :href="beneficiaryRedeemUrl"
                            class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-800"
                            data-testid="cockpit-voucher-detail-primary-claim-url-link"
                        >
                            Open claim URL
                        </a>
                        <CockpitManualCopyButton
                            v-if="distributionLinksAvailable"
                            :value="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
                            label="Copy claim URL"
                        />
                        <a
                            :href="distributionWorkspaceHref"
                            class="inline-flex items-center rounded-full border border-emerald-300 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-200 dark:hover:bg-emerald-900/50"
                            data-testid="cockpit-voucher-detail-primary-distribution-link"
                        >
                            Open distribution workspace
                        </a>
                        <a
                            :href="explorerHref"
                            class="inline-flex items-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            data-testid="cockpit-voucher-detail-primary-explorer-link"
                        >
                            Back to Pay Codes
                        </a>
                    </div>
                </div>

                <div
                    class="mt-5 rounded-xl border border-emerald-200 bg-white/80 p-4 dark:border-emerald-900/60 dark:bg-slate-950/70"
                    data-testid="cockpit-voucher-detail-primary-evidence-readiness"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                                Evidence readiness
                            </p>
                            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                Read-only integration state. These facts do not execute actions, send feedback, or write journal entries.
                            </p>
                        </div>
                        <span class="w-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                            summary only
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                        <div
                            v-for="item in primaryEvidenceReadiness"
                            :key="item.key"
                            class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                            data-testid="cockpit-voucher-detail-primary-evidence-readiness-item"
                        >
                            <dt class="flex items-center justify-between gap-3">
                                <span class="font-semibold text-slate-950 dark:text-slate-50">
                                    {{ item.label }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {{ item.status }}
                                </span>
                            </dt>
                            <dd class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                {{ item.count }} · {{ item.policy }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section
                v-if="campaignNavigationContext"
                class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/70 dark:bg-indigo-950/40"
                data-testid="cockpit-voucher-detail-campaign-navigation-context"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-indigo-700 dark:text-indigo-300">
                    Campaign context
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Opened from campaign activity
                </h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This Pay Code is being inspected with campaign-recipient context preserved. These links only change the read-only Cockpit view; they do not update campaign state, issue Pay Codes, send feedback, write journal entries, call providers, or move money.
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
                            Current page
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.destination }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Safety
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.mutation?.reason }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Payload visibility
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ campaignNavigationContext.redactions?.payloads }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-3">
                    <a
                        v-if="campaignExplorerReturnHref"
                        :href="campaignExplorerReturnHref"
                        class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
                        data-testid="cockpit-voucher-detail-campaign-explorer-return-link"
                    >
                        Back to Explorer · read-only
                    </a>
                    <a
                        v-if="campaignDashboardReturnHref"
                        :href="campaignDashboardReturnHref"
                        class="inline-flex items-center rounded-full border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50"
                        data-testid="cockpit-voucher-detail-campaign-dashboard-return-link"
                    >
                        Back to Campaign Dashboard · read-only
                    </a>
                </div>
            </section>

            <section
                v-if="distributionLinksAvailable"
                class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-voucher-detail-distribution-links-panel"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                    Read-only distribution link
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Beneficiary Pay Code URL
                </h3>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    This card exposes the canonical beneficiary claim URL for manual operator inspection. It is read-only;
                    delivery disabled means Cockpit does not send SMS, email, webhook, in-app feedback, campaign dispatch,
                    journal entries, provider calls, or money movement from this panel.
                </p>
                <dl class="mt-5 grid gap-3 text-sm md:grid-cols-3">
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70 md:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Full URL
                        </dt>
                        <dd class="mt-1 break-all font-semibold text-slate-950 dark:text-slate-50">
                            <a
                                v-if="beneficiaryRedeemUrl"
                                :href="beneficiaryRedeemUrl"
                                class="text-emerald-700 underline decoration-emerald-300 underline-offset-4 transition hover:text-emerald-900 dark:text-emerald-200 dark:decoration-emerald-700 dark:hover:text-emerald-100"
                                data-testid="cockpit-voucher-detail-beneficiary-url-link"
                            >
                                {{ beneficiaryRedeemUrl }}
                            </a>
                            <span v-else>
                                {{ beneficiaryRedeemPath }}
                            </span>
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Path
                        </dt>
                        <dd class="mt-1 break-all font-semibold text-slate-950 dark:text-slate-50">
                            {{ beneficiaryRedeemPath ?? 'path unavailable' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Source
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ distributionLinks?.source ?? 'x-change.claim.experience' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Delivery
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            delivery disabled
                        </dd>
                    </div>
                    <div class="rounded-lg bg-white/80 p-3 dark:bg-slate-950/70">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Payload policy
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ distributionLinksPolicy }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-5">
                    <CockpitManualCopyButton
                        :value="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
                        label="Copy beneficiary URL"
                    />
                </div>
                <div
                    class="mt-5 rounded-lg border border-emerald-200 bg-white/80 p-4 text-sm leading-6 text-slate-600 dark:border-emerald-900/60 dark:bg-slate-950/70 dark:text-slate-300"
                    data-testid="cockpit-voucher-detail-manual-distribution-guidance"
                >
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        Manual distribution guidance
                    </p>
                    <ul class="mt-3 list-disc space-y-1 pl-5">
                        <li>Use this copied link for manual distribution only.</li>
                        <li>Share it only through an approved external workflow after verifying the recipient.</li>
                        <li>Cockpit does not send SMS, email, webhook, in-app notification, or campaign delivery from this panel.</li>
                        <li>Cockpit does not record copy telemetry, create short links, or generate QR assets here.</li>
                        <li>Treat this beneficiary URL as sensitive settlement access material.</li>
                    </ul>
                </div>
            </section>

            <CockpitVoucherOverviewPanel :items="overviewItems" />

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-voucher-integration-summary-panel"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Voucher Integration Summary
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Journal · Action · Feedback
                </h3>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <article
                        v-for="summary in integrationSummaries"
                        :key="summary.key"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                        data-testid="cockpit-voucher-integration-summary-card"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-slate-950 dark:text-slate-50">
                                {{ summary.label }}
                            </p>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ summary.status }}
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                            {{ summary.count }}
                        </p>
                        <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            {{ summary.policy }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            {{ summary.reason }}
                        </p>
                    </article>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <CockpitVoucherTimelinePanel :items="timelineItems" />
                <div class="space-y-6">
                    <CockpitVoucherEvidencePanel
                        :heading="evidenceHeading"
                        :items="evidenceItems"
                    />
                    <CockpitVoucherDistributionPanel :items="distributionItems" />
                    <CockpitVoucherAuditPanel
                        :audits="auditItems"
                        :actions="detailActions"
                    />
                </div>
            </div>
        </section>
    </CockpitLayout>
</template>
