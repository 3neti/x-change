<script setup lang="ts">
import { computed } from 'vue';
import type {
    CockpitOperatorIssuanceActivityPresentation,
    CockpitOperatorIssuanceActivityReadModel,
} from '../types';

const props = defineProps<{
    readModel?: CockpitOperatorIssuanceActivityReadModel;
}>();

type SafeJournalDiagnostic = {
    label: string;
    tone?: string;
    description?: string;
    operatorAction?: string;
    readOnly: boolean;
};

type SafeJournalSummary = {
    journalEntryId?: string;
    writesJournal: boolean;
    source?: string;
    reason?: string;
    referenceNumber?: string;
    eventType?: string;
    diagnostic?: SafeJournalDiagnostic;
};

type SafeActionSummary = {
    actionHintId?: string;
    actionRunId?: string;
    executesAction: boolean;
    source?: string;
    reason?: string;
    suggestedAction?: string;
};

type SafeFeedbackSummary = {
    feedbackIntentId?: string;
    deliveryPlanId?: string;
    deliveryReceiptId?: string;
    sendsFeedback: boolean;
    source?: string;
    reason?: string;
    channel?: string;
    plannedDeliveries?: string;
};

type SafeCampaignAttribution = {
    status?: string;
    planningKey?: string;
    executionId?: string;
    campaignId?: string;
    audienceId?: string;
    recipientId?: string;
    source?: string;
    generatedCode?: string;
    templateKey?: string;
    amount?: string;
    currency?: string;
    recipientReference?: string;
    purpose?: string;
    readOnly: boolean;
    mutatesCampaign: boolean;
};

type SafePresentation = {
    id: string;
    title: string;
    subtitle: string;
    status: string;
    href?: string;
    distributionHref?: string;
    explorerHref?: string;
    campaignDashboardHref?: string;
    correlationId?: string;
    journalSummary?: SafeJournalSummary;
    actionSummary?: SafeActionSummary;
    feedbackSummary?: SafeFeedbackSummary;
    campaignAttribution?: SafeCampaignAttribution;
    handoffs: {
        journal: string;
        action: string;
        feedback: string;
    };
};

type SafeSearchFilters = {
    status: string;
    search?: string;
    statuses: string[];
    handoffStatuses: string[];
    availableStatuses: string[];
    availableHandoffStatuses: string[];
    readOnly: boolean;
};

type FilterClearLink = {
    key: string;
    label: string;
    href: string;
};

const presentations = computed<SafePresentation[]>(() => {
    if (!props.readModel?.authorized || !Array.isArray(props.readModel.presentations)) {
        return [];
    }

    return props.readModel.presentations
        .map((presentation) => sanitizePresentation(presentation))
        .filter((presentation): presentation is SafePresentation => presentation !== null);
});

const searchFilters = computed<SafeSearchFilters>(() => {
    const filters = isPlainObject(props.readModel?.search_filters) ? props.readModel.search_filters : {};

    return {
        status: stringValue(filters.status) ?? 'not_available',
        search: stringValue(filters.search),
        statuses: stringList(filters.statuses),
        handoffStatuses: stringList(filters.handoff_statuses),
        availableStatuses: stringList(filters.available_statuses),
        availableHandoffStatuses: stringList(filters.available_handoff_statuses),
        readOnly: filters.read_only === true,
    };
});

