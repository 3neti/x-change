<script setup lang="ts">
import { Activity, ArrowLeft, Megaphone, TicketPlus } from 'lucide-vue-next';
import { computed } from 'vue';
import CockpitPayCodeFilterBuilder from '../components/CockpitPayCodeFilterBuilder.vue';
import CockpitPayCodeResultsTable from '../components/CockpitPayCodeResultsTable.vue';
import CockpitPayCodeSearchBar from '../components/CockpitPayCodeSearchBar.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitPayCodeExplorerFilter,
    CockpitActivityNavigationContext,
    CockpitCampaignNavigationContext,
    CockpitDependentReadModel,
    CockpitPayCodeExplorerPageProps,
    CockpitPayCodeExplorerRecord,
    CockpitPayCodeExplorerReadModelRecord,
} from '../types';
import {
    cockpitPayCodeExplorerFilters,
    cockpitPayCodeExplorerRecords,
    cockpitPayCodeRowActions,
} from '../payCodeExplorerDefaults';

const props = defineProps<CockpitPayCodeExplorerPageProps>();

const readModel = computed(() => props.pay_codes_read_model);
const isHydrated = computed(() => readModel.value?.authorized === true);
const canViewTechnicalDetails = computed(
    () =>
        props.can?.view_technical_details === true &&
        props.read_model !== undefined,
);
const query = computed(() => stringValue(readModel.value?.query) ?? '');
const statusFilter = computed(
    () => stringValue(readModel.value?.status_filter) ?? null,
);
const payloadPolicy = computed(
    () => stringValue(readModel.value?.redactions?.payloads) ?? 'not-loaded',
);
const status = computed(
    () => stringValue(readModel.value?.status) ?? 'not_wired',
);
const stats = computed(() => ({
    total: numberValue(readModel.value?.stats?.total),
    active: numberValue(readModel.value?.stats?.active),
    awaitingApproval: numberValue(readModel.value?.stats?.awaiting_approval),
    redeemed: numberValue(readModel.value?.stats?.redeemed),
    expired: numberValue(readModel.value?.stats?.expired),
    pending: numberValue(readModel.value?.stats?.pending),
    failed: numberValue(readModel.value?.stats?.failed),
    filtered: numberValue(readModel.value?.stats?.filtered),
}));
const attentionCount = computed(
    () =>
        stats.value.awaitingApproval + stats.value.pending + stats.value.failed,
);
const quickGenerateHref = computed(() => '/x/cockpit/quick-generate');
const primarySummaryItems = computed(() => [
    {
        key: 'total',
        label: 'Total',
        value: String(stats.value.total || records.value.length),
        helper: 'All Pay Codes in the sanitized read model.',
    },
    {
        key: 'active',
        label: 'Active',
        value: String(stats.value.active),
        helper: 'Currently claimable or available summaries.',
    },
    {
        key: 'redeemed',
        label: 'Redeemed',
        value: String(stats.value.redeemed),
        helper: 'Completed redemption summaries.',
    },
    {
        key: 'attention',
        label: 'Needs attention',
        value: String(attentionCount.value),
        helper: 'Pending, failed, or awaiting approval.',
    },
]);
const currentSearchItems = computed(() => [
    {
        key: 'search',
        label: 'Search',
        value: query.value || 'All Pay Codes',
        helper: query.value
            ? 'Search term applied to the current read-only list.'
            : 'No search term is applied.',
    },
    {
        key: 'status',
        label: 'Status',
        value: statusFilter.value ?? 'All statuses',
        helper: statusFilter.value
            ? 'Status filter applied to the current read-only list.'
            : 'No lifecycle status filter is applied.',
    },
    {
        key: 'visible',
        label: 'Visible Rows',
        value: String(stats.value.filtered || records.value.length),
        helper: 'Rows currently visible from sanitized read-model facts.',
    },
    {
        key: 'campaign',
        label: 'Campaign Context',
        value: campaignNavigationContext.value ? 'Preserved' : 'None',
        helper: campaignNavigationContext.value
            ? 'Campaign identifiers are preserved as read-only filter context.'
            : 'No campaign filter context is attached.',
    },
]);
const enabledRowActionCount = computed(() =>
    records.value.reduce(
        (count, record) =>
            count +
            (record.actions ?? []).filter(
                (action) => action.enabled && action.href !== null,
            ).length,
        0,
    ),
);
const disabledRowActionCount = computed(() =>
    records.value.reduce(
        (count, record) =>
            count +
            (record.actions ?? []).filter((action) => action.disabled).length,
        0,
    ),
);
const rowActionGuidance = computed(() => [
    {
        key: 'navigation',
        label: 'Navigation Links',
        value: String(enabledRowActionCount.value),
        helper: 'Enabled row actions are read-only links to Cockpit detail or distribution pages.',
    },
    {
        key: 'blocked',
        label: 'Blocked Actions',
        value: String(disabledRowActionCount.value),
        helper: 'Disabled row actions remain informational and do not execute feedback or workflow actions.',
    },
    {
        key: 'result-rows',
        label: 'Rows',
        value: String(records.value.length),
        helper: 'Rows are sanitized before rendering.',
    },
]);
const campaignNavigationContext =
    computed<CockpitCampaignNavigationContext | null>(() => {
        const context = props.campaign_navigation_context;

        if (!context?.authorized || context.read_only !== true) {
            return null;
        }

        const planningKey = stringValue(context.planning_key);
        const executionId = stringValue(context.execution_id);
        const destination = stringValue(context.destination);

        if (!planningKey || !executionId || !destination) {
            return null;
        }

        return {
            schema:
                stringValue(context.schema) ??
                'x-change.cockpit.campaign-navigation.v1',
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
                status:
                    stringValue(objectValue(context.mutation).status) ??
                    'blocked',
                reason:
                    stringValue(objectValue(context.mutation).reason) ??
                    'campaign-navigation-read-only',
            },
            redactions: {
                payloads:
                    stringValue(context.redactions?.payloads) ??
                    'navigation-context-only',
            },
        };
    });
