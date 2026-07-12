<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type {
    CockpitQuickGenerateCampaignAttribution,
    CockpitQuickGenerateCampaignContext,
    CockpitQuickGenerateDraftContract,
    CockpitQuickGenerateMutationContract,
    CockpitQuickGeneratePostIssuanceNavigation,
    CockpitQuickGeneratePostIssuanceNavigationItem,
    CockpitQuickGenerateRuntimeActivity,
    CockpitQuickGenerateRuntimeDraft,
    CockpitQuickGenerateRuntimeFundingPreflight,
    CockpitQuickGenerateRuntimePricingPreflight,
    CockpitQuickGenerateTemplate,
} from '../types';

const props = defineProps<{
    mutationContract?: CockpitQuickGenerateMutationContract;
    draftContract?: CockpitQuickGenerateDraftContract;
    campaignContext?: CockpitQuickGenerateCampaignContext;
    templates: CockpitQuickGenerateTemplate[];
}>();

const emit = defineEmits<{
    submitStart: [payload: Record<string, unknown>];
    submitSuccess: [response: Record<string, unknown>];
    submitError: [error: Record<string, unknown>];
}>();

const selectedTemplate = ref(stringValue(props.campaignContext?.draft?.template_key) ?? stringValue(props.draftContract?.template_key) ?? props.templates[0]?.key ?? 'money-changer');
const amount = ref(stringValue(props.campaignContext?.draft?.amount) ?? stringValue(props.draftContract?.amount) ?? '25');
const currency = ref(stringValue(props.campaignContext?.draft?.currency) ?? stringValue(props.draftContract?.currency) ?? 'PHP');
const recipientReference = ref(stringValue(props.campaignContext?.draft?.recipient_reference) ?? stringValue(props.draftContract?.recipient_reference) ?? '');
const purpose = ref(stringValue(props.campaignContext?.draft?.purpose) ?? stringValue(props.draftContract?.purpose) ?? '');
const processing = ref(false);
const lastStatus = ref('ready');
const lastMessage = ref('Submit will call the existing x-change issuance handoff route.');
const lastResponse = ref<Record<string, unknown> | null>(null);

const routeUrl = computed<string | null>(() => stringValue(props.mutationContract?.route_url));
const routeName = computed<string>(() => stringValue(props.mutationContract?.route) ?? 'not-loaded');
const allowedMethods = computed<string[]>(() => {
    if (!Array.isArray(props.mutationContract?.allowed_methods)) {
        return [];
    }

    return props.mutationContract.allowed_methods
        .map((method) => method.toUpperCase())
        .filter((method) => method.length > 0);
});

const canSubmit = computed<boolean>(() => {
    return props.mutationContract?.runtime_enabled === true
        && routeUrl.value !== null
        && allowedMethods.value.includes('POST');
});

const campaignContextAvailable = computed<boolean>(() => {
    return props.campaignContext?.status === 'available'
        && props.campaignContext?.authorized === true
        && props.campaignContext?.read_only !== false
        && props.campaignContext?.mutates_campaign !== true;
});

const resultCode = computed<string | null>(() => {
    return stringValue(dataGet(lastResponse.value, ['result', 'code']));
});

const cockpitDetailUrl = computed<string | null>(() => {
    return stringValue(dataGet(lastResponse.value, ['result', 'links', 'cockpit_detail']));
});

const pricingPreflight = computed<CockpitQuickGenerateRuntimePricingPreflight | null>(() => {
    return objectValue(dataGet(lastResponse.value, ['preflight', 'pricing'])) as CockpitQuickGenerateRuntimePricingPreflight | null;
});

const fundingPreflight = computed<CockpitQuickGenerateRuntimeFundingPreflight | null>(() => {
    return objectValue(dataGet(lastResponse.value, ['preflight', 'funding'])) as CockpitQuickGenerateRuntimeFundingPreflight | null;
});

const draftRuntime = computed<CockpitQuickGenerateRuntimeDraft | null>(() => {
    return objectValue(dataGet(lastResponse.value, ['draft'])) as CockpitQuickGenerateRuntimeDraft | null;
});

const activityRuntime = computed<CockpitQuickGenerateRuntimeActivity | null>(() => {
    return objectValue(dataGet(lastResponse.value, ['activity'])) as CockpitQuickGenerateRuntimeActivity | null;
});

const campaignAttribution = computed<CockpitQuickGenerateCampaignAttribution | null>(() => {
    return objectValue(dataGet(lastResponse.value, ['campaign_attribution'])) as CockpitQuickGenerateCampaignAttribution | null;
});

const campaignAttributionAvailable = computed<boolean>(() => {
    return campaignAttribution.value?.status === 'available'
        && campaignAttribution.value?.available === true
        && campaignAttribution.value?.read_only !== false
        && campaignAttribution.value?.mutates_campaign !== true;
});