const canFilter = computed(() => props.readModel?.authorized === true && searchFilters.value.status === 'available');
const activeFilterCount = computed(() => [
    searchFilters.value.search,
    ...searchFilters.value.statuses,
    ...searchFilters.value.handoffStatuses,
].filter((value) => value !== undefined && value !== '').length);
const activeFilterLabels = computed(() => [
    searchFilters.value.search ? `search “${searchFilters.value.search}”` : undefined,
    ...searchFilters.value.statuses.map((status) => `status ${status}`),
    ...searchFilters.value.handoffStatuses.map((status) => `handoff ${status}`),
].filter((value): value is string => value !== undefined));
const activeFilterSummary = computed(() => {
    if (!canFilter.value) {
        return 'Filter summary unavailable until durable activity storage is wired.';
    }

    if (activeFilterLabels.value.length === 0) {
        return 'No activity filters applied.';
    }

    return `Filters: ${activeFilterLabels.value.join(' · ')}`;
});
const filterClearLinks = computed<FilterClearLink[]>(() => {
    if (!canFilter.value) {
        return [];
    }

    return [
        searchFilters.value.search ? {
            key: 'search',
            label: 'Clear search',
            href: filterHrefWithout('search'),
        } : undefined,
        searchFilters.value.statuses.length > 0 ? {
            key: 'status',
            label: 'Clear status',
            href: filterHrefWithout('status'),
        } : undefined,
        searchFilters.value.handoffStatuses.length > 0 ? {
            key: 'handoff',
            label: 'Clear handoff',
            href: filterHrefWithout('handoff'),
        } : undefined,
    ].filter((link): link is FilterClearLink => link !== undefined);
});
const activityResultSummary = computed(() => {
    const count = presentations.value.length;
    const noun = count === 1 ? 'activity' : 'activities';

    if (!canFilter.value) {
        return 'Activity filters become available when durable activity storage is wired.';
    }

    if (activeFilterCount.value > 0) {
        return `Showing ${count} matching ${noun} for the current read-only filters.`;
    }

    return `Showing ${count} recent ${noun}.`;
});
const emptyTitle = computed(() => stringValue(props.readModel?.empty_state?.title) ?? 'No operator issuance activity available');
const emptyDescription = computed(() => (
    stringValue(props.readModel?.empty_state?.description)
    ?? 'Activity recording is not wired yet. Quick Generate can still use the existing issuance path.'
));
const visibleEmptyTitle = computed(() => {
    if (canFilter.value && activeFilterCount.value > 0 && presentations.value.length === 0) {
        return 'No activity matches current filters';
    }

    return emptyTitle.value;
});
const visibleEmptyDescription = computed(() => {
    if (canFilter.value && activeFilterCount.value > 0 && presentations.value.length === 0) {
        return 'Clear filters or adjust the search/status criteria to inspect durable operator issuance activity.';
    }

    return emptyDescription.value;
});

function sanitizePresentation(presentation: CockpitOperatorIssuanceActivityPresentation): SafePresentation | null {
    const id = stringValue(presentation.id);
    const title = stringValue(presentation.title);
    const subtitle = stringValue(presentation.subtitle);
    const status = stringValue(presentation.status);

    if (!id || !title || !subtitle || !status) {
        return null;
    }

    const safePresentation: SafePresentation = {
        id,
        title,
        subtitle,
        status,
        correlationId: stringValue(presentation.correlation_id),
        journalSummary: safeJournalSummary(presentation.metadata?.journal_handoff),
        actionSummary: safeActionSummary(presentation.metadata?.action_handoff),
        feedbackSummary: safeFeedbackSummary(presentation.metadata?.feedback_handoff),
        campaignAttribution: safeCampaignAttribution(presentation.metadata?.campaign_attribution),
        handoffs: {
            journal: stringValue(presentation.handoffs?.journal) ?? 'not_wired',
            action: stringValue(presentation.handoffs?.action) ?? 'not_wired',
            feedback: stringValue(presentation.handoffs?.feedback) ?? 'not_wired',
        },
    };

    const detailHref = safeDetailHref(presentation.detail_href);

    safePresentation.href = safeDetailContextHref(detailHref, safePresentation.campaignAttribution);
    safePresentation.distributionHref = safeDistributionHref(detailHref, safePresentation.campaignAttribution);
    safePresentation.explorerHref = safeExplorerHref(detailHref, safePresentation.campaignAttribution);
    safePresentation.campaignDashboardHref = safeCampaignDashboardHref(safePresentation.campaignAttribution);

    return safePresentation;
}