const campaignNavigationContextItems = computed(() => {
    const context = campaignNavigationContext.value;

    if (!context) {
        return [];
    }

    return [
        {
            key: 'planning-key',
            label: 'Planning Key',
            value: context.planning_key,
        },
        {
            key: 'execution-id',
            label: 'Execution ID',
            value: context.execution_id,
        },
        {
            key: 'campaign-id',
            label: 'Campaign ID',
            value: context.campaign_id ?? 'Not provided',
        },
        {
            key: 'audience-id',
            label: 'Audience ID',
            value: context.audience_id ?? 'Not provided',
        },
        {
            key: 'recipient-id',
            label: 'Recipient ID',
            value: context.recipient_id ?? 'Not provided',
        },
        {
            key: 'source',
            label: 'Source',
            value: context.source,
        },
        {
            key: 'destination',
            label: 'Destination',
            value: 'Pay Code Explorer',
        },
        {
            key: 'payload-policy',
            label: 'Payload Policy',
            value: context.redactions?.payloads ?? 'navigation-context-only',
        },
    ];
});
const campaignDashboardHref = computed(() => {
    const context = campaignNavigationContext.value;

    if (!context) {
        return '/x/cockpit/overview';
    }

    const params = new URLSearchParams({
        campaign_planning_key: context.planning_key ?? '',
        campaign_execution_id: context.execution_id ?? '',
        campaign_source: context.source ?? 'campaign_cockpit',
    });

    if (context.campaign_id) {
        params.set('campaign_id', context.campaign_id);
    }

    if (context.audience_id) {
        params.set('campaign_audience_id', context.audience_id);
    }

    if (context.recipient_id) {
        params.set('campaign_recipient_id', context.recipient_id);
    }

    return `/x/cockpit/overview?${params.toString()}`;
});
const campaignExplorerContextParams = computed(() => {
    const context = campaignNavigationContext.value;

    if (!context) {
        return [];
    }

    const fields = [
        {
            name: 'campaign_planning_key',
            value: context.planning_key ?? '',
        },
        {
            name: 'campaign_execution_id',
            value: context.execution_id ?? '',
        },
        {
            name: 'campaign_source',
            value: context.source ?? 'campaign_cockpit',
        },
    ];

    if (context.campaign_id) {
        fields.push({ name: 'campaign_id', value: context.campaign_id });
    }

    if (context.audience_id) {
        fields.push({
            name: 'campaign_audience_id',
            value: context.audience_id,
        });
    }

    if (context.recipient_id) {
        fields.push({
            name: 'campaign_recipient_id',
            value: context.recipient_id,
        });
    }

    return fields.filter((field) => field.value.trim() !== '');
});
const campaignExplorerBaseHref = computed(() => {
    const params = new URLSearchParams();

    for (const field of campaignExplorerContextParams.value) {
        params.set(field.name, field.value);
    }

    const queryString = params.toString();

    return queryString === ''
        ? '/x/cockpit/pay-codes'
        : `/x/cockpit/pay-codes?${queryString}`;
});
const activityNavigationContext =
    computed<CockpitActivityNavigationContext | null>(() => {
        const context = props.activity_navigation_context;

        if (!context?.authorized || context.read_only !== true) {
            return null;
        }

        const code = stringValue(context.code);
        const destination = stringValue(context.destination);

        if (!code || !destination) {
            return null;
        }

        return {
            schema:
                stringValue(context.schema) ??
                'x-change.cockpit.activity-navigation.v1',
            status: stringValue(context.status) ?? 'available',
            authorized: true,
            source: stringValue(context.source) ?? 'operator_issuance_activity',
            code,
            destination,
            read_only: true,
            mutation: {
                enabled: false,
                status:
                    stringValue(objectValue(context.mutation).status) ??
                    'blocked',
                reason:
                    stringValue(objectValue(context.mutation).reason) ??
                    'activity-navigation-read-only',
            },
            redactions: {
                payloads:
                    stringValue(context.redactions?.payloads) ??
                    'activity-navigation-context-only',
            },
        };
    });

