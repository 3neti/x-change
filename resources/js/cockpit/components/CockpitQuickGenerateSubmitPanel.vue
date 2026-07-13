<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CockpitManualCopyButton from './CockpitManualCopyButton.vue';
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

type VoucherInstructionCoverageStatus =
    | 'editable'
    | 'defaulted'
    | 'advanced'
    | 'preview-only';

type VoucherInstructionCoverageGroup = {
    key: string;
    label: string;
    fields: Array<{
        key: string;
        status: VoucherInstructionCoverageStatus;
    }>;
};

const voucherInstructionCoverageGroups: VoucherInstructionCoverageGroup[] = [
    {
        key: 'cash',
        label: 'cash',
        fields: [
            { key: 'cash.amount', status: 'editable' },
            { key: 'cash.currency', status: 'editable' },
            { key: 'cash.settlement_rail', status: 'advanced' },
            { key: 'cash.fee_strategy', status: 'advanced' },
            { key: 'cash.slice_mode', status: 'editable' },
            { key: 'cash.slices', status: 'advanced' },
            { key: 'cash.max_slices', status: 'editable' },
            { key: 'cash.min_withdrawal', status: 'editable' },
            { key: 'cash.type', status: 'advanced' },
            { key: 'cash.mandates', status: 'advanced' },
        ],
    },
    {
        key: 'cash_validation',
        label: 'cash.validation',
        fields: [
            { key: 'cash.validation.secret', status: 'editable' },
            { key: 'cash.validation.mobile', status: 'editable' },
            { key: 'cash.validation.payable', status: 'editable' },
            { key: 'cash.validation.country', status: 'editable' },
            { key: 'cash.validation.location', status: 'editable' },
            { key: 'cash.validation.radius', status: 'editable' },
            { key: 'cash.validation.mobile_verification', status: 'advanced' },
        ],
    },
    {
        key: 'inputs',
        label: 'inputs',
        fields: [{ key: 'inputs.fields', status: 'editable' }],
    },
    {
        key: 'feedback',
        label: 'feedback',
        fields: [
            { key: 'feedback.email', status: 'editable' },
            { key: 'feedback.mobile', status: 'editable' },
            { key: 'feedback.webhook', status: 'editable' },
        ],
    },
    {
        key: 'rider',
        label: 'rider',
        fields: [
            { key: 'rider.message', status: 'editable' },
            { key: 'rider.url', status: 'editable' },
            { key: 'rider.redirect_timeout', status: 'advanced' },
            { key: 'rider.splash', status: 'editable' },
            { key: 'rider.splash_timeout', status: 'editable' },
            { key: 'rider.splash_meta', status: 'advanced' },
            { key: 'rider.og_source', status: 'advanced' },
        ],
    },
    {
        key: 'validation',
        label: 'validation',
        fields: [
            { key: 'validation.signature', status: 'advanced' },
            { key: 'validation.selfie', status: 'editable' },
            { key: 'validation.location', status: 'editable' },
            { key: 'validation.otp', status: 'editable' },
            { key: 'validation.face_match', status: 'advanced' },
            { key: 'validation.time', status: 'advanced' },
        ],
    },
    {
        key: 'generation',
        label: 'generation',
        fields: [
            { key: 'count', status: 'editable' },
            { key: 'prefix', status: 'advanced' },
            { key: 'mask', status: 'advanced' },
            { key: 'ttl', status: 'advanced' },
            { key: 'starts_at', status: 'advanced' },
            { key: 'expires_at', status: 'advanced' },
        ],
    },
    {
        key: 'settlement',
        label: 'settlement',
        fields: [
            { key: 'voucher_type', status: 'advanced' },
            { key: 'target_amount', status: 'advanced' },
            { key: 'rules', status: 'advanced' },
        ],
    },
    {
        key: 'execution',
        label: 'execution',
        fields: [
            { key: 'execution.schema', status: 'defaulted' },
            { key: 'execution.driver', status: 'defaulted' },
            { key: 'execution.mode', status: 'advanced' },
            { key: 'execution.pipeline', status: 'advanced' },
            { key: 'execution.fallback', status: 'advanced' },
            { key: 'execution.visibility', status: 'advanced' },
            { key: 'execution.metadata', status: 'advanced' },
        ],
    },
    {
        key: 'metadata',
        label: 'metadata',
        fields: [
            { key: 'metadata.flow_type', status: 'defaulted' },
            { key: 'metadata.issuer_id', status: 'preview-only' },
            { key: 'metadata.collection_wallet_id', status: 'preview-only' },
            { key: 'metadata.campaign', status: 'preview-only' },
        ],
    },
];