function safeCampaignAttribution(value: unknown): SafeCampaignAttribution | undefined {
    if (!isPlainObject(value)) {
        return undefined;
    }

    const status = stringValue(value.status);
    const planningKey = stringValue(value.planning_key);
    const executionId = stringValue(value.execution_id);
    const campaignId = stringValue(value.campaign_id);
    const audienceId = stringValue(value.audience_id);
    const recipientId = stringValue(value.recipient_id);
    const source = stringValue(value.source);
    const generatedCode = stringValue(value.generated_code);
    const templateKey = stringValue(value.template_key);
    const amount = stringValue(value.amount);
    const currency = stringValue(value.currency);
    const recipientReference = stringValue(value.recipient_reference);
    const purpose = stringValue(value.purpose);

    if (
        !status
        && !planningKey
        && !executionId
        && !campaignId
        && !audienceId
        && !recipientId
        && !source
        && !generatedCode
        && !templateKey
        && !amount
        && !currency
        && !recipientReference
        && !purpose
    ) {
        return undefined;
    }

    return {
        status,
        planningKey,
        executionId,
        campaignId,
        audienceId,
        recipientId,
        source,
        generatedCode,
        templateKey,
        amount,
        currency,
        recipientReference,
        purpose,
        readOnly: value.read_only === true,
        mutatesCampaign: value.mutates_campaign === true,
    };
}

function safeFeedbackSummary(value: unknown): SafeFeedbackSummary | undefined {
    if (!isPlainObject(value)) {
        return undefined;
    }

    const feedbackIntentId = stringValue(value.feedback_intent_id);
    const deliveryPlanId = stringValue(value.delivery_plan_id);
    const deliveryReceiptId = stringValue(value.delivery_receipt_id);
    const source = stringValue(value.source);
    const reason = stringValue(value.reason);
    const metadata = isPlainObject(value.metadata) ? value.metadata : {};
    const channels = Array.isArray(metadata.channels) ? metadata.channels : [];
    const channel = stringValue(channels[0]);
    const plannedDeliveries = stringValue(metadata.planned_deliveries);

    if (!feedbackIntentId && !deliveryPlanId && !deliveryReceiptId && !source && !reason && !channel && !plannedDeliveries) {
        return undefined;
    }

    return {
        feedbackIntentId,
        deliveryPlanId,
        deliveryReceiptId,
        sendsFeedback: value.sends_feedback === true,
        source,
        reason,
        channel,
        plannedDeliveries,
    };
}

function safeActionSummary(value: unknown): SafeActionSummary | undefined {
    if (!isPlainObject(value)) {
        return undefined;
    }

    const actionHintId = stringValue(value.action_hint_id);
    const actionRunId = stringValue(value.action_run_id);
    const source = stringValue(value.source);
    const reason = stringValue(value.reason);
    const metadata = isPlainObject(value.metadata) ? value.metadata : {};
    const actions = Array.isArray(metadata.actions) ? metadata.actions : [];
    const firstAction = actions.find((action): action is Record<string, unknown> => isPlainObject(action));
    const suggestedAction = stringValue(firstAction?.label);

    if (!actionHintId && !actionRunId && !source && !reason && !suggestedAction) {
        return undefined;
    }

    return {
        actionHintId,
        actionRunId,
        executesAction: value.executes_action === true,
        source,
        reason,
        suggestedAction,
    };
}

function safeJournalSummary(value: unknown): SafeJournalSummary | undefined {
    if (!isPlainObject(value)) {
        return undefined;
    }

    const journalEntryId = stringValue(value.journal_entry_id);
    const source = stringValue(value.source);
    const reason = stringValue(value.reason);
    const metadata = isPlainObject(value.metadata) ? value.metadata : {};
    const referenceNumber = stringValue(metadata.reference_number);
    const eventType = stringValue(metadata.event_type);
    const diagnostic = safeJournalDiagnostic(value.diagnostic);

    if (!journalEntryId && !source && !reason && !referenceNumber && !eventType && !diagnostic) {
        return undefined;
    }

    return {
        journalEntryId,
        writesJournal: value.writes_journal === true,
        source,
        reason,
        referenceNumber,
        eventType,
        diagnostic,
    };
}