const filters = computed<CockpitPayCodeExplorerFilter[]>(() => {
    if (!isHydrated.value) {
        return cockpitPayCodeExplorerFilters;
    }

    const readModelFilters = readModel.value?.filters ?? [];

    return [
        {
            key: 'query',
            label: 'Query',
            value: query.value || 'All sanitized records',
            helper: 'Filtering remains local and read-only until an approved host query API exists.',
        },
        {
            key: 'payload-policy',
            label: 'Payload policy',
            value: payloadPolicy.value,
            helper: 'Only sanitized list fields are rendered in this slice.',
        },
        {
            key: 'readiness',
            label: 'Read model status',
            value: status.value,
            helper: 'This status describes list-readiness only; it is not voucher lifecycle truth.',
        },
        ...campaignExplorerContextParams.value.map((field) => ({
            key: field.name,
            label: field.name
                .replace(/^campaign_/, 'Campaign ')
                .replaceAll('_', ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase()),
            value: field.value,
            active: true,
            read_only: true,
            helper: 'Campaign context is preserved as read-only Explorer orientation metadata.',
        })),
        ...readModelFilters.map((filter) => ({
            key: filter.key,
            label: filter.label,
            value: filter.value,
            active: filter.active === true,
            read_only: filter.read_only !== false,
            helper:
                filter.read_only === false
                    ? 'Unexpected writable filter metadata.'
                    : 'Read-only GET filter metadata.',
        })),
    ];
});

const records = computed<CockpitPayCodeExplorerRecord[]>(() => {
    if (!isHydrated.value) {
        return cockpitPayCodeExplorerRecords;
    }

    return (readModel.value?.records ?? [])
        .map((record) => sanitizeRecord(record))
        .filter(
            (record): record is CockpitPayCodeExplorerRecord => record !== null,
        );
});

const integrationBadges = computed(() => [
    integrationBadge('journal', 'Journal', props.read_model?.journal),
    integrationBadge('actions', 'Actions', props.read_model?.actions),
    integrationBadge('feedback', 'Feedback', props.read_model?.feedback),
]);
function sanitizeRecord(
    record: CockpitPayCodeExplorerReadModelRecord,
): CockpitPayCodeExplorerRecord | null {
    const code = stringValue(record.code);

    if (code === null) {
        return null;
    }

    const operationalStatus = sanitizeOperationalStatus(record);
    const consumerStatus = stringValue(record.consumer_status);
    const posReference = objectValue(record.pos_reference);

    return {
        code,
        template: stringValue(record.template) ?? 'Template pending',
        purpose:
            stringValue(posReference.purpose) ?? stringValue(record.purpose),
        capability: sanitizeCapability(record.capability),
        instructionBadges: sanitizeInstructionBadges(record.instruction_badges),
        amount: moneyValue(record.amount, stringValue(record.currency)),
        status: consumerStatus ?? operationalStatus.key,
        consumerStatus,
        collection: objectValue(record.collection),
        posReference,
        voucherStatus: stringValue(record.voucher_status),
        operationalStatus,
        party: sanitizeParty(record.party),
        timing: sanitizeTiming(record),
        terminalControl: sanitizeTerminalControl(record),
        owner: stringValue(record.owner) ?? 'Redacted',
        lastActivity:
            stringValue(record.last_activity) ?? 'Read model activity pending',
        attention: sanitizeAttention(record.attention),
        actions: Array.isArray(record.actions)
            ? record.actions
                  .map((action) => sanitizeRowAction(action))
                  .map((action) => appendCampaignContextToRowAction(action))
                  .filter(
                      (
                          action,
                      ): action is NonNullable<
                          ReturnType<typeof sanitizeRowAction>
                      > => action !== null,
                  )
            : [],
    };
}

