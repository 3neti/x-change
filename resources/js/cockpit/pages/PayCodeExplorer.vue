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
const enabledRowActionCount = computed(() => records.value.reduce((count, record) => (
    count + record.actions.filter((action) => action.enabled && action.href !== null).length
), 0));
const disabledRowActionCount = computed(() => records.value.reduce((count, record) => (
    count + record.actions.filter((action) => action.disabled).length
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
                    Wave 4 · Slice 13
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    Pay Code Explorer Foundation
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    Pay Code Explorer Read Model hydration renders sanitized list facts when supplied.
                    This screen remains read-only: it does not mutate vouchers, execute drivers, approve
                    claims, send feedback, write journal entries, call providers, or move money.
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
                        Current view
                    </p>
                    <p class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50">
                        Query: {{ query || 'all Pay Codes' }} · Status: {{ statusFilter ?? 'all statuses' }}
                    </p>
                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        Search and filter controls use read-only GET navigation. Row actions are navigation-only unless a future authorized mutation slice explicitly changes that boundary.
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
                            href="/x/cockpit/pay-codes"
                            class="inline-flex items-center rounded-full border border-emerald-300 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-200 dark:hover:bg-emerald-900/50"
                            data-testid="cockpit-pay-code-explorer-primary-clear-link"
                        >
                            Clear filters
                        </a>
                    </div>
                </div>
            </section>

            <section
                class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-900/70 dark:bg-slate-900"
                data-testid="cockpit-pay-code-row-action-guidance"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700 dark:text-emerald-300">
                            Row action guidance
                        </p>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Row actions are safe navigation or disabled placeholders. This page does not execute actions, deliver feedback, mutate vouchers, or call providers from a list row.
                        </p>
                    </div>
                    <span class="w-fit rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                        navigation-only
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
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-stats-summary"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Functional parity summary
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
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-pay-code-integration-badges"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Integration badges
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
            </section>

            <div
                v-if="campaignNavigationContext"
                class="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-950 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100"
                data-testid="cockpit-campaign-navigation-context"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-300">
                    Campaign navigation context
                </p>
                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                            Planning key
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ campaignNavigationContext.planning_key }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                            Execution id
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ campaignNavigationContext.execution_id }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                            Destination
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ campaignNavigationContext.destination }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">
                            Payload policy
                        </p>
                        <p class="mt-1 font-semibold">
                            {{ campaignNavigationContext.redactions?.payloads }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 rounded-lg border border-sky-200 bg-white/60 px-3 py-3 dark:border-sky-800 dark:bg-sky-900/40">
                    <p class="font-semibold">
                        Mutation blocked
                    </p>
                    <p class="mt-1">
                        {{ campaignNavigationContext.mutation?.reason }}
                    </p>
                </div>
            </div>

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
                :filters="readModel?.filters ?? []"
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