function safeJournalDiagnostic(value: unknown): SafeJournalDiagnostic | undefined {
    if (!isPlainObject(value)) {
        return undefined;
    }

    const label = stringValue(value.label);

    if (!label) {
        return undefined;
    }

    return {
        label,
        tone: stringValue(value.tone),
        description: stringValue(value.description),
        operatorAction: stringValue(value.operator_action),
        readOnly: value.read_only === true && value.retry_enabled !== true && value.mutation_enabled !== true && value.raw_payloads_exposed !== true,
    };
}

function safeDetailHref(value: unknown): string | undefined {
    const href = stringValue(value);

    if (!href?.startsWith('/x/cockpit/pay-codes/')) {
        return undefined;
    }

    return href;
}

function safeDetailContextHref(href: string | undefined, campaignAttribution?: SafeCampaignAttribution): string | undefined {
    if (!href) {
        return undefined;
    }

    const params = new URLSearchParams();
    appendCampaignAttributionParams(params, campaignAttribution);

    const query = params.toString();

    return query === '' ? href : `${href}?${query}`;
}

function safeDistributionHref(href: string | undefined, campaignAttribution?: SafeCampaignAttribution): string | undefined {
    if (!href) {
        return undefined;
    }

    const code = href.replace('/x/cockpit/pay-codes/', '').split('/')[0];

    if (!code) {
        return undefined;
    }

    const params = new URLSearchParams();
    appendCampaignAttributionParams(params, campaignAttribution);

    const query = params.toString();
    const distributionHref = `/x/cockpit/pay-codes/${code}/distribution`;

    return query === '' ? distributionHref : `${distributionHref}?${query}`;
}

function safeExplorerHref(href: string | undefined, campaignAttribution?: SafeCampaignAttribution): string | undefined {
    if (!href) {
        return undefined;
    }

    const code = href.replace('/x/cockpit/pay-codes/', '').split('/')[0];

    if (!code) {
        return undefined;
    }

    const params = new URLSearchParams({
        activity_code: code,
        activity_source: 'operator_issuance_activity',
    });

    appendCampaignAttributionParams(params, campaignAttribution);

    return `/x/cockpit/pay-codes?${params.toString()}`;
}

function safeCampaignDashboardHref(campaignAttribution?: SafeCampaignAttribution): string | undefined {
    if (!campaignAttribution) {
        return undefined;
    }

    const params = new URLSearchParams();
    appendCampaignAttributionParams(params, campaignAttribution);

    const query = params.toString();

    return query === '' ? undefined : `/x/cockpit?${query}`;
}

function appendCampaignAttributionParams(params: URLSearchParams, campaignAttribution?: SafeCampaignAttribution): void {
    if (!campaignAttribution || !campaignAttribution.readOnly || campaignAttribution.mutatesCampaign) {
        return;
    }

    setParam(params, 'campaign_planning_key', campaignAttribution.planningKey);
    setParam(params, 'campaign_execution_id', campaignAttribution.executionId);
    setParam(params, 'campaign_id', campaignAttribution.campaignId);
    setParam(params, 'campaign_audience_id', campaignAttribution.audienceId);
    setParam(params, 'campaign_recipient_id', campaignAttribution.recipientId);
    setParam(params, 'campaign_source', campaignAttribution.source);
}

function setParam(params: URLSearchParams, key: string, value?: string): void {
    if (value) {
        params.set(key, value);
    }
}