function sanitizeOperationalStatus(
    record: CockpitPayCodeExplorerReadModelRecord,
): CockpitPayCodeExplorerRecord['operationalStatus'] {
    const value = objectValue(record.operational_status);
    const fallbackKey =
        stringValue(record.display_status) ??
        stringValue(record.status) ??
        'not_wired';
    const key = stringValue(value.key) ?? fallbackKey;

    return {
        key,
        label: stringValue(value.label) ?? displayValue(key),
        tone: stringValue(value.tone) ?? 'neutral',
        availabilityKey: stringValue(value.availability_key) ?? 'unknown',
        availabilityLabel: stringValue(value.availability_label) ?? 'Unknown',
        settlementOutcome:
            stringValue(value.settlement_outcome) ?? 'not_applicable',
        isTerminal: value.is_terminal === true,
        canClaim: value.can_claim === true,
        canRetryPayout: value.can_retry_payout === true,
    };
}

function displayValue(value: string): string {
    return value
        .split(/[_\s-]+/)
        .filter((part) => part.trim() !== '')
        .map(
            (part) =>
                `${part.charAt(0).toUpperCase()}${part.slice(1).toLowerCase()}`,
        )
        .join(' ');
}

function sanitizeAttention(
    attention: CockpitPayCodeExplorerReadModelRecord['attention'],
): CockpitPayCodeExplorerRecord['attention'] {
    const value = objectValue(attention);
    const key = stringValue(value.key);
    const label = stringValue(value.label);
    const message = stringValue(value.message);

    if (!key || !label || !message) {
        return null;
    }

    return {
        key,
        label,
        message,
        tone: stringValue(value.tone) === 'warning' ? 'warning' : 'critical',
    };
}

function sanitizeCapability(
    capability: CockpitPayCodeExplorerReadModelRecord['capability'],
): CockpitPayCodeExplorerRecord['capability'] {
    const value = objectValue(capability);

    return {
        key: stringValue(value.key) ?? 'disbursement',
        label: stringValue(value.label) ?? 'Disbursement',
        voucherTypeLabel: stringValue(value.voucher_type_label) ?? 'Redeemable',
    };
}

function sanitizeInstructionBadges(
    badges: CockpitPayCodeExplorerReadModelRecord['instruction_badges'],
): CockpitPayCodeExplorerRecord['instructionBadges'] {
    if (!Array.isArray(badges)) {
        return [];
    }

    return badges
        .map((badge) => {
            const value = objectValue(badge);
            const key = stringValue(value.key);
            const label = stringValue(value.label);

            return key && label ? { key, label } : null;
        })
        .filter(
            (
                badge,
            ): badge is CockpitPayCodeExplorerRecord['instructionBadges'][number] =>
                badge !== null,
        );
}

function sanitizeParty(
    party: CockpitPayCodeExplorerReadModelRecord['party'],
): CockpitPayCodeExplorerRecord['party'] {
    const value = objectValue(party);

    return {
        state: stringValue(value.state) ?? 'open',
        label: stringValue(value.label) ?? 'Availability',
        primary: stringValue(value.primary) ?? 'Open claim',
        secondary: stringValue(value.secondary),
        masked: value.masked === true,
    };
}

function sanitizeTiming(
    record: CockpitPayCodeExplorerReadModelRecord,
): CockpitPayCodeExplorerRecord['timing'] {
    const timing = objectValue(record.timing);

    return {
        createdAt:
            stringValue(timing.created_at) ?? stringValue(record.created_at),
        startsAt: stringValue(timing.starts_at),
        expiresAt:
            stringValue(timing.expires_at) ?? stringValue(record.expires_at),
        redeemedAt: stringValue(timing.redeemed_at),
        terminalAt: stringValue(timing.terminal_at),
    };
}

