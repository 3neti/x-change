<script setup lang="ts">
import { computed } from 'vue';
import CockpitManualCopyButton from '../components/CockpitManualCopyButton.vue';
import CockpitPayCodeRecordWorkspace from '../components/CockpitPayCodeRecordWorkspace.vue';
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
const redemption = computed<Record<string, unknown> | null>(() => objectValue(summary.value.redemption));
const redemptionStatus = computed(() => settlementStatusLabel(stringValue(redemption.value?.status)));
const redemptionAmount = computed(() => {
    const amountMinor = numberValue(redemption.value?.amount_minor);

    return amountMinor === null
        ? 'Not available'
        : moneyValue(amountMinor / 100, stringValue(redemption.value?.currency));
});
const redemptionProvider = computed(() => providerLabel(stringValue(redemption.value?.provider)));
const redemptionRail = computed(() => settlementRailLabel(stringValue(redemption.value?.settlement_rail)));
const redemptionDestination = computed(() => {
    const bank = institutionLabel(stringValue(redemption.value?.bank_code));
    const account = stringValue(redemption.value?.account_number_masked);

    return [bank, account].filter((value): value is string => value !== null).join(' · ') || 'Not available';
});
const redemptionReference = computed(() => stringValue(redemption.value?.provider_transaction_id) ?? 'Pending');
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
const lifecycleGuidance = computed(() => {
    const normalizedStatus = status.value.toLowerCase();

    if (normalizedStatus.includes('payout_rejected')) {
        return {
            tone: 'critical',
            label: 'Payout Rejected',
            message: 'The claim is recorded, but the provider rejected the payout. The protected principal requires destination correction.',
        };
    }

    if (normalizedStatus.includes('payout_pending')) {
        return {
            tone: 'watch',
            label: 'Payout Pending',
            message: 'The claim is recorded while provider settlement remains in progress.',
        };
    }

    if (normalizedStatus === 'paid') {
        return {
            tone: 'complete',
            label: 'Paid',
            message: 'The provider payout succeeded. Expiry no longer changes this completed financial outcome.',
        };
    }

    if (normalizedStatus.includes('expired')) {
        return {
            tone: 'warning',
            label: 'Expired',
            message: 'The Pay Code appears expired. Review evidence before manual distribution.',
        };
    }

    if (normalizedStatus.includes('redeemed') || normalizedStatus.includes('claimed')) {
        return {
            tone: 'complete',
            label: 'Claimed',
            message: 'The Pay Code appears claimed. Use this screen for read-only inspection.',
        };
    }

    if (normalizedStatus.includes('approval') || normalizedStatus.includes('review')) {
        return {
            tone: 'watch',
            label: 'Needs Review',
            message: 'The Pay Code may require approval or review before completion.',
        };
    }

    return {
        tone: 'ready',
        label: 'Available',
        message: 'The Pay Code is presented as available from the sanitized lifecycle summary.',
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
        label: 'Notification status',
        description: 'Notification summaries remain unavailable until an authorized feedback read model is connected.',
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
        : 'Evidence status'
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
        label: 'Follow-up guidance',
        status: readModelStatus(props.read_model?.actions),
        helper: 'Follow-up guidance can be displayed here, but this page does not execute actions.',
    },
    {
        id: 'feedback',
        label: 'Notification status',
        status: readModelStatus(props.read_model?.feedback),
        helper: 'Notification delivery remains unavailable until an authorized feedback read model is connected.',
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
    integrationSummary('journal', 'Audit Trail', props.read_model?.journal, 'entries', 'entries'),
    integrationSummary('actions', 'Follow-Up Actions', props.read_model?.actions, 'actions', 'actions'),
    integrationSummary('feedback', 'Notifications', props.read_model?.feedback, 'deliveries', 'deliveries'),
]);
const primaryEvidenceReadiness = computed(() => integrationSummaries.value.map((summary) => ({
    ...summary,
    label: summary.label.replace(' Trail', '').replace(' Actions', ''),
})));
const connectedContextSummaries = computed(() => [
    {
        key: 'claim-url',
        label: 'Claim URL',
        status: distributionLinksAvailable.value ? 'Ready' : 'Not available',
        count: beneficiaryRedeemUrl.value ? 'Full URL' : 'Path pending',
        source: 'Claim experience',
    },
    {
        key: 'delivery-evidence',
        label: 'Delivery Evidence',
        status: operatorStatus(readModelStatus(props.read_model?.feedback)),
        count: collectionCount(props.read_model?.feedback, 'deliveries', 'deliveries'),
        source: 'Notification summaries',
    },
    {
        key: 'follow-up-guidance',
        label: 'Follow-Up Guidance',
        status: operatorStatus(readModelStatus(props.read_model?.actions)),
        count: collectionCount(props.read_model?.actions, 'actions', 'actions'),
        source: 'Disabled action guidance',
    },
    {
        key: 'audit-evidence',
        label: 'Audit Evidence',
        status: operatorStatus(readModelStatus(props.read_model?.journal)),
        count: collectionCount(props.read_model?.journal, 'entries', 'entries'),
        source: 'Journal summaries',
    },
]);
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

function stringValue(value: unknown): string | null {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return null;
}

function numberValue(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : null;
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

function titleValue(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
}

function institutionLabel(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return {
        GXCHPHM2XXX: 'GCash',
        PAPHPHM1XXX: 'Maya',
    }[value] ?? value;
}

function providerLabel(value: string | null): string {
    return {
        netbank: 'NetBank',
        paynamics: 'Paynamics',
    }[value?.toLowerCase() ?? ''] ?? titleValue(value) ?? 'Not available';
}

function settlementRailLabel(value: string | null): string {
    return {
        INSTAPAY: 'InstaPay',
        PESONET: 'PESONet',
    }[value?.toUpperCase() ?? ''] ?? value ?? 'Not available';
}

function settlementStatusLabel(value: string | null): string {
    return {
        succeeded: 'Paid',
        completed: 'Paid',
        pending: 'Pending',
        unknown: 'Needs Review',
        failed: 'Failed',
    }[value ?? ''] ?? titleValue(value) ?? 'Recorded';
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

function operatorStatus(status: string): string {
    if (status === 'not_wired') {
        return 'Not connected';
    }

    if (status === 'available') {
        return 'Available';
    }

    return status;
}

function collectionCount(
    model: CockpitDependentReadModel | undefined,
    collectionKey: 'entries' | 'actions' | 'deliveries',
    noun: string,
): string {
    const collection = Array.isArray(model?.[collectionKey]) ? model[collectionKey] : [];

    return `${collection.length} ${noun}`;
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
                label: 'Audit trail',
                status: readModelStatus(model),
                helper: 'Audit entries remain unavailable until an authorized journal read model is connected.',
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
    const payload = objectValue(journalEntry.payload);
    const summary = stringValue(journalEntry.summary)
        ?? stringValue(payload?.summary)
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
    const description = stringValue(actionItem.description);
    const target = objectValue(actionItem.target);
    const route = stringValue(target?.route);
    const payloadPolicy = stringValue(actionRedactions?.payloads) ?? 'safe-action-host-summary-only';
    const reasonParts = [
        description,
        route === null ? null : `Target: ${route}`,
        `${status} · ${payloadPolicy}`,
        'Follow-up action is disabled; Cockpit does not execute x-action actions from Voucher Detail.',
    ].filter((part): part is string => part !== null && part.trim() !== '');

    return {
        key,
        label,
        disabled: true,
        reason: reasonParts.join(' · '),
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
    const providerStatus = stringValue(deliveryItem.provider_status);
    const attemptCount = numberValue(deliveryItem.attempt_count);
    const maxAttempts = numberValue(deliveryItem.max_attempts);
    const attemptSummary = attemptCount === null
        ? null
        : `Attempts: ${attemptCount}${maxAttempts === null ? '' : `/${maxAttempts}`}`;
    const helperParts = [
        payloadPolicy,
        providerStatus === null ? null : `Provider status: ${providerStatus}`,
        attemptSummary,
        'Notification delivery remains read-only from Cockpit.',
    ].filter((part): part is string => part !== null && part.trim() !== '');

    return {
        id: stringValue(deliveryItem.delivery_id) ?? stringValue(deliveryItem.id) ?? `feedback-${index + 1}`,
        channel: channelLabel(channel),
        status,
        helper: helperParts.join(' · '),
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
        status: operatorStatus(readModelStatus(model)),
        count: `${collection.length} ${noun}`,
        policy: policyLabel(stringValue(model?.redactions?.payloads) ?? 'not-loaded'),
        reason: policyLabel(stringValue(model?.redactions?.reason) ?? 'read-model-ready'),
    };
}

function policyLabel(value: string): string {
    return {
        'not-loaded': 'No data loaded',
        'read-model-ready': 'Ready for display',
        'journal-evidence-summary-only': 'Audit summary only',
        'safe-action-host-summary-only': 'Follow-up summary only',
        'communication-delivery-summary-only': 'Notification summary only',
        'read-model-unavailable': 'Read model unavailable',
    }[value] ?? value.replaceAll('-', ' ');
}
</script>

<template>
    <CockpitLayout
        active-navigation="pay-codes"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <section class="space-y-6" data-testid="cockpit-voucher-detail-shell">
            <div
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-voucher-detail-header"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center"
                    data-testid="cockpit-voucher-detail-header-row"
                >
                    <div class="min-w-0 lg:w-64 lg:shrink-0 xl:w-72">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                Pay Code inspection
                            </p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                read-only
                            </span>
                        </div>
                        <h2 class="mt-1 text-lg font-semibold leading-6 text-slate-950 dark:text-slate-50">
                            Pay Code Detail
                        </h2>
                        <p class="mt-0.5 text-xs leading-4 text-slate-600 dark:text-slate-300">
                            Inspect lifecycle state, claim readiness, and connected evidence.
                        </p>
                    </div>
                    <dl
                        class="grid w-full gap-1.5 rounded-lg bg-slate-50 p-1.5 text-xs sm:grid-cols-3 lg:min-w-0 lg:flex-1 dark:bg-slate-950"
                        data-testid="cockpit-voucher-detail-header-facts"
                    >
                        <div
                            class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                            data-testid="cockpit-voucher-detail-header-fact"
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
                            data-testid="cockpit-voucher-detail-header-fact"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Lifecycle status
                            </dt>
                            <dd class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50">
                                {{ status }}
                            </dd>
                        </div>
                        <div
                            class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                            data-testid="cockpit-voucher-detail-header-fact"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Payload policy
                            </dt>
                            <dd class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50">
                                {{ redactions.payloads ?? 'not-loaded' }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <details
                    class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-800"
                    data-testid="cockpit-voucher-detail-boundary"
                >
                    <summary class="cursor-pointer text-[0.7rem] font-semibold text-slate-500 dark:text-slate-400">
                        Read-only limits
                    </summary>
                    <p class="mt-2 max-w-4xl text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Inspection only. This page can open or copy the claim URL, but it cannot change the Pay Code, send messages, call providers, or move money.
                    </p>
                </details>
            </div>

            <CockpitPayCodeRecordWorkspace
                :code="code"
                :status="status"
                :voucher="props.read_model?.voucher"
                :journal="props.read_model?.journal"
                :feedback="props.read_model?.feedback"
                :redemption="redemption"
                :claim-url="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
                :distribution-url="distributionWorkspaceHref"
                :explorer-url="explorerHref"
            />

            <div class="hidden" aria-hidden="true" data-testid="cockpit-voucher-detail-legacy-projection">
            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-voucher-detail-primary-summary"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Operator detail summary
                        </p>
                        <h3 class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Pay Code {{ code }}
                        </h3>
                        <p class="mt-1 max-w-3xl text-sm leading-5 text-slate-600 dark:text-slate-300">
                            This summary is built from sanitized voucher read-model facts. It gives operators the current
                            lifecycle state, beneficiary URL readiness, and safe next steps without mutating the Pay Code
                            or triggering delivery.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-slate-950 dark:text-emerald-200 dark:ring-emerald-800">
                        read-only
                    </span>
                </div>

                <dl
                    class="mt-4 grid gap-2 rounded-xl bg-white/50 p-2 text-sm md:grid-cols-2 xl:grid-cols-4 dark:bg-slate-950/30"
                    data-testid="cockpit-voucher-detail-primary-readiness-strip"
                >
                    <div
                        class="rounded-xl bg-white/80 px-3 py-2 dark:bg-slate-950/70"
                        data-testid="cockpit-voucher-detail-primary-readiness-item"
                    >
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Lifecycle
                        </dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            {{ status }}
                        </dd>
                        <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            Display state only; no execution is invoked.
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-white/80 px-3 py-2 dark:bg-slate-950/70"
                        data-testid="cockpit-voucher-detail-primary-readiness-item"
                    >
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Amount
                        </dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            {{ amountDisplay }}
                        </dd>
                        <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            Sanitized summary amount only.
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-white/80 px-3 py-2 dark:bg-slate-950/70"
                        data-testid="cockpit-voucher-detail-primary-readiness-item"
                    >
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Claim State
                        </dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            {{ claimStateDisplay }}
                        </dd>
                        <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            Claim payloads remain redacted.
                        </p>
                    </div>
                    <div
                        class="rounded-xl bg-white/80 px-3 py-2 dark:bg-slate-950/70"
                        data-testid="cockpit-voucher-detail-primary-readiness-item"
                    >
                        <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Claim URL
                        </dt>
                        <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            {{ distributionLinksAvailable ? 'ready' : 'not available' }}
                        </dd>
                        <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            {{ distributionLinksAvailable ? 'Manual copy/inspection only.' : 'Waiting for distribution link read model.' }}
                        </p>
                    </div>
                </dl>

                <section
                    v-if="redemption"
                    class="mt-3 rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/60 dark:bg-slate-950/70"
                    data-testid="cockpit-voucher-detail-redemption-summary"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                            Redemption
                        </p>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                            {{ redemptionStatus }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-5">
                        <div>
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Paid Amount</dt>
                            <dd class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ redemptionAmount }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Provider</dt>
                            <dd class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ redemptionProvider }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Rail</dt>
                            <dd class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ redemptionRail }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Destination</dt>
                            <dd class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ redemptionDestination }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Transaction</dt>
                            <dd class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ redemptionReference }}</dd>
                        </div>
                    </dl>
                </section>

                <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
                    <div class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/60 dark:bg-slate-950/70">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                            Operator next step
                        </p>
                        <p class="mt-1.5 text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ primaryNextStep.label }}
                        </p>
                        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ primaryNextStep.description }}
                        </p>
                        <p class="mt-1.5 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            Availability: {{ availabilityDisplay }} · Payload policy: {{ redactions.payloads ?? 'not-loaded' }}
                        </p>
                        <div
                            class="mt-3 flex flex-wrap items-start gap-2"
                            data-testid="cockpit-voucher-detail-primary-actions"
                        >
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

                    <details
                        class="self-start rounded-xl border p-3"
                        :class="
                            lifecycleGuidance.tone === 'warning'
                                ? 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/30'
                                : lifecycleGuidance.tone === 'complete'
                                  ? 'border-sky-200 bg-sky-50 dark:border-sky-900/60 dark:bg-sky-950/30'
                                  : lifecycleGuidance.tone === 'watch'
                                    ? 'border-violet-200 bg-violet-50 dark:border-violet-900/60 dark:bg-violet-950/30'
                                    : 'border-emerald-200 bg-white/80 dark:border-emerald-900/60 dark:bg-slate-950/70'
                        "
                        data-testid="cockpit-voucher-detail-lifecycle-guidance"
                    >
                        <summary class="cursor-pointer list-none">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                Lifecycle guidance
                            </p>
                            <span class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                                    {{ lifecycleGuidance.label }}
                                </span>
                                <span class="rounded-full bg-white px-2.5 py-0.5 text-[0.7rem] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-200 dark:ring-slate-800">
                                    {{ lifecycleGuidance.tone }}
                                </span>
                            </span>
                        </summary>
                        <p class="mt-2 border-t border-current/10 pt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                            {{ lifecycleGuidance.message }}
                        </p>
                        <p class="mt-1 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            Derived from display status only; Cockpit does not enforce lifecycle policy from this page.
                        </p>
                    </details>
                </div>

                <details
                    class="mt-3 border-t border-emerald-200 pt-3 dark:border-emerald-900/60"
                    data-testid="cockpit-voucher-detail-connected-context"
                >
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        <span class="flex flex-wrap items-center gap-2">
                            <span>Connected context</span>
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-slate-950/70 dark:text-emerald-200">
                                4 read-only facts
                            </span>
                        </span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-emerald-950 dark:text-emerald-200">
                            read-only
                        </span>
                    </summary>
                    <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Main inspection facts for claim access, notification state, follow-up guidance, and audit evidence.
                    </p>
                    <dl class="mt-2 grid gap-1.5 text-xs sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="item in connectedContextSummaries"
                            :key="item.key"
                            class="rounded-lg bg-white/70 px-2.5 py-2 ring-1 ring-slate-200 dark:bg-slate-950/60 dark:ring-slate-800"
                            data-testid="cockpit-voucher-detail-connected-context-item"
                        >
                            <dt class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-950 dark:text-slate-50">
                                    {{ item.label }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {{ item.status }}
                                </span>
                            </dt>
                            <dd class="mt-1 text-xs font-semibold text-slate-950 dark:text-slate-50">
                                {{ item.count }}
                            </dd>
                            <p class="mt-0.5 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                                {{ item.source }}
                            </p>
                        </div>
                    </dl>
                </details>

                <details
                    class="mt-3 border-t border-emerald-200 pt-3 dark:border-emerald-900/60"
                    data-testid="cockpit-voucher-detail-primary-evidence-readiness"
                >
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        <span class="flex flex-wrap items-center gap-2">
                            <span>Connected services</span>
                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-slate-950/70 dark:text-emerald-200">
                                3 service summaries
                            </span>
                        </span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.65rem] tracking-normal text-emerald-700 normal-case dark:bg-emerald-950 dark:text-emerald-200">
                            summary only
                        </span>
                    </summary>
                    <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                        Read-only audit, follow-up, and notification state. These facts do not execute actions, send notifications, or write journal entries.
                    </p>
                    <dl class="mt-2 grid gap-1.5 text-xs md:grid-cols-3">
                        <div
                            v-for="item in primaryEvidenceReadiness"
                            :key="item.key"
                            class="rounded-lg bg-white/70 px-2.5 py-2 ring-1 ring-slate-200 dark:bg-slate-950/60 dark:ring-slate-800"
                            data-testid="cockpit-voucher-detail-primary-evidence-readiness-item"
                        >
                            <dt class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-950 dark:text-slate-50">
                                    {{ item.label }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {{ item.status }}
                                </span>
                            </dt>
                            <dd class="mt-1 text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                                <span>{{ item.count }} · {{ item.policy }}</span>
                                <span
                                    class="block"
                                    data-testid="cockpit-voucher-detail-primary-evidence-readiness-reason"
                                >
                                    {{ item.reason }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </details>
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

            <details
                v-if="distributionLinksAvailable"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-voucher-detail-distribution-links-panel"
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
                        Copying is browser-local; delivery disabled means recipient verification and delivery remain external.
                    </p>
                </div>
                <div
                    class="mt-3 grid gap-2 rounded-lg border border-emerald-200 bg-white/70 p-2.5 text-xs dark:border-emerald-900/60 dark:bg-slate-950/60 sm:grid-cols-3"
                    data-testid="cockpit-voucher-detail-distribution-link-density-summary"
                >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">
                            Claim URL
                        </p>
                        <p class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">
                            {{ beneficiaryRedeemUrl ? 'Ready' : 'Path only' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">
                            Delivery
                        </p>
                        <p class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">
                            Disabled
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">
                            Copy
                        </p>
                        <p class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">
                            Browser-local
                        </p>
                    </div>
                </div>
                <dl class="mt-3 grid gap-2 text-xs">
                    <div class="rounded-lg bg-white/80 p-2.5 dark:bg-slate-950/70">
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
                </dl>
                <details
                    class="mt-2 rounded-lg border border-emerald-200 bg-white/70 p-2.5 text-xs text-slate-600 dark:border-emerald-900/60 dark:bg-slate-950/60 dark:text-slate-300"
                    data-testid="cockpit-voucher-detail-distribution-link-metadata"
                >
                    <summary class="cursor-pointer font-semibold text-slate-700 dark:text-slate-200">
                        Link details
                    </summary>
                    <dl class="mt-2 grid gap-2 md:grid-cols-3">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Path
                            </dt>
                            <dd class="mt-1 break-all font-semibold text-slate-950 dark:text-slate-50">
                                {{ beneficiaryRedeemPath ?? 'path unavailable' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Source
                            </dt>
                            <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                                {{ distributionLinks?.source ?? 'x-change.claim.experience' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Payload policy
                            </dt>
                            <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                                {{ distributionLinksPolicy }}
                            </dd>
                        </div>
                    </dl>
                </details>
                <div class="mt-3">
                    <CockpitManualCopyButton
                        :value="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"
                        label="Copy beneficiary URL"
                    />
                </div>
                <details
                    class="mt-3 rounded-lg border border-emerald-200 bg-white/80 p-3 text-xs leading-5 text-slate-600 dark:border-emerald-900/60 dark:bg-slate-950/70 dark:text-slate-300"
                    data-testid="cockpit-voucher-detail-manual-distribution-guidance"
                >
                    <summary class="cursor-pointer font-semibold text-slate-950 dark:text-slate-50">
                        Manual distribution guidance
                    </summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Use this copied link for manual distribution only.</li>
                        <li>Share it only through an approved external workflow after verifying the recipient.</li>
                        <li>Cockpit does not send SMS, email, webhook, in-app notification, or campaign delivery from this panel.</li>
                        <li>Cockpit does not record copy telemetry, create short links, or generate QR assets here.</li>
                        <li>Treat this beneficiary URL as sensitive settlement access material.</li>
                    </ul>
                </details>
            </details>

            <div class="space-y-3" data-testid="cockpit-voucher-secondary-content">
                <CockpitVoucherOverviewPanel :items="overviewItems" />

                <div
                    class="grid items-start gap-3 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"
                    data-testid="cockpit-voucher-supporting-evidence-grid"
                >
                    <CockpitVoucherTimelinePanel :items="timelineItems" />
                    <div class="space-y-3" data-testid="cockpit-voucher-supporting-evidence-stack">
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
            </div>
            </div>
        </section>
    </CockpitLayout>
</template>