const selectedTemplate = ref(
    stringValue(props.campaignContext?.draft?.template_key) ??
        stringValue(props.draftContract?.template_key) ??
        props.templates[0]?.key ??
        'money-changer',
);
const amount = ref(
    stringValue(props.campaignContext?.draft?.amount) ??
        stringValue(props.draftContract?.amount) ??
        '25',
);
const currency = ref(
    stringValue(props.campaignContext?.draft?.currency) ??
        stringValue(props.draftContract?.currency) ??
        'PHP',
);
const recipientReference = ref(
    stringValue(props.campaignContext?.draft?.recipient_reference) ??
        stringValue(props.draftContract?.recipient_reference) ??
        '',
);
const purpose = ref(
    stringValue(props.campaignContext?.draft?.purpose) ??
        stringValue(props.draftContract?.purpose) ??
        '',
);
const count = ref('1');
const requireMobileInput = ref(true);
const requireFullNameInput = ref(false);
const requireBankAccountInput = ref(false);
const requireEmailInput = ref(false);
const validationSecret = ref('');
const requireMobileValidation = ref(true);
const requirePayableValidation = ref(false);
const requireCountryValidation = ref(false);
const requireLocationValidation = ref(false);
const verificationKyc = ref(false);
const verificationOtp = ref(false);
const verificationSelfie = ref(false);
const riderUrl = ref('');
const riderSplash = ref('');
const riderSplashTimeout = ref('3');
const feedbackEmail = ref('');
const feedbackMobile = ref(recipientReference.value);
const feedbackWebhook = ref('');
const sliceMode = ref<'whole' | 'open'>('whole');
const maxSlices = ref('2');
const minWithdrawal = ref('0');
const processing = ref(false);
const lastStatus = ref('ready');
const lastMessage = ref(
    'Submit will call the existing x-change issuance handoff route.',
);
const lastResponse = ref<Record<string, unknown> | null>(null);

const routeUrl = computed<string | null>(() =>
    stringValue(props.mutationContract?.route_url),
);
const routeName = computed<string>(
    () => stringValue(props.mutationContract?.route) ?? 'not-loaded',
);
const allowedMethods = computed<string[]>(() => {
    if (!Array.isArray(props.mutationContract?.allowed_methods)) {
        return [];
    }

    return props.mutationContract.allowed_methods
        .map((method) => method.toUpperCase())
        .filter((method) => method.length > 0);
});

const canSubmit = computed<boolean>(() => {
    return (
        props.mutationContract?.runtime_enabled === true &&
        routeUrl.value !== null &&
        allowedMethods.value.includes('POST')
    );
});

const campaignContextAvailable = computed<boolean>(() => {
    return (
        props.campaignContext?.status === 'available' &&
        props.campaignContext?.authorized === true &&
        props.campaignContext?.read_only !== false &&
        props.campaignContext?.mutates_campaign !== true
    );
});

const resultCode = computed<string | null>(() => {
    return stringValue(dataGet(lastResponse.value, ['result', 'code']));
});

const cockpitDetailUrl = computed<string | null>(() => {
    return stringValue(
        dataGet(lastResponse.value, ['result', 'links', 'cockpit_detail']),
    );
});

const beneficiaryRedeemUrl = computed<string | null>(() => {
    return stringValue(
        dataGet(lastResponse.value, ['result', 'links', 'redeem']),
    );
});

const beneficiaryRedeemPath = computed<string | null>(() => {
    return stringValue(
        dataGet(lastResponse.value, ['result', 'links', 'redeem_path']),
    );
});

const beneficiaryClaimUrl = computed<string | null>(() => {
    if (beneficiaryRedeemUrl.value !== null) {
        return beneficiaryRedeemUrl.value;
    }

    if (beneficiaryRedeemPath.value === null || typeof window === 'undefined') {
        return beneficiaryRedeemPath.value;
    }

    return `${window.location.origin}${beneficiaryRedeemPath.value}`;
});

const pricingPreflight =
    computed<CockpitQuickGenerateRuntimePricingPreflight | null>(() => {
        return objectValue(
            dataGet(lastResponse.value, ['preflight', 'pricing']),
        ) as CockpitQuickGenerateRuntimePricingPreflight | null;
    });