function sanitizeTerminalControl(
    record: CockpitPayCodeExplorerReadModelRecord,
): CockpitPayCodeExplorerRecord['terminalControl'] {
    const control = objectValue(record.terminal_control);

    return {
        canExpire: control.can_expire === true,
        canCancel: control.can_cancel === true,
        blockedReason: stringValue(control.blocked_reason),
        status: stringValue(control.status) ?? 'blocked',
    };
}

function sanitizeRowAction(action: unknown): {
    key: string;
    label: string;
    enabled: boolean;
    disabled: boolean;
    read_only: boolean;
    href: string | null;
    reason: string | null;
} | null {
    const value = objectValue(action);
    const key = stringValue(value.key);
    const label = stringValue(value.label);

    if (!key || !label) {
        return null;
    }

    const enabled = value.enabled === true && stringValue(value.href) !== null;

    return {
        key,
        label,
        enabled,
        disabled: !enabled,
        read_only: value.read_only !== false,
        href: enabled ? stringValue(value.href) : null,
        reason: stringValue(value.reason),
    };
}

function appendCampaignContextToRowAction(
    action: ReturnType<typeof sanitizeRowAction>,
): ReturnType<typeof sanitizeRowAction> {
    if (
        action === null ||
        action.href === null ||
        campaignExplorerContextParams.value.length === 0
    ) {
        return action;
    }

    const [path, queryString = ''] = action.href.split('?');
    const params = new URLSearchParams(queryString);

    for (const field of campaignExplorerContextParams.value) {
        params.set(field.name, field.value);
    }

    return {
        ...action,
        href: `${path}?${params.toString()}`,
    };
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

function objectValue(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}

function numberValue(value: unknown): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : 0;
}

function moneyValue(value: unknown, currency: string | null = 'PHP'): string {
    if (
        typeof value === 'string' &&
        value.trim() !== '' &&
        Number.isNaN(Number(value))
    ) {
        return value.trim();
    }

    const amount = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(amount)) {
        return 'Pending';
    }

    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: currency ?? 'PHP',
    }).format(amount);
}

function integrationBadge(
    key: string,
    label: string,
    model: CockpitDependentReadModel | undefined,
): {
    key: string;
    label: string;
    status: string;
    policy: string;
} {
    return {
        key,
        label,
        status: stringValue(model?.status) ?? 'not_wired',
        policy: stringValue(model?.redactions?.payloads) ?? 'not-loaded',
    };
}
</script>

