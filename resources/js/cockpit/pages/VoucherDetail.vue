<script setup lang="ts">
import { computed } from 'vue';
import CockpitVoucherAuditPanel from '../components/CockpitVoucherAuditPanel.vue';
import CockpitVoucherDistributionPanel from '../components/CockpitVoucherDistributionPanel.vue';
import CockpitVoucherEvidencePanel from '../components/CockpitVoucherEvidencePanel.vue';
import CockpitVoucherOverviewPanel from '../components/CockpitVoucherOverviewPanel.vue';
import CockpitVoucherTimelinePanel from '../components/CockpitVoucherTimelinePanel.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitDependentReadModel,
    CockpitReadModelRedactions,
    CockpitVoucherAuditItem,
    CockpitVoucherDetailAction,
    CockpitVoucherDetailPageProps,
    CockpitVoucherDistributionItem,
    CockpitVoucherEvidenceItem,
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
]);

const distributionItems = computed<CockpitVoucherDistributionItem[]>(() => [
    {
        id: 'sms',
        channel: 'SMS',
        status: readModelStatus(props.read_model?.feedback),
        helper: 'SMS delivery status remains owned by x-feedback read models.',
    },
    {
        id: 'email',
        channel: 'Email',
        status: readModelStatus(props.read_model?.feedback),
        helper: 'Email delivery status remains owned by x-feedback read models.',
    },
    {
        id: 'in-app',
        channel: 'In-app',
        status: readModelStatus(props.read_model?.feedback),
        helper: 'In-app notification state remains x-feedback presentation state.',
    },
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

            <CockpitVoucherOverviewPanel :items="overviewItems" />

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <CockpitVoucherTimelinePanel :items="timelineItems" />
                <div class="space-y-6">
                    <CockpitVoucherEvidencePanel :items="evidenceItems" />
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