function filterHrefWithout(filter: 'search' | 'status' | 'handoff'): string {
    const params = new URLSearchParams();

    if (filter !== 'search' && searchFilters.value.search) {
        params.set('activity_search', searchFilters.value.search);
    }

    if (filter !== 'status') {
        searchFilters.value.statuses.forEach((status) => params.append('activity_status', status));
    }

    if (filter !== 'handoff') {
        searchFilters.value.handoffStatuses.forEach((status) => params.append('activity_handoff_status', status));
    }

    const query = params.toString();

    return query === '' ? '/x/cockpit' : `/x/cockpit?${query}`;
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

function stringList(value: unknown): string[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => stringValue(item))
        .filter((item): item is string => item !== undefined);
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-operator-issuance-activity-panel"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Operator Issuance Activity
                </p>
                <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                    Quick Generate evidence
                </h3>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                presentation-only
            </span>
        </div>

        <form
            class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950"
            action="/x/cockpit"
            method="get"
            data-testid="cockpit-operator-issuance-activity-filter-form"
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label class="flex-1">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        Search activity
                    </span>
                    <input
                        name="activity_search"
                        type="search"
                        :value="searchFilters.search ?? ''"
                        :disabled="!canFilter"
                        placeholder="Search title, Pay Code, operator, or correlation"
                        class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:disabled:bg-slate-900/50"
                        data-testid="cockpit-operator-issuance-activity-search-input"
                    />
                </label>

                <label class="lg:w-48">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        Status
                    </span>
                    <select
                        name="activity_status"
                        :value="searchFilters.statuses[0] ?? ''"
                        :disabled="!canFilter"
                        class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:disabled:bg-slate-900/50"
                        data-testid="cockpit-operator-issuance-activity-status-filter"
                    >
                        <option value="">Any status</option>
                        <option
                            v-for="status in searchFilters.availableStatuses"
                            :key="status"
                            :value="status"
                            :selected="status === searchFilters.statuses[0]"
                        >
                            {{ status }}
                        </option>
                    </select>
                </label>

                <label class="lg:w-56">
                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        Handoff
                    </span>
                    <select
                        name="activity_handoff_status"
                        :value="searchFilters.handoffStatuses[0] ?? ''"
                        :disabled="!canFilter"
                        class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:disabled:bg-slate-900/50"
                        data-testid="cockpit-operator-issuance-activity-handoff-filter"
                    >
                        <option value="">Any handoff status</option>
                        <option
                            v-for="status in searchFilters.availableHandoffStatuses"
                            :key="status"
                            :value="status"
                            :selected="status === searchFilters.handoffStatuses[0]"
                        >
                            {{ status }}
                        </option>
                    </select>
                </label>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="!canFilter"
                        class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300 dark:bg-slate-50 dark:text-slate-950 dark:disabled:bg-slate-700 dark:disabled:text-slate-400"
                        data-testid="cockpit-operator-issuance-activity-filter-submit"
                    >
                        Apply filters
                    </button>
                    <a
                        href="/x/cockpit"
                        class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200"
                        data-testid="cockpit-operator-issuance-activity-filter-clear"
                    >
                        Clear
                    </a>
                </div>
            </div>

            <div
                class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400"
                data-testid="cockpit-operator-issuance-activity-active-filters"
            >
                <span class="font-semibold text-slate-700 dark:text-slate-300">
                    {{ activeFilterCount }} active filters
                </span>
                <span
                    v-if="searchFilters.search"
                    class="rounded-full bg-white px-2 py-1 dark:bg-slate-900"
                >
                    Search: {{ searchFilters.search }}
                </span>
                <span
                    v-for="status in searchFilters.statuses"
                    :key="`status-${status}`"
                    class="rounded-full bg-white px-2 py-1 dark:bg-slate-900"
                >
                    Status: {{ status }}
                </span>
                <span
                    v-for="status in searchFilters.handoffStatuses"
                    :key="`handoff-${status}`"
                    class="rounded-full bg-white px-2 py-1 dark:bg-slate-900"
                >
                    Handoff: {{ status }}
                </span>
                <span>
                    Read-only filter query; no activity mutation is executed.
                </span>
            </div>
            <p
                class="mt-3 text-xs font-medium text-slate-600 dark:text-slate-300"
                data-testid="cockpit-operator-issuance-activity-result-summary"
            >
                {{ activityResultSummary }}
            </p>
            <p
                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                data-testid="cockpit-operator-issuance-activity-filter-summary"
            >
                {{ activeFilterSummary }}
            </p>
            <div
                v-if="filterClearLinks.length > 0"
                class="mt-3 flex flex-wrap gap-2 text-xs"
                data-testid="cockpit-operator-issuance-activity-clear-links"
            >
                <a
                    v-for="link in filterClearLinks"
                    :key="link.key"
                    :href="link.href"
                    class="rounded-full border border-slate-200 bg-white px-2 py-1 font-semibold text-slate-600 hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-100"
                    :data-testid="`cockpit-operator-issuance-activity-clear-${link.key}`"
                >
                    {{ link.label }}
                </a>
            </div>
        </form>

        <div
            v-if="presentations.length === 0"
            class="mt-5 rounded-lg border border-dashed border-slate-200 p-4 dark:border-slate-800"
            data-testid="cockpit-operator-issuance-activity-empty"
        >
            <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                {{ visibleEmptyTitle }}
            </p>
            <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                {{ visibleEmptyDescription }}
            </p>
        </div>

        <div v-else class="mt-5 space-y-3">
            <article
                v-for="presentation in presentations"
                :key="presentation.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-operator-issuance-activity-card"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                            {{ presentation.title }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ presentation.subtitle }}
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ presentation.status }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-2 text-xs text-slate-500 dark:text-slate-400 sm:grid-cols-3">
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-300">
                            Journal
                        </dt>
                        <dd>journal: {{ presentation.handoffs.journal }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-300">
                            Action
                        </dt>
                        <dd>action: {{ presentation.handoffs.action }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-300">
                            Feedback
                        </dt>
                        <dd>feedback: {{ presentation.handoffs.feedback }}</dd>
                    </div>
                </dl>

                <dl
                    v-if="presentation.campaignAttribution"
                    class="mt-4 grid gap-2 rounded-lg bg-sky-50 p-3 text-xs text-sky-800 dark:bg-sky-950/40 dark:text-sky-200 sm:grid-cols-2"
                    data-testid="cockpit-operator-issuance-activity-campaign-attribution"
                >
                    <div>
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Campaign attribution
                        </dt>
                        <dd>Campaign attribution: {{ presentation.campaignAttribution.status ?? 'available' }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.campaignId">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Campaign
                        </dt>
                        <dd>Campaign: {{ presentation.campaignAttribution.campaignId }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.audienceId">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Audience
                        </dt>
                        <dd>Audience: {{ presentation.campaignAttribution.audienceId }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.recipientId">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Recipient
                        </dt>
                        <dd>Recipient: {{ presentation.campaignAttribution.recipientId }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.recipientReference">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Recipient reference
                        </dt>
                        <dd>Recipient reference: {{ presentation.campaignAttribution.recipientReference }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.templateKey">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Template
                        </dt>
                        <dd>Template: {{ presentation.campaignAttribution.templateKey }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.amount || presentation.campaignAttribution.currency">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Amount
                        </dt>
                        <dd>Amount: {{ presentation.campaignAttribution.currency }} {{ presentation.campaignAttribution.amount }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.generatedCode">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Generated Pay Code
                        </dt>
                        <dd>Generated Pay Code: {{ presentation.campaignAttribution.generatedCode }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.planningKey">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Planning key
                        </dt>
                        <dd>Planning key: {{ presentation.campaignAttribution.planningKey }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.executionId">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Execution
                        </dt>
                        <dd>Execution: {{ presentation.campaignAttribution.executionId }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.source">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Source
                        </dt>
                        <dd>Source: {{ presentation.campaignAttribution.source }}</dd>
                    </div>
                    <div v-if="presentation.campaignAttribution.purpose">
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Purpose
                        </dt>
                        <dd>Purpose: {{ presentation.campaignAttribution.purpose }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Campaign mutation
                        </dt>
                        <dd>Campaign mutation: {{ presentation.campaignAttribution.mutatesCampaign ? 'yes' : 'no' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-sky-900 dark:text-sky-100">
                            Read-only
                        </dt>
                        <dd>Read-only: {{ presentation.campaignAttribution.readOnly ? 'yes' : 'no' }}</dd>
                    </div>
                </dl>

                <dl
                    v-if="presentation.journalSummary"
                    class="mt-4 grid gap-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-300 sm:grid-cols-2"
                    data-testid="cockpit-operator-issuance-activity-journal-summary"
                >
                    <div v-if="presentation.journalSummary.journalEntryId">
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Journal entry
                        </dt>
                        <dd>Journal entry: {{ presentation.journalSummary.journalEntryId }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Writes journal
                        </dt>
                        <dd>Writes journal: {{ presentation.journalSummary.writesJournal ? 'yes' : 'no' }}</dd>
                    </div>
                    <div v-if="presentation.journalSummary.source">
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Source
                        </dt>
                        <dd>Source: {{ presentation.journalSummary.source }}</dd>
                    </div>
                    <div v-if="presentation.journalSummary.reason">
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Reason
                        </dt>
                        <dd>Reason: {{ presentation.journalSummary.reason }}</dd>
                    </div>
                    <div v-if="presentation.journalSummary.referenceNumber">
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Reference
                        </dt>
                        <dd>Reference: {{ presentation.journalSummary.referenceNumber }}</dd>
                    </div>
                    <div v-if="presentation.journalSummary.eventType">
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Event
                        </dt>
                        <dd>Event: {{ presentation.journalSummary.eventType }}</dd>
                    </div>
                    <div
                        v-if="presentation.journalSummary.diagnostic"
                        class="sm:col-span-2"
                        data-testid="cockpit-operator-issuance-activity-journal-diagnostic"
                    >
                        <dt class="font-semibold text-slate-700 dark:text-slate-200">
                            Operator diagnostic
                        </dt>
                        <dd>Diagnostic: {{ presentation.journalSummary.diagnostic.label }}</dd>
                        <dd v-if="presentation.journalSummary.diagnostic.description">
                            {{ presentation.journalSummary.diagnostic.description }}
                        </dd>
                        <dd v-if="presentation.journalSummary.diagnostic.operatorAction">
                            Action: {{ presentation.journalSummary.diagnostic.operatorAction }}
                        </dd>
                        <dd>
                            Read-only: {{ presentation.journalSummary.diagnostic.readOnly ? 'yes' : 'no' }}
                        </dd>
                    </div>
                </dl>

                <dl
                    v-if="presentation.actionSummary"
                    class="mt-4 grid gap-2 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200 sm:grid-cols-2"
                    data-testid="cockpit-operator-issuance-activity-action-summary"
                >
                    <div v-if="presentation.actionSummary.actionHintId">
                        <dt class="font-semibold text-indigo-900 dark:text-indigo-100">
                            Action hint
                        </dt>
                        <dd>Action hint: {{ presentation.actionSummary.actionHintId }}</dd>
                    </div>
                    <div v-if="presentation.actionSummary.actionRunId">
                        <dt class="font-semibold text-indigo-900 dark:text-indigo-100">
                            Action run
                        </dt>
                        <dd>Action run: {{ presentation.actionSummary.actionRunId }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-indigo-900 dark:text-indigo-100">
                            Executes action
                        </dt>
                        <dd>Executes action: {{ presentation.actionSummary.executesAction ? 'yes' : 'no' }}</dd>
                    </div>
                    <div v-if="presentation.actionSummary.suggestedAction">
                        <dt class="font-semibold text-indigo-900 dark:text-indigo-100">
                            Suggested action
                        </dt>
                        <dd>Suggested action: {{ presentation.actionSummary.suggestedAction }}</dd>
                    </div>
                    <div v-if="presentation.actionSummary.source">
                        <dt class="font-semibold text-indigo-900 dark:text-indigo-100">
                            Source
                        </dt>
                        <dd>Source: {{ presentation.actionSummary.source }}</dd>
                    </div>
                    <div v-if="presentation.actionSummary.reason">
                        <dt class="font-semibold text-indigo-900 dark:text-indigo-100">
                            Reason
                        </dt>
                        <dd>Reason: {{ presentation.actionSummary.reason }}</dd>
                    </div>
                </dl>

                <dl
                    v-if="presentation.feedbackSummary"
                    class="mt-4 grid gap-2 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200 sm:grid-cols-2"
                    data-testid="cockpit-operator-issuance-activity-feedback-summary"
                >
                    <div v-if="presentation.feedbackSummary.feedbackIntentId">
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Feedback intent
                        </dt>
                        <dd>Feedback intent: {{ presentation.feedbackSummary.feedbackIntentId }}</dd>
                    </div>
                    <div v-if="presentation.feedbackSummary.deliveryPlanId">
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Delivery plan
                        </dt>
                        <dd>Delivery plan: {{ presentation.feedbackSummary.deliveryPlanId }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Sends feedback
                        </dt>
                        <dd>Sends feedback: {{ presentation.feedbackSummary.sendsFeedback ? 'yes' : 'no' }}</dd>
                    </div>
                    <div v-if="presentation.feedbackSummary.channel">
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Channel
                        </dt>
                        <dd>Channel: {{ presentation.feedbackSummary.channel }}</dd>
                    </div>
                    <div v-if="presentation.feedbackSummary.plannedDeliveries">
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Planned deliveries
                        </dt>
                        <dd>Planned deliveries: {{ presentation.feedbackSummary.plannedDeliveries }}</dd>
                    </div>
                    <div v-if="presentation.feedbackSummary.source">
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Source
                        </dt>
                        <dd>Source: {{ presentation.feedbackSummary.source }}</dd>
                    </div>
                    <div v-if="presentation.feedbackSummary.reason">
                        <dt class="font-semibold text-emerald-900 dark:text-emerald-100">
                            Reason
                        </dt>
                        <dd>Reason: {{ presentation.feedbackSummary.reason }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-col gap-2 text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                    <span v-if="presentation.correlationId">
                        Correlation: {{ presentation.correlationId }}
                    </span>
                    <a
                        v-if="presentation.href"
                        :href="presentation.href"
                        class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-slate-950 dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-white"
                        data-testid="cockpit-operator-issuance-activity-link"
                    >
                        Open Pay Code
                        <span v-if="presentation.campaignAttribution && !presentation.campaignAttribution.mutatesCampaign">
                            · campaign context · read-only
                        </span>
                    </a>
                    <a
                        v-if="presentation.distributionHref"
                        :href="presentation.distributionHref"
                        class="font-semibold text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-slate-950 dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-white"
                        data-testid="cockpit-operator-issuance-activity-distribution-link"
                    >
                        Open Distribution workspace
                        <span v-if="presentation.campaignAttribution && !presentation.campaignAttribution.mutatesCampaign">
                            · campaign context · read-only
                        </span>
                    </a>
                    <a
                        v-if="presentation.explorerHref"
                        :href="presentation.explorerHref"
                        class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 dark:text-sky-200 dark:decoration-sky-600 dark:hover:text-white"
                        data-testid="cockpit-operator-issuance-activity-explorer-link"
                    >
                        Open in Explorer
                        <span v-if="presentation.campaignAttribution && !presentation.campaignAttribution.mutatesCampaign">
                            · campaign context
                        </span>
                    </a>
                    <a
                        v-if="presentation.campaignDashboardHref"
                        :href="presentation.campaignDashboardHref"
                        class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-4 hover:text-sky-950 dark:text-sky-200 dark:decoration-sky-600 dark:hover:text-white"
                        data-testid="cockpit-operator-issuance-activity-campaign-dashboard-link"
                    >
                        Return to Campaign Dashboard
                        <span> · read-only</span>
                    </a>
                </div>
            </article>
        </div>
    </section>
</template>
