<script setup lang="ts">
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
const query = computed(() => stringValue(readModel.value?.query) ?? '');
const statusFilter = computed(() => stringValue(readModel.value?.status_filter) ?? null);
const payloadPolicy = computed(() => stringValue(readModel.value?.redactions?.payloads) ?? 'not-loaded');
const status = computed(() => stringValue(readModel.value?.status) ?? 'not_wired');
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
const attentionCount = computed(() => stats.value.awaitingApproval + stats.value.pending + stats.value.failed + stats.value.expired);
const quickGenerateHref = computed(() => '/x/cockpit/quick-generate');
const primarySummaryItems = computed(() => [
    {
        key: 'filtered',
        label: 'Visible',
        value: String(stats.value.filtered || records.value.length),
        helper: 'Sanitized rows matching the current read model.',
    },
    {
        key: 'total',
        label: 'Total',
        value: String(stats.value.total || records.value.length),
        helper: 'Total available from the read-model summary.',
    },
    {
        key: 'attention',
        label: 'Needs Attention',
        value: String(attentionCount.value),
        helper: 'Expired, pending, failed, or awaiting approval summaries.',
    },
    {
        key: 'payload-policy',
        label: 'Payload Policy',
        value: payloadPolicy.value,
        helper: 'List rows are sanitized before display.',
    },
]);
const currentSearchItems = computed(() => [
    {
        key: 'search',
        label: 'Search',
        value: query.value || 'All Pay Codes',
        helper: query.value ? 'Search term applied to the current read-only list.' : 'No search term is applied.',
    },
    {
        key: 'status',
        label: 'Status',
        value: statusFilter.value ?? 'All statuses',
        helper: statusFilter.value ? 'Status filter applied to the current read-only list.' : 'No lifecycle status filter is applied.',
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
const enabledRowActionCount = computed(() => records.value.reduce((count, record) => (
    count + (record.actions ?? []).filter((action) => action.enabled && action.href !== null).length
), 0));
const disabledRowActionCount = computed(() => records.value.reduce((count, record) => (
    count + (record.actions ?? []).filter((action) => action.disabled).length
), 0));
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
const campaignNavigationContext = computed<CockpitCampaignNavigationContext | null>(() => {
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
            status: stringValue(objectValue(context.mutation).status) ?? 'blocked',
            reason: stringValue(objectValue(context.mutation).reason) ?? 'campaign-navigation-read-only',
        },
        redactions: {
            payloads: stringValue(context.redactions?.payloads) ?? 'navigation-context-only',
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
        return '/x/cockpit';
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

    return `/x/cockpit?${params.toString()}`;
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
        fields.push({ name: 'campaign_audience_id', value: context.audience_id });
    }

    if (context.recipient_id) {
        fields.push({ name: 'campaign_recipient_id', value: context.recipient_id });
    }

    return fields.filter((field) => field.value.trim() !== '');
});
const campaignExplorerBaseHref = computed(() => {
    const params = new URLSearchParams();

    for (const field of campaignExplorerContextParams.value) {
        params.set(field.name, field.value);
    }

    const queryString = params.toString();

    return queryString === '' ? '/x/cockpit/pay-codes' : `/x/cockpit/pay-codes?${queryString}`;
});
const activityNavigationContext = computed<CockpitActivityNavigationContext | null>(() => {
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
        schema: stringValue(context.schema) ?? 'x-change.cockpit.activity-navigation.v1',
        status: stringValue(context.status) ?? 'available',
        authorized: true,
        source: stringValue(context.source) ?? 'operator_issuance_activity',
        code,
        destination,
        read_only: true,
        mutation: {
            enabled: false,
            status: stringValue(objectValue(context.mutation).status) ?? 'blocked',
            reason: stringValue(objectValue(context.mutation).reason) ?? 'activity-navigation-read-only',
        },
        redactions: {
            payloads: stringValue(context.redactions?.payloads) ?? 'activity-navigation-context-only',
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
            helper: filter.read_only === false ? 'Unexpected writable filter metadata.' : 'Read-only GET filter metadata.',
        })),
    ];
});

const records = computed<CockpitPayCodeExplorerRecord[]>(() => {
    if (!isHydrated.value) {
        return cockpitPayCodeExplorerRecords;
    }

    return (readModel.value?.records ?? [])
        .map((record) => sanitizeRecord(record))
        .filter((record): record is CockpitPayCodeExplorerRecord => record !== null);
});

const integrationBadges = computed(() => [
    integrationBadge('journal', 'Journal', props.read_model?.journal),
    integrationBadge('actions', 'Actions', props.read_model?.actions),
    integrationBadge('feedback', 'Feedback', props.read_model?.feedback),
]);
const integrationReadinessCards = computed(() => integrationBadges.value.map((badge) => ({
    ...badge,
    helper: badge.key === 'journal'
        ? 'Journal evidence remains read-only audit context.'
        : badge.key === 'actions'
          ? 'Action CTAs are presentation-only unless explicitly authorized elsewhere.'
          : 'Feedback delivery state is communication status, not lifecycle truth.',
})));