const fundingPreflight =
    computed<CockpitQuickGenerateRuntimeFundingPreflight | null>(() => {
        return objectValue(
            dataGet(lastResponse.value, ['preflight', 'funding']),
        ) as CockpitQuickGenerateRuntimeFundingPreflight | null;
    });

const draftRuntime = computed<CockpitQuickGenerateRuntimeDraft | null>(() => {
    return objectValue(
        dataGet(lastResponse.value, ['draft']),
    ) as CockpitQuickGenerateRuntimeDraft | null;
});

const activityRuntime = computed<CockpitQuickGenerateRuntimeActivity | null>(
    () => {
        return objectValue(
            dataGet(lastResponse.value, ['activity']),
        ) as CockpitQuickGenerateRuntimeActivity | null;
    },
);

const campaignAttribution =
    computed<CockpitQuickGenerateCampaignAttribution | null>(() => {
        return objectValue(
            dataGet(lastResponse.value, ['campaign_attribution']),
        ) as CockpitQuickGenerateCampaignAttribution | null;
    });

const campaignAttributionAvailable = computed<boolean>(() => {
    return (
        campaignAttribution.value?.status === 'available' &&
        campaignAttribution.value?.available === true &&
        campaignAttribution.value?.read_only !== false &&
        campaignAttribution.value?.mutates_campaign !== true
    );
});

const postIssuanceNavigation =
    computed<CockpitQuickGeneratePostIssuanceNavigation | null>(() => {
        return objectValue(
            dataGet(lastResponse.value, ['post_issuance_navigation']),
        ) as CockpitQuickGeneratePostIssuanceNavigation | null;
    });

const postIssuanceNavigationItems = computed<
    CockpitQuickGeneratePostIssuanceNavigationItem[]
>(() => {
    if (!Array.isArray(postIssuanceNavigation.value?.items)) {
        return [];
    }

    return postIssuanceNavigation.value.items
        .map((item): CockpitQuickGeneratePostIssuanceNavigationItem | null =>
            sanitizePostIssuanceNavigationItem(item),
        )
        .filter(
            (item): item is CockpitQuickGeneratePostIssuanceNavigationItem =>
                item !== null,
        );
});

const canRefreshReadModel = computed<boolean>(() => {
    return lastResponse.value !== null && !processing.value;
});

const selectedTemplateName = computed<string>(() => {
    return (
        props.templates.find(
            (template) => template.key === selectedTemplate.value,
        )?.name ?? selectedTemplate.value
    );
});

const selectedInputFields = computed<string[]>(() => {
    const fields = [];

    if (requireMobileInput.value || recipientReference.value.trim() !== '') {
        fields.push('mobile');
    }

    if (requireFullNameInput.value) {
        fields.push('name');
    }

    if (requireBankAccountInput.value) {
        fields.push('bank_account');
    }

    if (requireEmailInput.value || feedbackEmail.value.trim() !== '') {
        fields.push('email');
    }

    return fields;
});

const validationSummary = computed<Record<string, unknown>>(() => {
    const mobile = recipientReference.value.trim();
    const secret = validationSecret.value.trim();

    return {
        ...(secret === '' ? {} : { secret }),
        ...(requireMobileValidation.value && mobile !== '' ? { mobile } : {}),
        ...(requirePayableValidation.value ? { payable: 'required' } : {}),
        ...(requireCountryValidation.value ? { country: 'PH' } : {}),
        ...(requireLocationValidation.value
            ? { location: 'required', radius: '100' }
            : {}),
    };
});

const verificationSummary = computed<string[]>(() => {
    return [
        verificationKyc.value ? 'kyc' : null,
        verificationOtp.value ? 'otp' : null,
        verificationSelfie.value ? 'selfie' : null,
    ].filter((item): item is string => item !== null);
});

const feedbackSummary = computed<Record<string, unknown>>(() => {
    const mobile =
        feedbackMobile.value.trim() || recipientReference.value.trim();
    const email = feedbackEmail.value.trim();
    const webhook = feedbackWebhook.value.trim();

    return {
        mobile: mobile === '' ? null : mobile,
        email: email === '' ? null : email,
        webhook: webhook === '' ? null : webhook,
    };
});

const riderSummary = computed<Record<string, unknown>>(() => {
    const message = purpose.value.trim();
    const url = riderUrl.value.trim();
    const splash = riderSplash.value.trim();
    const timeout = Number(riderSplashTimeout.value);

    return {
        message: message === '' ? null : message,
        url: url === '' ? null : url,
        splash: splash === '' ? null : splash,
        splash_timeout:
            Number.isFinite(timeout) && timeout > 0 ? timeout : null,
    };
});