const postIssuanceNavigation = computed<CockpitQuickGeneratePostIssuanceNavigation | null>(() => {
    return objectValue(dataGet(lastResponse.value, ['post_issuance_navigation'])) as CockpitQuickGeneratePostIssuanceNavigation | null;
});

const postIssuanceNavigationItems = computed<CockpitQuickGeneratePostIssuanceNavigationItem[]>(() => {
    if (!Array.isArray(postIssuanceNavigation.value?.items)) {
        return [];
    }

    return postIssuanceNavigation.value.items
        .map((item): CockpitQuickGeneratePostIssuanceNavigationItem | null => sanitizePostIssuanceNavigationItem(item))
        .filter((item): item is CockpitQuickGeneratePostIssuanceNavigationItem => item !== null);
});

const canRefreshReadModel = computed<boolean>(() => {
    return lastResponse.value !== null && !processing.value;
});

async function submit(): Promise<void> {
    if (!canSubmit.value || processing.value || routeUrl.value === null) {
        return;
    }

    processing.value = true;
    lastStatus.value = 'submitting';
    lastMessage.value = 'Submitting through the idempotency-protected issuance handoff.';

    const idempotencyKey = generateIdempotencyKey();
    const payload = buildPayload();

    emit('submitStart', payload);

    try {
        const response = await fetch(routeUrl.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'Idempotency-Key': idempotencyKey,
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify(payload),
        });

        const body = await safeJson(response);

        if (!response.ok) {
            lastStatus.value = 'failed';
            lastMessage.value = stringValue(body.message) ?? 'Quick Generate submission failed.';
            emit('submitError', body);

            return;
        }

        lastStatus.value = body.status === 'replayed' ? 'replayed' : 'issued';
        lastMessage.value = body.status === 'replayed'
            ? 'Idempotent replay returned the existing operator-safe result.'
            : 'Pay Code issued through the existing x-change issuance handoff.';
        lastResponse.value = body;
        emit('submitSuccess', body);
    } catch (error) {
        const body = {
            message: error instanceof Error ? error.message : 'Quick Generate submission failed.',
        };

        lastStatus.value = 'failed';
        lastMessage.value = body.message;
        emit('submitError', body);
    } finally {
        processing.value = false;
    }
}

function refreshReadModel(): void {
    if (!canRefreshReadModel.value) {
        return;
    }

    router.reload({
        only: ['quick_generate_read_model'],
    });
}

function buildPayload(): Record<string, unknown> {
    const normalizedAmount = Number(amount.value);
    const mobile = recipientReference.value.trim();
    const message = purpose.value.trim();
    const campaign = campaignMetadata();
    const validation = mobile === '' ? {} : { mobile };
    const fields = mobile === '' ? [] : ['mobile'];

    return {
        cash: {
            amount: Number.isFinite(normalizedAmount) ? normalizedAmount : amount.value,
            currency: currency.value.trim() || 'PHP',
            validation,
        },
        inputs: {
            fields,
        },
        count: 1,
        feedback: {
            mobile: mobile === '' ? null : mobile,
        },
        rider: {
            message: message === '' ? null : message,
        },
        metadata: {
            ...(campaign === null ? {} : { campaign }),
            custom: {
                cockpit: {
                    template_key: selectedTemplate.value,
                    source: 'cockpit.quick-generate',
                    ...(campaign === null ? {} : { campaign_context: 'read-model-prefill' }),
                },
            },
        },
    };
}

function campaignMetadata(): Record<string, unknown> | null {
    if (!campaignContextAvailable.value) {
        return null;
    }

    return {
        planning_key: stringValue(props.campaignContext?.planning_key),
        execution_id: stringValue(props.campaignContext?.execution_id),
        campaign_id: stringValue(props.campaignContext?.campaign_id),
        audience_id: stringValue(props.campaignContext?.audience_id),
        recipient_id: stringValue(props.campaignContext?.recipient_id),
        source: stringValue(props.campaignContext?.source) ?? 'campaign_cockpit',
        read_only: true,
        mutates_campaign: false,
    };
}

function generateIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `cockpit-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

async function safeJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const body = await response.json();

        return typeof body === 'object' && body !== null ? body as Record<string, unknown> : {};
    } catch {
        return {};
    }
}

function stringValue(value: unknown): string | null {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return null;
    }

    const normalized = String(value).trim();

    return normalized === '' ? null : normalized;
}

function objectValue(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? value as Record<string, unknown>
        : null;
}

function displayValue(value: unknown, fallback = 'not available'): string {
    const normalized = stringValue(value);

    return normalized ?? fallback;
}

function sanitizePostIssuanceNavigationItem(item: CockpitQuickGeneratePostIssuanceNavigationItem): CockpitQuickGeneratePostIssuanceNavigationItem | null {
    const key = stringValue(item.key);
    const label = stringValue(item.label);

    if (!key || !label) {
        return null;
    }

    return {
        key,
        label,
        href: stringValue(item.href),
        status: stringValue(item.status) ?? 'unknown',
        enabled: item.enabled === true,
        read_only: item.read_only !== false,
        reason: stringValue(item.reason) ?? 'Read-only post-issuance destination.',
    };
}

function dataGet(source: unknown, path: string[]): unknown {
    return path.reduce<unknown>((value, key) => {
        if (typeof value !== 'object' || value === null || !(key in value)) {
            return null;
        }

        return (value as Record<string, unknown>)[key];
    }, source);
}
</script>

<template>
    <form
        class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
        data-testid="cockpit-quick-generate-submit-panel"
        @submit.prevent="submit"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700 dark:text-emerald-300">
                    Mutation Wave 1E
                </p>
                <h3 class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50">
                    Submit through existing issuance handoff
                </h3>
                <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                    Route: {{ routeName }} · {{ routeUrl ?? 'not available' }}
                </p>
            </div>
            <span
                class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
                data-testid="cockpit-quick-generate-submit-status"
            >
                {{ lastStatus }}
            </span>
        </div>

        <section
            v-if="campaignContextAvailable"
            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
            data-testid="cockpit-quick-generate-campaign-context-panel"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">
                        Campaign context prefill
                    </p>
                    <p class="mt-1 leading-5">
                        Campaign context is used only to prefill this form. Quick Generate still hands off to existing issuance and does not mutate campaign state.
                    </p>
                </div>
                <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800">
                    read-only
                </span>
            </div>
            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                <div>
                    <dt class="text-amber-700/80 dark:text-amber-200/80">
                        Planning key
                    </dt>
                    <dd class="font-semibold">
                        {{ displayValue(campaignContext?.planning_key) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-amber-700/80 dark:text-amber-200/80">
                        Execution
                    </dt>
                    <dd class="font-semibold">
                        {{ displayValue(campaignContext?.execution_id) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-amber-700/80 dark:text-amber-200/80">
                        Campaign
                    </dt>
                    <dd class="font-semibold">
                        {{ displayValue(campaignContext?.campaign_id) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-amber-700/80 dark:text-amber-200/80">
                        Source
                    </dt>
                    <dd class="font-semibold">
                        {{ displayValue(campaignContext?.source, 'campaign_cockpit') }}
                    </dd>
                </div>
            </dl>
        </section>

        <div class="mt-4 grid gap-3">
            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Template
                <select
                    v-model="selectedTemplate"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-template"
                    :disabled="processing"
                >
                    <option v-for="template in templates" :key="template.key" :value="template.key">
                        {{ template.name }}
                    </option>
                </select>
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Amount
                <input
                    v-model="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-amount"
                    :disabled="processing"
                >
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Currency
                <input
                    v-model="currency"
                    type="text"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-currency"
                    :disabled="processing"
                >
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Recipient mobile/reference
                <input
                    v-model="recipientReference"
                    type="text"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-recipient"
                    :disabled="processing"
                >
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Purpose/message
                <textarea
                    v-model="purpose"
                    rows="2"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-purpose"
                    :disabled="processing"
                />
            </label>
        </div>

        <button
            type="submit"
            class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 dark:disabled:bg-slate-800 dark:disabled:text-slate-500"
            data-testid="cockpit-quick-generate-submit-button"
            :disabled="!canSubmit || processing"
        >
            {{ processing ? 'Submitting…' : 'Generate Pay Code' }}
        </button>

        <p class="mt-3 text-xs leading-5 text-slate-600 dark:text-slate-300">
            {{ lastMessage }}
        </p>

        <div
            v-if="lastResponse"
            class="mt-4 rounded-xl border border-emerald-200 bg-white p-3 text-xs text-slate-700 dark:border-emerald-900 dark:bg-slate-950 dark:text-slate-300"
            data-testid="cockpit-quick-generate-result-panel"
        >
            <p class="font-semibold text-slate-950 dark:text-slate-50">
                Generated Pay Code: {{ resultCode ?? 'operator-safe result received' }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a
                    v-if="cockpitDetailUrl"
                    :href="cockpitDetailUrl"
                    class="rounded-lg bg-slate-950 px-3 py-2 font-semibold text-white dark:bg-slate-100 dark:text-slate-950"
                    data-testid="cockpit-quick-generate-result-link"
                >
                    Open Cockpit detail
                </a>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-800 dark:text-slate-200"
                    data-testid="cockpit-quick-generate-refresh-button"
                    :disabled="!canRefreshReadModel"
                    @click="refreshReadModel"
                >
                    Refresh read model
                </button>
            </div>
            <p class="mt-3 leading-5 text-slate-500 dark:text-slate-400">
                No automatic redirect is performed. The operator chooses whether to refresh Cockpit data or open the generated Pay Code detail.
            </p>

            <section
                v-if="campaignAttributionAvailable"
                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30"
                data-testid="cockpit-quick-generate-campaign-attribution-panel"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-amber-950 dark:text-amber-50">
                            Campaign attribution
                        </p>
                        <p class="mt-1 text-[11px] leading-4 text-amber-800 dark:text-amber-200">
                            This result keeps campaign context for read-only navigation only. Campaign state is not mutated here.
                        </p>
                    </div>
                    <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800">
                        read-only
                    </span>
                </div>
                <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Planning key
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.planning_key) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Execution
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.execution_id) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Campaign
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.campaign_id) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Generated Pay Code
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.generated_code) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Recipient
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.recipient_id) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Recipient reference
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.recipient_reference) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Template
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.template_key) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Amount
                        </dt>
                        <dd class="font-semibold text-amber-950 dark:text-amber-50">
                            {{ displayValue(campaignAttribution?.currency) }} {{ displayValue(campaignAttribution?.amount) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                v-if="postIssuanceNavigationItems.length > 0"
                class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-quick-generate-post-issuance-navigation-panel"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            Post-issuance handoff
                        </p>
                        <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                            Read-only destinations for the generated Pay Code. Automatic redirect:
                            {{ postIssuanceNavigation?.auto_redirect === true ? 'enabled' : 'disabled' }}.
                        </p>
                    </div>
                    <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800">
                        {{ displayValue(postIssuanceNavigation?.status) }}
                    </span>
                </div>

                <div class="mt-3 grid gap-2">
                    <a
                        v-for="item in postIssuanceNavigationItems"
                        :key="item.key ?? item.label"
                        :href="item.enabled === true && item.href ? item.href : undefined"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 aria-disabled:cursor-not-allowed aria-disabled:opacity-60 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-900 dark:hover:text-emerald-300"
                        :aria-disabled="item.enabled === true && item.href ? undefined : 'true'"
                        :data-testid="`cockpit-quick-generate-post-issuance-link-${item.key}`"
                    >
                        {{ item.label }}
                        <span class="ml-2 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                            {{ item.status }} · {{ item.read_only === true ? 'read-only' : 'mutation' }}
                        </span>
                    </a>
                </div>
            </section>

            <div
                v-if="draftRuntime || activityRuntime"
                class="mt-4 grid gap-3 md:grid-cols-2"
                data-testid="cockpit-quick-generate-runtime-metadata-panel"
            >
                <section
                    v-if="draftRuntime"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-quick-generate-draft-runtime-card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            Draft runtime
                        </p>
                        <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800">
                            {{ displayValue(draftRuntime.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Factory
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ displayValue(draftRuntime.factory) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Compiler
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ displayValue(draftRuntime.compiler) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="activityRuntime"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-quick-generate-activity-runtime-card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            Activity runtime
                        </p>
                        <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800">
                            {{ displayValue(activityRuntime.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Schema
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ displayValue(activityRuntime.schema) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Presentation only
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ activityRuntime.presentation_only === true ? 'yes' : 'no' }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <div
                v-if="pricingPreflight || fundingPreflight"
                class="mt-4 grid gap-3 md:grid-cols-2"
                data-testid="cockpit-quick-generate-runtime-preflight-panel"
            >
                <section
                    v-if="pricingPreflight"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-quick-generate-pricing-preflight-card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            Pricing preflight
                        </p>
                        <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800">
                            {{ displayValue(pricingPreflight.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Total
                            </dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">
                                {{ displayValue(pricingPreflight.currency, 'PHP') }} {{ displayValue(pricingPreflight.total, '0') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Base fee
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ displayValue(pricingPreflight.base_fee, '0') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Blocking
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ pricingPreflight.blocking === true ? 'yes' : 'no' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="fundingPreflight"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-quick-generate-funding-preflight-card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-slate-950 dark:text-slate-50">
                            Funding preflight
                        </p>
                        <span class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800">
                            {{ displayValue(fundingPreflight.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Authority
                            </dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">
                                {{ displayValue(fundingPreflight.authority) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Balance
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ displayValue(fundingPreflight.authoritative?.currency, 'PHP') }}
                                {{ displayValue(fundingPreflight.authoritative?.balance, 'not available') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Sync
                            </dt>
                            <dd class="font-medium text-slate-700 dark:text-slate-200">
                                {{ displayValue(fundingPreflight.sync_status) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </form>
</template>