<template>
    <CockpitLayout
        active-navigation="pay-codes"
        mobile-presentation="edge"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <section
            class="mx-auto max-w-7xl space-y-5 px-4 md:px-0"
            data-testid="cockpit-pay-code-explorer-shell"
        >
            <section
                class="-mx-4 border-y border-slate-200 bg-white p-4 shadow-none md:mx-0 md:rounded-2xl md:border md:shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-explorer-command-card"
            >
                <div
                    class="hidden flex-col gap-3 md:flex xl:flex-row xl:items-center xl:justify-between"
                    data-testid="cockpit-pay-code-explorer-shell-header"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1
                                class="text-xl font-semibold tracking-tight text-slate-950 dark:text-slate-50"
                            >
                                Pay Code Explorer
                            </h1>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Read-only
                            </span>
                        </div>
                        <p
                            class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                        >
                            Search, inspect, and apply governed lifecycle
                            controls.
                        </p>
                    </div>
                    <a
                        :href="quickGenerateHref"
                        class="inline-flex h-9 w-fit items-center gap-2 rounded-full bg-emerald-700 px-4 text-xs font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                        data-testid="cockpit-pay-code-explorer-primary-quick-generate-link"
                    >
                        <TicketPlus aria-hidden="true" class="size-4" />
                        Create
                    </a>
                </div>

                <dl
                    class="flex flex-wrap items-center gap-x-4 gap-y-1 border-y border-slate-100 py-2 text-xs text-slate-600 md:mt-3 dark:border-slate-800 dark:text-slate-300"
                    data-testid="cockpit-pay-code-explorer-primary-summary"
                >
                    <div
                        v-for="item in primarySummaryItems"
                        :key="item.key"
                        class="flex min-w-0 items-baseline gap-1.5"
                        data-testid="cockpit-pay-code-explorer-primary-summary-item"
                    >
                        <dt
                            class="truncate font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ item.label }}
                        </dt>
                        <dd
                            class="font-mono font-semibold tabular-nums text-slate-950 dark:text-slate-50"
                        >
                            {{ item.value }}
                        </dd>
                        <p class="sr-only">
                            {{ item.helper }}
                        </p>
                    </div>
                </dl>

                <CockpitPayCodeSearchBar
                    :clear-href="campaignExplorerBaseHref"
                    :filters="readModel?.filters ?? []"
                    :hidden-fields="campaignExplorerContextParams"
                    :query="query"
                    :status-filter="statusFilter"
                />
            </section>

            <section
                v-if="campaignNavigationContext"
                class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 shadow-sm dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100"
                data-testid="cockpit-campaign-navigation-context"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-sky-700 ring-1 ring-sky-200 dark:bg-sky-950 dark:text-sky-200 dark:ring-sky-800"
                        >
                            <Megaphone aria-hidden="true" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold">Campaign filter active</p>
                            <p
                                class="truncate text-xs text-sky-800/80 dark:text-sky-200/80"
                            >
                                {{ campaignNavigationContext.planning_key }}
                                · read-only context
                            </p>
                        </div>
                    </div>
                    <a
                        :href="campaignDashboardHref"
                        class="inline-flex h-9 w-fit items-center gap-2 rounded-full border border-sky-300 bg-white px-4 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100 dark:hover:bg-sky-900"
                        data-testid="cockpit-campaign-navigation-dashboard-link"
                    >
                        <ArrowLeft aria-hidden="true" class="size-4" />
                        Return to campaign
                    </a>
                </div>
                <details
                    class="mt-3 border-t border-sky-200 pt-2 dark:border-sky-800"
                    data-testid="cockpit-campaign-navigation-context-details"
                >
                    <summary
                        class="cursor-pointer text-xs font-semibold text-sky-800 dark:text-sky-100"
                    >
                        Campaign filter details
                    </summary>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="item in campaignNavigationContextItems"
                            :key="item.key"
                            class="rounded-lg bg-white/80 px-3 py-2 dark:bg-sky-950/70"
                            data-testid="cockpit-campaign-navigation-context-item"
                        >
                            <dt
                                class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300"
                            >
                                {{ item.label }}
                            </dt>
                            <dd class="mt-1 break-words font-semibold">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </section>

            <section
                v-else-if="activityNavigationContext"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                data-testid="cockpit-activity-navigation-context"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
                    >
                        <Activity aria-hidden="true" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold">
                            Opened from issuance activity
                        </p>
                        <p
                            class="truncate font-mono text-xs text-emerald-800/80 dark:text-emerald-200/80"
                        >
                            {{ activityNavigationContext.code }}
                        </p>
                    </div>
                </div>
                <details
                    class="mt-3 border-t border-emerald-200 pt-2 dark:border-emerald-800"
                    data-testid="cockpit-activity-navigation-context-details"
                >
                    <summary
                        class="cursor-pointer text-xs font-semibold text-emerald-800 dark:text-emerald-100"
                    >
                        Activity context details
                    </summary>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="item in [
                                {
                                    label: 'Pay Code',
                                    value: activityNavigationContext.code,
                                },
                                {
                                    label: 'Source',
                                    value: activityNavigationContext.source,
                                },
                                {
                                    label: 'Destination',
                                    value: activityNavigationContext.destination,
                                },
                                {
                                    label: 'Payload Policy',
                                    value: activityNavigationContext.redactions
                                        ?.payloads,
                                },
                            ]"
                            :key="item.label"
                            class="rounded-lg bg-white/80 px-3 py-2 dark:bg-emerald-950/70"
                        >
                            <dt
                                class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300"
                            >
                                {{ item.label }}
                            </dt>
                            <dd class="mt-1 break-words font-semibold">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </details>
            </section>

            <CockpitPayCodeResultsTable
                :records="records"
                :actions="cockpitPayCodeRowActions"
            />

            <details
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-page-details-disclosure"
            >
                <summary class="cursor-pointer list-none">
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Explorer details
                            </p>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Filter metadata and read-model boundaries.
                            </p>
                        </div>
                        <span
                            class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            Details
                        </span>
                    </div>
                </summary>
                <p
                    class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                >
                    The main scan path stays focused on search and results. This
                    Explorer exposes only the existing governed expire and
                    cancel controls. It does not execute drivers, approve
                    claims, send feedback, write journal entries directly, call
                    providers, or move money itself.
                </p>

                <div class="mt-3 grid gap-3">
                    <details
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                        data-testid="cockpit-pay-code-explorer-current-search-disclosure"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200"
                        >
                            Current search and read model
                        </summary>
                        <dl
                            class="mt-3 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4"
                            data-testid="cockpit-pay-code-explorer-current-search"
                        >
                            <div
                                v-for="item in currentSearchItems"
                                :key="item.key"
                                class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                                data-testid="cockpit-pay-code-explorer-current-search-item"
                            >
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.label }}
                                </dt>
                                <dd
                                    class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ item.value }}
                                </dd>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.helper }}
                                </p>
                            </div>
                        </dl>
                        <dl
                            class="mt-3 grid w-full gap-2 text-sm sm:grid-cols-3 xl:w-[32rem]"
                            data-testid="cockpit-pay-code-explorer-shell-facts"
                        >
                            <div
                                class="rounded-full bg-slate-100 px-3 py-2 dark:bg-slate-900"
                            >
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Read model
                                </dt>
                                <dd
                                    class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ status }}
                                </dd>
                            </div>
                            <div
                                class="rounded-full bg-slate-100 px-3 py-2 dark:bg-slate-900"
                            >
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Records
                                </dt>
                                <dd
                                    class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ records.length }}
                                </dd>
                            </div>
                            <div
                                class="rounded-full bg-slate-100 px-3 py-2 dark:bg-slate-900"
                            >
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Payload policy
                                </dt>
                                <dd
                                    class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ payloadPolicy }}
                                </dd>
                            </div>
                        </dl>
                        <p
                            class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            Search and filters only change the current list
                            view; filtering does not mutate vouchers. Terminal
                            row actions require an explicit reason and
                            confirmation and remain subject to the established
                            lifecycle authority.
                        </p>
                    </details>
                    <details
                        class="rounded-xl border border-emerald-200 bg-white p-3 dark:border-emerald-900/70 dark:bg-slate-950"
                        data-testid="cockpit-pay-code-row-action-guidance"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-emerald-800 dark:text-emerald-200"
                        >
                            Row action guidance
                        </summary>
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300"
                                >
                                    Row action guidance
                                </p>
                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                                >
                                    Row actions open inspection workspaces or
                                    the established governed terminal form.
                                    Unavailable actions remain disabled with an
                                    authoritative reason; no provider is called
                                    from a list row.
                                </p>
                            </div>
                            <span
                                class="w-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200"
                            >
                                Governed controls
                            </span>
                        </div>
                        <dl class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                            <div
                                v-for="item in rowActionGuidance"
                                :key="item.key"
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                                data-testid="cockpit-pay-code-row-action-guidance-item"
                            >
                                <dt
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.label }}
                                </dt>
                                <dd
                                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ item.value }}
                                </dd>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ item.helper }}
                                </p>
                            </div>
                        </dl>
                    </details>

                    <details
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                        data-testid="cockpit-pay-code-stats-summary"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200"
                        >
                            List totals
                        </summary>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400"
                        >
                            Read-only totals
                        </p>
                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div
                                class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900"
                            >
                                <p
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Filtered
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ stats.filtered }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900"
                            >
                                <p
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Total
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ stats.total }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900"
                            >
                                <p
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Active
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ stats.active }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900"
                            >
                                <p
                                    class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                >
                                    Needs attention
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        stats.awaitingApproval +
                                        stats.pending +
                                        stats.failed +
                                        stats.expired
                                    }}
                                </p>
                            </div>
                        </div>
                    </details>

                    <details
                        v-if="canViewTechnicalDetails"
                        class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                        data-testid="cockpit-pay-code-technical-details"
                    >
                        <summary
                            class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200"
                        >
                            Technical details
                        </summary>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400"
                        >
                            Connected services
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="badge in integrationBadges"
                                :key="badge.key"
                                class="rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                data-testid="cockpit-pay-code-integration-badge"
                            >
                                {{ badge.label }}: {{ badge.status }}
                                <span class="ml-1 font-normal opacity-70">{{
                                    badge.policy
                                }}</span>
                            </span>
                        </div>
                    </details>

                    <CockpitPayCodeFilterBuilder :filters="filters" />
                </div>
            </details>
        </section>
    </CockpitLayout>
</template>