const sliceSummary = computed<Record<string, unknown>>(() => {
    const max = Number(maxSlices.value);
    const minimum = Number(minWithdrawal.value);

    if (sliceMode.value !== 'open') {
        return {
            mode: 'whole',
            max_slices: 1,
            min_withdrawal: null,
        };
    }

    return {
        mode: 'open',
        max_slices: Number.isFinite(max) && max > 0 ? Math.round(max) : 2,
        min_withdrawal:
            Number.isFinite(minimum) && minimum > 0 ? minimum : null,
    };
});

const contractSummaryItems = computed<Array<{ label: string; value: string }>>(
    () => {
        return [
            { label: 'Template', value: selectedTemplateName.value },
            {
                label: 'Money',
                value: `${currency.value.trim() || 'PHP'} ${amount.value || '0'} × ${count.value || '1'}`,
            },
            {
                label: 'Claim inputs',
                value:
                    selectedInputFields.value.length > 0
                        ? selectedInputFields.value.join(', ')
                        : 'none',
            },
            {
                label: 'Validation',
                value:
                    Object.keys(validationSummary.value).length > 0
                        ? Object.keys(validationSummary.value).join(', ')
                        : 'none',
            },
            {
                label: 'Verification',
                value:
                    verificationSummary.value.length > 0
                        ? verificationSummary.value.join(', ')
                        : 'none',
            },
            {
                label: 'Rider',
                value:
                    purpose.value.trim() ||
                    riderUrl.value.trim() ||
                    riderSplash.value.trim()
                        ? 'configured'
                        : 'none',
            },
            {
                label: 'Feedback',
                value: Object.values(feedbackSummary.value).some(
                    (value) => value !== null,
                )
                    ? 'configured'
                    : 'none',
            },
            {
                label: 'Slices',
                value:
                    sliceMode.value === 'open'
                        ? `open · max ${sliceSummary.value.max_slices}`
                        : 'whole amount',
            },
        ];
    },
);

const sanitizedInstructionPayload = computed<Record<string, unknown>>(() => {
    return buildPayloadShape(true);
});

const sanitizedInstructionPayloadJson = computed<string>(() => {
    return JSON.stringify(sanitizedInstructionPayload.value, null, 2);
});