function sanitizeRecord(record: CockpitPayCodeExplorerReadModelRecord): CockpitPayCodeExplorerRecord | null {
    const code = stringValue(record.code);

    if (code === null) {
        return null;
    }

    return {
        code,
        template: stringValue(record.template) ?? 'Template pending',
        amount: moneyValue(record.amount, stringValue(record.currency)),
        status: stringValue(record.display_status) ?? stringValue(record.status) ?? 'not_wired',
        owner: stringValue(record.owner) ?? 'Redacted',
        lastActivity: stringValue(record.last_activity) ?? 'Read model activity pending',
        actions: Array.isArray(record.actions)
            ? record.actions
                .map((action) => sanitizeRowAction(action))
                .map((action) => appendCampaignContextToRowAction(action))
                .filter((action): action is NonNullable<ReturnType<typeof sanitizeRowAction>> => action !== null)
            : [],
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

function appendCampaignContextToRowAction(action: ReturnType<typeof sanitizeRowAction>): ReturnType<typeof sanitizeRowAction> {
    if (action === null || action.href === null || campaignExplorerContextParams.value.length === 0) {
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
    return value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {};
}

function numberValue(value: unknown): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : 0;
}

function moneyValue(value: unknown, currency: string | null = 'PHP'): string {
    if (typeof value === 'string' && value.trim() !== '' && Number.isNaN(Number(value))) {
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
    <CockpitLayout active-navigation="pay-codes">
        <section class="space-y-6" data-testid="cockpit-pay-code-explorer-shell">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    Pay Code operations
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Pay Code Explorer
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Find and inspect Pay Codes using sanitized list facts. This
                    screen remains read-only: it does not mutate vouchers,
                    execute drivers, approve claims, send feedback, write
                    journal entries, call providers, or move money.
                </p>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Read model
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ status }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Records
                        </dt>
                        <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                            {{ records.length }}
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
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
                data-testid="cockpit-pay-code-explorer-primary-summary"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Operator list summary
                        </p>
                        <h3 class="mt-2 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Pay Code Explorer
                        </h3>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            This page helps operators find Pay Codes using sanitized list facts. It can navigate to detail and distribution workspaces, but it does not mutate vouchers, execute drivers, send feedback, write journal entries, call providers, or move money.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-slate-950 dark:text-emerald-200 dark:ring-emerald-800">
                        read-only
                    </span>
                </div>

                <dl class="mt-5 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="item in primarySummaryItems"
                        :key="item.key"
                        class="rounded-xl bg-white/80 p-4 dark:bg-slate-950/70"
                        data-testid="cockpit-pay-code-explorer-primary-summary-item"
                    >
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ item.label }}
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ item.value }}
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            {{ item.helper }}
                        </p>
                    </div>
                </dl>

                <div class="mt-5 rounded-xl border border-emerald-200 bg-white/80 p-4 dark:border-emerald-900/60 dark:bg-slate-950/70">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        Current Search
                    </p>
                    <dl
                        class="mt-3 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4"
                        data-testid="cockpit-pay-code-explorer-current-search"
                    >
                        <div
                            v-for="item in currentSearchItems"
                            :key="item.key"
                            class="rounded-lg border border-emerald-100 bg-emerald-50/70 p-3 dark:border-emerald-900/70 dark:bg-emerald-950/30"
                            data-testid="cockpit-pay-code-explorer-current-search-item"
                        >
                            <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                                {{ item.label }}
                            </dt>
                            <dd class="mt-1 font-semibold text-slate-950 dark:text-slate-50">
                                {{ item.value }}
                            </dd>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                {{ item.helper }}
                            </p>
                        </div>
                    </dl>
                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        Search and filters only change the current list view. Row actions remain navigation-only unless a future authorized mutation slice explicitly changes that boundary.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a
                            :href="quickGenerateHref"
                            class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-800"
                            data-testid="cockpit-pay-code-explorer-primary-quick-generate-link"
                        >
                            Quick Generate
                        </a>
                        <a
                            :href="campaignExplorerBaseHref"
                            class="inline-flex items-center rounded-full border border-emerald-300 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-200 dark:hover:bg-emerald-900/50"
                            data-testid="cockpit-pay-code-explorer-primary-clear-link"
                        >
                            Clear filters
                        </a>
                    </div>
                </div>
            </section>

            <section
                v-if="campaignNavigationContext"
                class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950 shadow-sm dark:border-sky-900 dark:bg-sky-950/70 dark:text-sky-100"
                data-testid="cockpit-campaign-navigation-context"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-300">
                            Campaign Explorer Context
                        </p>
                        <h3 class="mt-2 text-xl font-semibold">
                            Campaign-aware Pay Code view
                        </h3>
                        <p class="mt-2 max-w-3xl leading-6 text-sky-900/80 dark:text-sky-100/80">
                            This context came from Campaign Cockpit navigation and is used only to orient the Explorer. It does not dispatch campaigns, issue more Pay Codes, send feedback, write journal entries, call providers, or move money.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200 dark:bg-sky-900 dark:text-sky-100 dark:ring-sky-800">
                        Read-only filter
                    </span>
                </div>

                <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="item in campaignNavigationContextItems"
                        :key="item.key"
                        class="rounded-xl bg-white/80 p-4 dark:bg-sky-900/50"
                        data-testid="cockpit-campaign-navigation-context-item"
                    >
                        <dt class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                            {{ item.label }}
                        </dt>
                        <dd class="mt-1 break-words font-semibold">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-col gap-3 rounded-xl border border-sky-200 bg-white/70 p-4 dark:border-sky-800 dark:bg-sky-900/40 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold">
                            Campaign changes are disabled here
                        </p>
                        <p class="mt-1 leading-6 text-sky-900/80 dark:text-sky-100/80">
                            {{ campaignNavigationContext.mutation?.reason }}
                        </p>
                    </div>
                    <a
                        :href="campaignDashboardHref"
                        class="inline-flex w-fit items-center rounded-full border border-sky-300 bg-white px-4 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100 dark:hover:bg-sky-900"
                        data-testid="cockpit-campaign-navigation-dashboard-link"
                    >
                        Return to Cockpit campaign view
                    </a>
                </div>
            </section>

            <details
                class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-900/70 dark:bg-slate-900"
                data-testid="cockpit-pay-code-row-action-guidance"
            >
                <summary class="cursor-pointer text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                    Row action guidance
                </summary>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Row action guidance
                        </p>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Row actions open inspection workspaces or remain disabled when the destination is not authorized. This page does not execute actions, deliver feedback, mutate vouchers, or call providers from a list row.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                        Links only
                    </span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                    <div
                        v-for="item in rowActionGuidance"
                        :key="item.key"
                        class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"
                        data-testid="cockpit-pay-code-row-action-guidance-item"
                    >
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ item.label }}
                        </dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ item.value }}
                        </dd>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            {{ item.helper }}
                        </p>
                    </div>
                </dl>
            </details>

            <details
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-stats-summary"
            >
                <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200">
                    List totals
                </summary>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Read-only totals
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Filtered</p>
                        <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">{{ stats.filtered }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</p>
                        <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">{{ stats.total }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Active</p>
                        <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">{{ stats.active }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Needs attention</p>
                        <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            {{ stats.awaitingApproval + stats.pending + stats.failed + stats.expired }}
                        </p>
                    </div>
                </div>
            </details>

            <details
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-integration-badges"
            >
                <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200">
                    Connected service badges
                </summary>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
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
                        <span class="ml-1 font-normal opacity-70">{{ badge.policy }}</span>
                    </span>
                </div>
            </details>

            <details
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-integration-readiness"
            >
                <summary class="cursor-pointer text-sm font-semibold text-slate-800 dark:text-slate-200">
                    Connected service details
                </summary>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Connected service readiness
                </p>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    These cards summarize read-only integration context for the list. They do not write journal entries, execute actions, send feedback, or change voucher lifecycle state.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <article
                        v-for="card in integrationReadinessCards"
                        :key="card.key"
                        class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                        data-testid="cockpit-pay-code-integration-readiness-card"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold text-slate-950 dark:text-slate-50">
                                {{ card.label }}
                            </h3>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ card.status }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ card.policy }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            {{ card.helper }}
                        </p>
                    </article>
                </div>
            </details>

            <div
                v-if="activityNavigationContext"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                data-testid="cockpit-activity-navigation-context"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                    Activity navigation context
                </p>
                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            Pay Code
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ activityNavigationContext.code }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            Source
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ activityNavigationContext.source }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            Destination
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ activityNavigationContext.destination }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                            Payload policy
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ activityNavigationContext.redactions?.payloads }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 rounded-lg border border-emerald-200 bg-white/60 px-3 py-3 dark:border-emerald-800 dark:bg-emerald-900/40">
                    <p class="font-semibold">
                        Mutation blocked
                    </p>
                    <p class="mt-1">
                        {{ activityNavigationContext.mutation?.reason }}
                    </p>
                </div>
            </div>

            <CockpitPayCodeSearchBar
                :clear-href="campaignExplorerBaseHref"
                :filters="readModel?.filters ?? []"
                :hidden-fields="campaignExplorerContextParams"
                :query="query"
                :status-filter="statusFilter"
            />
            <CockpitPayCodeFilterBuilder :filters="filters" />
            <CockpitPayCodeResultsTable
                :records="records"
                :actions="cockpitPayCodeRowActions"
            />
        </section>
    </CockpitLayout>
</template>