async function submit(): Promise<void> {
    if (!canSubmit.value || processing.value || routeUrl.value === null) {
        return;
    }

    processing.value = true;
    lastStatus.value = 'submitting';
    lastMessage.value =
        'Submitting through the idempotency-protected issuance handoff.';

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
            lastMessage.value =
                stringValue(body.message) ??
                'Quick Generate submission failed.';
            emit('submitError', body);

            return;
        }

        lastStatus.value = body.status === 'replayed' ? 'replayed' : 'issued';
        lastMessage.value =
            body.status === 'replayed'
                ? 'Idempotent replay returned the existing operator-safe result.'
                : 'Pay Code issued through the existing x-change issuance handoff.';
        lastResponse.value = body;
        emit('submitSuccess', body);
    } catch (error) {
        const body = {
            message:
                error instanceof Error
                    ? error.message
                    : 'Quick Generate submission failed.',
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
    return buildPayloadShape(false);
}

function buildPayloadShape(redactSensitive: boolean): Record<string, unknown> {
    const normalizedAmount = Number(amount.value);
    const normalizedCount = Number(count.value);
    const campaign = campaignMetadata();
    const normalizedMaxSlices = Number(sliceSummary.value.max_slices);
    const normalizedMinWithdrawal = Number(sliceSummary.value.min_withdrawal);
    const cash: Record<string, unknown> = {
        amount: Number.isFinite(normalizedAmount)
            ? normalizedAmount
            : amount.value,
        currency: currency.value.trim() || 'PHP',
        validation: validationSummary.value,
    };

    if (sliceMode.value === 'open') {
        cash.slice_mode = 'open';
        cash.max_slices = Number.isFinite(normalizedMaxSlices)
            ? normalizedMaxSlices
            : 2;
        cash.min_withdrawal = Number.isFinite(normalizedMinWithdrawal)
            ? normalizedMinWithdrawal
            : null;
    }

    const validation = { ...validationSummary.value };

    if (redactSensitive && 'secret' in validation) {
        validation.secret = '[redacted secret]';
    }

    cash.validation = validation;

    return {
        cash,
        inputs: {
            fields: selectedInputFields.value,
            requirements: verificationSummary.value,
        },
        count:
            Number.isFinite(normalizedCount) && normalizedCount > 0
                ? Math.round(normalizedCount)
                : 1,
        feedback: feedbackSummary.value,
        rider: riderSummary.value,
        metadata: {
            ...(campaign === null ? {} : { campaign }),
            ...(sliceMode.value === 'open'
                ? {
                      slice_policy: {
                          mode: 'open',
                          selection: 'operator',
                          enforced: false,
                      },
                  }
                : {}),
            custom: {
                cockpit: {
                    template_key: selectedTemplate.value,
                    source: 'cockpit.quick-generate',
                    builder: 'guided-voucher-instruction-builder',
                    contract_summary: contractSummaryItems.value,
                    ...(campaign === null
                        ? {}
                        : { campaign_context: 'read-model-prefill' }),
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
        source:
            stringValue(props.campaignContext?.source) ?? 'campaign_cockpit',
        read_only: true,
        mutates_campaign: false,
    };
}

function generateIdempotencyKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `cockpit-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

async function safeJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const body = await response.json();

        return typeof body === 'object' && body !== null
            ? (body as Record<string, unknown>)
            : {};
    } catch {
        return {};
    }
}

function stringValue(value: unknown): string | null {
    if (
        typeof value !== 'string' &&
        typeof value !== 'number' &&
        typeof value !== 'boolean'
    ) {
        return null;
    }

    const normalized = String(value).trim();

    return normalized === '' ? null : normalized;
}

function objectValue(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : null;
}

function displayValue(value: unknown, fallback = 'not available'): string {
    const normalized = stringValue(value);

    return normalized ?? fallback;
}

function sanitizePostIssuanceNavigationItem(
    item: CockpitQuickGeneratePostIssuanceNavigationItem,
): CockpitQuickGeneratePostIssuanceNavigationItem | null {
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
        reason:
            stringValue(item.reason) ?? 'Read-only post-issuance destination.',
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
        class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-sky-50 p-4 shadow-sm dark:border-emerald-900/70 dark:from-emerald-950/40 dark:via-slate-950 dark:to-sky-950/30"
        data-testid="cockpit-quick-generate-submit-panel"
        @submit.prevent="submit"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.22em] text-emerald-700 uppercase dark:text-emerald-300"
                >
                    Guided voucher instruction builder
                </p>
                <h3
                    class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50"
                >
                    Design the Pay Code contract before generation
                </h3>
                <p
                    class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300"
                >
                    This builder maps operator choices into the existing
                    x-change GeneratePayCode handoff. It does not create a
                    parallel issuance runtime.
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
                    <p class="font-semibold">Campaign context prefill</p>
                    <p class="mt-1 leading-5">
                        Campaign context is used only to prefill this form.
                        Quick Generate still hands off to existing issuance and
                        does not mutate campaign state.
                    </p>
                </div>
                <span
                    class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800"
                >
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
                        {{
                            displayValue(
                                campaignContext?.source,
                                'campaign_cockpit',
                            )
                        }}
                    </dd>
                </div>
            </dl>
        </section>

        <section
            class="mt-5 rounded-2xl border border-slate-200 bg-white/80 p-4 text-xs text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-950/80 dark:text-slate-300"
            data-testid="cockpit-voucher-instruction-coverage"
        >
            <div
                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
            >
                <div>
                    <p
                        class="font-semibold tracking-[0.2em] text-slate-500 uppercase dark:text-slate-400"
                    >
                        VoucherInstruction DTO coverage
                    </p>
                    <h4
                        class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50"
                    >
                        Fields represented on this page
                    </h4>
                    <p class="mt-1 leading-5">
                        This coverage map keeps the operator UI aligned with the
                        voucher-owned DTO. Advanced fields are visible here even
                        when their editors remain collapsed or defaulted.
                    </p>
                </div>
                <span
                    class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-800"
                >
                    read-only coverage map
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div
                    v-for="group in voucherInstructionCoverageGroups"
                    :key="group.key"
                    class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900/70"
                >
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ group.label }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="field in group.fields"
                            :key="field.key"
                            class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"
                        >
                            {{ field.key }}
                            <span class="text-slate-400">
                                · {{ field.status }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="mt-5 grid gap-4"
            data-testid="cockpit-voucher-instruction-builder"
        >
            <div class="grid gap-4">
                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-200"
                            >1</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Money and quantity
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Amount, currency, template, and number of Pay
                                Codes.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Template
                            <select
                                v-model="selectedTemplate"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-template"
                                :disabled="processing"
                            >
                                <option
                                    v-for="template in templates"
                                    :key="template.key"
                                    :value="template.key"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                        </label>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Quantity
                            <input
                                v-model="count"
                                type="number"
                                min="1"
                                step="1"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-count"
                                :disabled="processing"
                            />
                        </label>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Amount
                            <input
                                v-model="amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-amount"
                                :disabled="processing"
                            />
                        </label>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Currency
                            <input
                                v-model="currency"
                                type="text"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-currency"
                                :disabled="processing"
                            />
                        </label>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-700 dark:bg-sky-900/60 dark:text-sky-200"
                            >2</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Claim inputs
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                What the beneficiary must provide before
                                execution.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <label
                            class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Recipient mobile/reference
                            <input
                                v-model="recipientReference"
                                type="text"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-recipient"
                                :disabled="processing"
                            />
                        </label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireMobileInput"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Mobile number
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireFullNameInput"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Full name
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireBankAccountInput"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Bank or wallet account
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireEmailInput"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Email address
                            </label>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-900/60 dark:text-violet-200"
                            >3</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Validation and verification
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Validation maps into cash validation.
                                Verification stays operator intent metadata.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <label
                            class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Claim secret / passphrase
                            <input
                                v-model="validationSecret"
                                type="text"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-validation-secret"
                                :disabled="processing"
                            />
                        </label>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireMobileValidation"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Match recipient mobile
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requirePayableValidation"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Payable-only validation
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireCountryValidation"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                PH country gate
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="requireLocationValidation"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Location radius gate
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="verificationKyc"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                KYC evidence
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="verificationOtp"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                OTP confirmation
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="verificationSelfie"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Selfie evidence
                            </label>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-700 dark:bg-amber-900/60 dark:text-amber-200"
                            >4</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Rider and beneficiary experience
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Operator-safe context shown during the claim
                                journey.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Purpose/message
                            <textarea
                                v-model="purpose"
                                rows="2"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-purpose"
                                :disabled="processing"
                            />
                        </label>
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Rider URL
                                <input
                                    v-model="riderUrl"
                                    type="url"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    :disabled="processing"
                                />
                            </label>
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Splash timeout
                                <input
                                    v-model="riderSplashTimeout"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    :disabled="processing"
                                />
                            </label>
                        </div>
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Splash text
                            <input
                                v-model="riderSplash"
                                type="text"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                :disabled="processing"
                            />
                        </label>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-sm font-bold text-rose-700 dark:bg-rose-900/60 dark:text-rose-200"
                            >5</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Feedback channels
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Saved as feedback intent. Cockpit still does not
                                deliver SMS, email, or webhooks directly.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Feedback email
                            <input
                                v-model="feedbackEmail"
                                type="email"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                :disabled="processing"
                            />
                        </label>
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Feedback mobile
                            <input
                                v-model="feedbackMobile"
                                type="tel"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-feedback-mobile"
                                :disabled="processing"
                            />
                        </label>
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Feedback webhook
                            <input
                                v-model="feedbackWebhook"
                                type="url"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                :disabled="processing"
                            />
                        </label>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-100 text-sm font-bold text-cyan-700 dark:bg-cyan-900/60 dark:text-cyan-200"
                            >6</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Slices and availability
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Open-slice settings are passed through existing
                                instruction metadata.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Slice policy
                            <select
                                v-model="sliceMode"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                :disabled="processing"
                            >
                                <option value="whole">Whole amount</option>
                                <option value="open">Open slices</option>
                            </select>
                        </label>
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Max slices
                            <input
                                v-model="maxSlices"
                                type="number"
                                min="1"
                                step="1"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                :disabled="processing || sliceMode !== 'open'"
                            />
                        </label>
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Minimum withdrawal
                            <input
                                v-model="minWithdrawal"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                :disabled="processing || sliceMode !== 'open'"
                            />
                        </label>
                    </div>
                </section>
            </div>
        </div>

        <section
            class="mt-5 rounded-2xl border border-slate-200 bg-slate-950 p-4 text-xs text-slate-300 shadow-sm dark:border-slate-800"
            data-testid="cockpit-voucher-instruction-summary"
        >
            <div
                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
            >
                <div>
                    <p
                        class="font-semibold tracking-[0.22em] text-emerald-300 uppercase"
                    >
                        Pay Code contract summary
                    </p>
                    <p class="mt-2 leading-5 text-slate-400">
                        Operator preview of the payload sent to the existing
                        issuance handoff.
                    </p>
                </div>
                <div
                    class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-3 md:max-w-sm"
                >
                    <p class="font-semibold text-emerald-200">Handoff route</p>
                    <p class="mt-1 break-all text-slate-300">
                        {{ routeName }} · {{ routeUrl ?? 'not available' }}
                    </p>
                </div>
            </div>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="item in contractSummaryItems"
                    :key="item.label"
                    class="min-w-0 rounded-xl border border-white/10 bg-white/5 p-3"
                >
                    <dt class="text-slate-500">
                        {{ item.label }}
                    </dt>
                    <dd class="mt-1 font-semibold break-words text-slate-100">
                        {{ item.value }}
                    </dd>
                </div>
            </dl>

            <details
                class="mt-4 rounded-xl border border-white/10 bg-white/5 p-3"
                data-testid="cockpit-quick-generate-engineering-preview"
            >
                <summary
                    class="cursor-pointer text-sm font-semibold text-slate-100"
                >
                    Engineering Preview — sanitized instruction payload
                </summary>
                <p class="mt-2 text-xs leading-5 text-slate-400">
                    This preview shows how the builder maps into
                    <code>cash</code>, <code>inputs</code>,
                    <code>validation</code>, <code>rider</code>,
                    <code>feedback</code>, and <code>metadata</code>. Secrets
                    are redacted; provider, wallet, journal, action, feedback
                    delivery, and campaign mutation internals are not rendered.
                </p>
                <pre
                    class="mt-3 max-h-96 overflow-auto rounded-xl border border-slate-800 bg-slate-950 p-3 text-[11px] leading-5 text-slate-200"
                    data-testid="cockpit-quick-generate-engineering-preview-json"
                    >{{ sanitizedInstructionPayloadJson }}</pre
                >
            </details>
        </section>

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
                Generated Pay Code:
                {{ resultCode ?? 'operator-safe result received' }}
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
                No automatic redirect is performed. The operator chooses whether
                to refresh Cockpit data or open the generated Pay Code detail.
            </p>

            <section
                v-if="beneficiaryClaimUrl || beneficiaryRedeemPath"
                class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/70 dark:bg-emerald-950/30"
                data-testid="cockpit-quick-generate-beneficiary-url-panel"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="font-semibold text-emerald-950 dark:text-emerald-50"
                        >
                            Beneficiary Pay Code URL
                        </p>
                        <p
                            class="mt-1 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                        >
                            Operator-safe full URL from the existing issuance
                            result. Showing this link does not send SMS, email,
                            webhook, or campaign delivery.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
                    >
                        read-only
                    </span>
                </div>
                <dl class="mt-3 grid gap-2">
                    <div v-if="beneficiaryClaimUrl">
                        <dt
                            class="text-[11px] font-medium tracking-[0.18em] text-emerald-700/80 uppercase dark:text-emerald-200/80"
                        >
                            Full URL
                        </dt>
                        <dd
                            class="mt-1 font-mono text-[12px] font-semibold break-all text-emerald-950 dark:text-emerald-50"
                        >
                            <a
                                :href="beneficiaryClaimUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline decoration-emerald-400 underline-offset-4"
                                data-testid="cockpit-quick-generate-beneficiary-url-link"
                            >
                                {{ beneficiaryClaimUrl }}
                            </a>
                        </dd>
                    </div>
                    <div v-if="beneficiaryRedeemPath">
                        <dt
                            class="text-[11px] font-medium tracking-[0.18em] text-emerald-700/80 uppercase dark:text-emerald-200/80"
                        >
                            Path
                        </dt>
                        <dd
                            class="mt-1 font-mono text-[12px] font-semibold break-all text-emerald-950 dark:text-emerald-50"
                        >
                            {{ beneficiaryRedeemPath }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-3">
                    <CockpitManualCopyButton
                        :value="beneficiaryClaimUrl"
                        label="Copy beneficiary URL"
                    />
                </div>
            </section>

            <section
                v-if="campaignAttributionAvailable"
                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30"
                data-testid="cockpit-quick-generate-campaign-attribution-panel"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            Campaign attribution
                        </p>
                        <p
                            class="mt-1 text-[11px] leading-4 text-amber-800 dark:text-amber-200"
                        >
                            This result keeps campaign context for read-only
                            navigation only. Campaign state is not mutated here.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800"
                    >
                        read-only
                    </span>
                </div>
                <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Planning key
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{
                                displayValue(campaignAttribution?.planning_key)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Execution
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{
                                displayValue(campaignAttribution?.execution_id)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Campaign
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{ displayValue(campaignAttribution?.campaign_id) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Generated Pay Code
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{
                                displayValue(
                                    campaignAttribution?.generated_code,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Recipient
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{
                                displayValue(campaignAttribution?.recipient_id)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Recipient reference
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{
                                displayValue(
                                    campaignAttribution?.recipient_reference,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Template
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{
                                displayValue(campaignAttribution?.template_key)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-amber-700/80 dark:text-amber-200/80">
                            Amount
                        </dt>
                        <dd
                            class="font-semibold text-amber-950 dark:text-amber-50"
                        >
                            {{ displayValue(campaignAttribution?.currency) }}
                            {{ displayValue(campaignAttribution?.amount) }}
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
                        <p
                            class="font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Post-issuance handoff
                        </p>
                        <p
                            class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400"
                        >
                            Read-only destinations for the generated Pay Code.
                            Automatic redirect:
                            {{
                                postIssuanceNavigation?.auto_redirect === true
                                    ? 'enabled'
                                    : 'disabled'
                            }}.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800"
                    >
                        {{ displayValue(postIssuanceNavigation?.status) }}
                    </span>
                </div>

                <div class="mt-3 grid gap-2">
                    <a
                        v-for="item in postIssuanceNavigationItems"
                        :key="String(item.key ?? item.label ?? 'post-issuance')"
                        :href="
                            item.enabled === true && item.href
                                ? item.href
                                : undefined
                        "
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 aria-disabled:cursor-not-allowed aria-disabled:opacity-60 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-emerald-900 dark:hover:text-emerald-300"
                        :aria-disabled="
                            item.enabled === true && item.href
                                ? undefined
                                : 'true'
                        "
                        :data-testid="`cockpit-quick-generate-post-issuance-link-${item.key}`"
                    >
                        {{ item.label }}
                        <span
                            class="ml-2 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ item.status }} ·
                            {{
                                item.read_only === true
                                    ? 'read-only'
                                    : 'mutation'
                            }}
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
                        <p
                            class="font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Draft runtime
                        </p>
                        <span
                            class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800"
                        >
                            {{ displayValue(draftRuntime.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Factory
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{ displayValue(draftRuntime.factory) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Compiler
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
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
                        <p
                            class="font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Activity runtime
                        </p>
                        <span
                            class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800"
                        >
                            {{ displayValue(activityRuntime.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Schema
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{ displayValue(activityRuntime.schema) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Presentation only
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    activityRuntime.presentation_only === true
                                        ? 'yes'
                                        : 'no'
                                }}
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
                        <p
                            class="font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Pricing preflight
                        </p>
                        <span
                            class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800"
                        >
                            {{ displayValue(pricingPreflight.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Total
                            </dt>
                            <dd
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{
                                    displayValue(
                                        pricingPreflight.currency,
                                        'PHP',
                                    )
                                }}
                                {{ displayValue(pricingPreflight.total, '0') }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Base fee
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    displayValue(pricingPreflight.base_fee, '0')
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Blocking
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    pricingPreflight.blocking === true
                                        ? 'yes'
                                        : 'no'
                                }}
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
                        <p
                            class="font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Funding preflight
                        </p>
                        <span
                            class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-950 dark:text-slate-300 dark:ring-slate-800"
                        >
                            {{ displayValue(fundingPreflight.status) }}
                        </span>
                    </div>
                    <dl class="mt-3 grid gap-2">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Authority
                            </dt>
                            <dd
                                class="font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ displayValue(fundingPreflight.authority) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Balance
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    displayValue(
                                        fundingPreflight.authoritative
                                            ?.currency,
                                        'PHP',
                                    )
                                }}
                                {{
                                    displayValue(
                                        fundingPreflight.authoritative?.balance,
                                        'not available',
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">
                                Sync
                            </dt>
                            <dd
                                class="font-medium text-slate-700 dark:text-slate-200"
                            >
                                {{ displayValue(fundingPreflight.sync_status) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </form>
</template>
