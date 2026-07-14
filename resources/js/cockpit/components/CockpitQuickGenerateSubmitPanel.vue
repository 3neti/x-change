<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

type VoucherInputFieldOption = {
    value: string;
    label: string;
    helper: string;
};

type CashTypeOption = {
    value: string;
    label: string;
    helper: string;
};

type MandateOption = {
    value: string;
    label: string;
    helper: string;
};

type QuickGenerateTemplateDefaults = {
    amount: string;
    currency: string;
    count: string;
    payee: string;
    purpose: string;
    inputFields: string[];
    expiryPreset: 'none' | 'P12H' | 'P1D' | 'P3D' | 'P7D' | 'custom';
    requireMobileValidation: boolean;
    requirePayableValidation: boolean;
    requireCountryValidation: boolean;
    verificationKyc: boolean;
    verificationOtp: boolean;
    verificationSelfie: boolean;
    feedbackMobile: string;
    feedbackEmail: string;
    feedbackWebhook: string;
    riderUrl: string;
    riderSplash: string;
    riderSplashTimeout: string;
    sliceMode: 'whole' | 'fixed' | 'open';
    maxSlices: string;
    minWithdrawal: string;
    voucherType: 'redeemable' | 'payable' | 'settlement';
    targetAmount: string;
    includeExecutionInstruction: boolean;
    executionDriver: string;
};

const voucherInputFieldOptions: VoucherInputFieldOption[] = [
    {
        value: 'signature',
        label: 'Signature',
        helper: 'Beneficiary signature evidence.',
    },
    {
        value: 'selfie',
        label: 'Selfie Photo',
        helper: 'Beneficiary selfie evidence.',
    },
    {
        value: 'location',
        label: 'Location',
        helper: 'Location capture during claim.',
    },
    {
        value: 'otp',
        label: 'OTP',
        helper: 'One-time-passcode input.',
    },
    {
        value: 'kyc',
        label: 'KYC',
        helper: 'Identity verification evidence.',
    },
    {
        value: 'reference_code',
        label: 'Reference Code',
        helper: 'External or branch-provided claim reference.',
    },
    {
        value: 'name',
        label: 'Full Name',
        helper: 'Beneficiary legal or display name.',
    },
    {
        value: 'address',
        label: 'Address',
        helper: 'Beneficiary address details.',
    },
    {
        value: 'birth_date',
        label: 'Birthdate',
        helper: 'Beneficiary birth date.',
    },
    {
        value: 'gross_monthly_income',
        label: 'Gross Monthly Income',
        helper: 'Financial profile input where required.',
    },
    {
        value: 'mobile',
        label: 'Mobile Number',
        helper: 'Beneficiary mobile or GCash-style reference.',
    },
    {
        value: 'email',
        label: 'Email Address',
        helper: 'Beneficiary email collected during claim.',
    },
];

const voucherInputFieldPayloadOrder = [
    'mobile',
    'email',
    'reference_code',
    'signature',
    'kyc',
    'name',
    'address',
    'birth_date',
    'gross_monthly_income',
    'location',
    'otp',
    'selfie',
];

const cashTypeOptions: CashTypeOption[] = [
    {
        value: 'default',
        label: 'Default cash contract',
        helper: 'Omit cash.type and let the voucher package use its default cash behavior.',
    },
    {
        value: 'cash',
        label: 'Cash',
        helper: 'Standard claimable cash Pay Code.',
    },
    {
        value: 'disbursement',
        label: 'Disbursement',
        helper: 'Operator-issued payout contract for a known beneficiary or route.',
    },
    {
        value: 'claimable_cash',
        label: 'Claimable cash',
        helper: 'Explicit claim-first cash contract.',
    },
    {
        value: 'settlement_cash',
        label: 'Settlement cash',
        helper: 'Settlement-oriented cash contract; execution still remains voucher-owned.',
    },
    {
        value: 'custom',
        label: 'Custom key',
        helper: 'Use only when an upstream contract defines the cash type key.',
    },
];

const mandateOptions: MandateOption[] = [
    {
        value: 'branch-release',
        label: 'Branch release',
        helper: 'Operator or branch counter release is required.',
    },
    {
        value: 'counter-check',
        label: 'Counter check',
        helper: 'Counter staff must verify the Pay Code before release.',
    },
    {
        value: 'kyc-required',
        label: 'KYC required',
        helper: 'Identity evidence is expected before execution.',
    },
    {
        value: 'otp-required',
        label: 'OTP required',
        helper: 'One-time passcode verification is expected.',
    },
    {
        value: 'manual-review',
        label: 'Manual review',
        helper: 'A human review step is expected before completion.',
    },
    {
        value: 'recipient-match',
        label: 'Recipient match',
        helper: 'Claim data should match the intended recipient.',
    },
    {
        value: 'settlement-readiness',
        label: 'Settlement readiness',
        helper: 'Settlement readiness must be confirmed before execution.',
    },
];

const quickGenerateTemplateDefaults: Record<
    string,
    QuickGenerateTemplateDefaults
> = {
    'money-changer': {
        amount: '25',
        currency: 'PHP',
        count: '1',
        payee: 'CASH',
        purpose: 'Branch counter cash-out',
        inputFields: ['reference_code'],
        expiryPreset: 'P1D',
        requireMobileValidation: false,
        requirePayableValidation: false,
        requireCountryValidation: true,
        verificationKyc: false,
        verificationOtp: false,
        verificationSelfie: false,
        feedbackMobile: '',
        feedbackEmail: '',
        feedbackWebhook: '',
        riderUrl: '',
        riderSplash: 'Present this Pay Code at the counter.',
        riderSplashTimeout: '3',
        sliceMode: 'whole',
        maxSlices: '1',
        minWithdrawal: '0',
        voucherType: 'redeemable',
        targetAmount: '',
        includeExecutionInstruction: false,
        executionDriver: 'default',
    },
    'ofw-remittance': {
        amount: '500',
        currency: 'PHP',
        count: '1',
        payee: '09170000000',
        purpose: 'OFW remittance payout',
        inputFields: ['mobile', 'name', 'reference_code'],
        expiryPreset: 'P3D',
        requireMobileValidation: true,
        requirePayableValidation: false,
        requireCountryValidation: true,
        verificationKyc: true,
        verificationOtp: true,
        verificationSelfie: false,
        feedbackMobile: '09170000000',
        feedbackEmail: '',
        feedbackWebhook: '',
        riderUrl: '',
        riderSplash: 'Your remittance Pay Code is ready.',
        riderSplashTimeout: '5',
        sliceMode: 'whole',
        maxSlices: '1',
        minWithdrawal: '0',
        voucherType: 'redeemable',
        targetAmount: '',
        includeExecutionInstruction: false,
        executionDriver: 'default',
    },
    'settlement-envelope': {
        amount: '1000',
        currency: 'PHP',
        count: '1',
        payee: 'CASH',
        purpose: 'Settlement envelope readiness check',
        inputFields: ['name', 'signature', 'kyc'],
        expiryPreset: 'P7D',
        requireMobileValidation: false,
        requirePayableValidation: false,
        requireCountryValidation: true,
        verificationKyc: true,
        verificationOtp: false,
        verificationSelfie: true,
        feedbackMobile: '',
        feedbackEmail: '',
        feedbackWebhook: '',
        riderUrl: '',
        riderSplash: 'Settlement envelope requires readiness approval.',
        riderSplashTimeout: '5',
        sliceMode: 'open',
        maxSlices: '3',
        minWithdrawal: '100',
        voucherType: 'settlement',
        targetAmount: '1000',
        includeExecutionInstruction: true,
        executionDriver: 'settlement_envelope',
    },
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
const selectedInputFieldValues = ref<string[]>(['mobile']);
const validationSecret = ref('');
const requireMobileValidation = ref(true);
const requirePayableValidation = ref(false);
const requireCountryValidation = ref(false);
const requireLocationValidation = ref(false);
const verificationKyc = ref(false);
const verificationOtp = ref(false);
const verificationSelfie = ref(false);
const signatureRequired = ref(false);
const signatureFailure = ref<'block' | 'warn'>('block');
const selfieFailure = ref<'block' | 'warn'>('block');
const otpFailure = ref<'block' | 'warn'>('block');
const faceMatchRequired = ref(false);
const faceMatchFailure = ref<'block' | 'warn'>('block');
const faceMatchConfidence = ref('0.75');
const timeValidationEnabled = ref(false);
const timeWindowStart = ref('09:00');
const timeWindowEnd = ref('17:00');
const timeWindowTimezone = ref('Asia/Manila');
const timeLimitMinutes = ref('10');
const timeTrackDuration = ref(true);
const riderUrl = ref('');
const riderRedirectTimeout = ref('');
const riderSplash = ref('');
const riderSplashTimeout = ref('3');
const riderSplashMetaSanitized = ref(true);
const riderSplashMetaProfile = ref('');
const riderOgSource = ref('');
const feedbackEmail = ref('');
const feedbackMobile = ref(recipientReference.value);
const feedbackWebhook = ref('');
const prefix = ref('');
const mask = ref('');
const ttl = ref('');
const expiryPreset = ref<'none' | 'P12H' | 'P1D' | 'P3D' | 'P7D' | 'custom'>(
    'none',
);
const expiryCustomDays = ref('');
const startsAt = ref('');
const expiresAt = ref('');
const settlementRail = ref('');
const feeStrategy = ref<'absorb' | 'include' | 'add'>('absorb');
const cashType = ref('default');
const customCashType = ref('');
const selectedMandates = ref<string[]>([]);
const customMandates = ref('');
const sliceMode = ref<'whole' | 'fixed' | 'open'>('whole');
const slices = ref('1');
const maxSlices = ref('2');
const minWithdrawal = ref('0');
const voucherType = ref<'redeemable' | 'payable' | 'settlement'>('redeemable');
const targetAmount = ref('');
const rulesMinPayment = ref('');
const rulesMaxPayment = ref('');
const rulesAllowOverpayment = ref(false);
const rulesAutoCloseOnFullPayment = ref(true);
const includeExecutionInstruction = ref(false);
const executionSchema = ref('voucher.execution.v1');
const executionDriver = ref('default');
const executionMode = ref('');
const executionPipeline = ref('');
const executionFallback = ref('');
const executionVisibility = ref('');
const executionMetadata = ref('');
const metadataFlowType = ref('');
const metadataIssuerId = ref('');
const metadataCollectionWalletId = ref('');
const processing = ref(false);
const lastStatus = ref('ready');
const lastMessage = ref(
    'Submit will call the existing x-change issuance handoff route.',
);
const lastResponse = ref<Record<string, unknown> | null>(null);

watch(selectedTemplate, (templateKey): void => {
    applyTemplateDefaults(templateKey);
});

function applyTemplateDefaults(templateKey: string): void {
    const defaults = quickGenerateTemplateDefaults[templateKey];

    if (!defaults) {
        return;
    }

    amount.value = defaults.amount;
    currency.value = defaults.currency;
    count.value = defaults.count;
    recipientReference.value = defaults.payee;
    purpose.value = defaults.purpose;
    selectedInputFieldValues.value = [...defaults.inputFields];
    expiryPreset.value = defaults.expiryPreset;
    expiryCustomDays.value = '';
    ttl.value = '';
    requireMobileValidation.value = defaults.requireMobileValidation;
    requirePayableValidation.value = defaults.requirePayableValidation;
    requireCountryValidation.value = defaults.requireCountryValidation;
    verificationKyc.value = defaults.verificationKyc;
    verificationOtp.value = defaults.verificationOtp;
    verificationSelfie.value = defaults.verificationSelfie;
    feedbackMobile.value = defaults.feedbackMobile;
    feedbackEmail.value = defaults.feedbackEmail;
    feedbackWebhook.value = defaults.feedbackWebhook;
    riderUrl.value = defaults.riderUrl;
    riderSplash.value = defaults.riderSplash;
    riderSplashTimeout.value = defaults.riderSplashTimeout;
    cashType.value =
        defaults.executionDriver === 'settlement_envelope'
            ? 'settlement_cash'
            : 'default';
    customCashType.value = '';
    selectedMandates.value =
        defaults.executionDriver === 'settlement_envelope'
            ? ['settlement-readiness', 'manual-review']
            : [];
    customMandates.value = '';
    sliceMode.value = defaults.sliceMode;
    maxSlices.value = defaults.maxSlices;
    minWithdrawal.value = defaults.minWithdrawal;
    voucherType.value = defaults.voucherType;
    targetAmount.value = defaults.targetAmount;
    includeExecutionInstruction.value = defaults.includeExecutionInstruction;
    executionDriver.value = defaults.executionDriver;
    executionPipeline.value =
        defaults.executionDriver === 'settlement_envelope'
            ? 'readiness, authorize, execute'
            : '';
    metadataFlowType.value = templateKey;
    lastStatus.value = 'ready';
    lastMessage.value = `${selectedTemplateName.value} defaults applied. Submit will call the existing x-change issuance handoff route.`;
    lastResponse.value = null;
}

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

const normalizedPayee = computed<string>(() => {
    const normalized = recipientReference.value.trim();

    return normalized.toUpperCase() === 'CASH' ? '' : normalized;
});

const payeeType = computed<'anyone' | 'mobile' | 'vendor'>(() => {
    const normalized = recipientReference.value.trim();

    if (normalized === '' || normalized.toUpperCase() === 'CASH') {
        return 'anyone';
    }

    if (/^(\+|09|639|63)/.test(normalized)) {
        return 'mobile';
    }

    return 'vendor';
});

const payeeHelpText = computed<string>(() => {
    if (payeeType.value === 'mobile') {
        return `Restricted to mobile number: ${normalizedPayee.value}`;
    }

    if (payeeType.value === 'vendor') {
        return `Restricted to vendor alias: ${normalizedPayee.value}`;
    }

    return 'Blank or CASH means anyone can claim subject to the other validation gates.';
});

const effectiveTtl = computed<string>(() => {
    const advancedTtl = ttl.value.trim();

    if (advancedTtl !== '') {
        return advancedTtl;
    }

    if (expiryPreset.value === 'custom') {
        const customDays = Number(expiryCustomDays.value);

        return Number.isFinite(customDays) && customDays > 0
            ? `P${Math.round(customDays)}D`
            : '';
    }

    return expiryPreset.value === 'none' ? '' : expiryPreset.value;
});

const effectiveExpiry = computed<{
    source: 'none' | 'preset' | 'ttl_override' | 'absolute_expires_at';
    label: string;
    payload: Record<string, string>;
}>(() => {
    const absoluteExpiresAt = expiresAt.value.trim();

    if (absoluteExpiresAt !== '') {
        return {
            source: 'absolute_expires_at',
            label: `Absolute expiry: ${absoluteExpiresAt}`,
            payload: { expires_at: absoluteExpiresAt },
        };
    }

    const advancedTtl = ttl.value.trim();

    if (advancedTtl !== '') {
        return {
            source: 'ttl_override',
            label: `Raw TTL override: ${advancedTtl}`,
            payload: { ttl: advancedTtl },
        };
    }

    if (effectiveTtl.value !== '') {
        return {
            source: 'preset',
            label: `Expiry preset: ${effectiveTtl.value}`,
            payload: { ttl: effectiveTtl.value },
        };
    }

    return {
        source: 'none',
        label: 'No expiry payload will be submitted.',
        payload: {},
    };
});

const illustrativeFee = computed<number>(() => {
    const normalizedAmount = Number(amount.value);

    if (!Number.isFinite(normalizedAmount) || normalizedAmount <= 0) {
        return 0;
    }

    if (settlementRail.value === 'PESONET') {
        return 25;
    }

    return 10;
});

const feeStrategyLabel = computed<string>(() => {
    if (feeStrategy.value === 'include') {
        return 'Include fee inside Pay Code amount';
    }

    if (feeStrategy.value === 'add') {
        return 'Add fee on top of amount';
    }

    return 'Issuer absorbs fee';
});

const feeStrategyPreview = computed<{
    recipientAmount: string;
    issuerCost: string;
    note: string;
}>(() => {
    const normalizedAmount = Number(amount.value);
    const safeAmount = Number.isFinite(normalizedAmount)
        ? Math.max(normalizedAmount, 0)
        : 0;
    const fee = illustrativeFee.value;

    if (feeStrategy.value === 'include') {
        return {
            recipientAmount: formatMoney(Math.max(safeAmount - fee, 0)),
            issuerCost: formatMoney(safeAmount),
            note: 'Illustrative only: fee is deducted from the visible amount.',
        };
    }

    if (feeStrategy.value === 'add') {
        return {
            recipientAmount: formatMoney(safeAmount),
            issuerCost: formatMoney(safeAmount + fee),
            note: 'Illustrative only: fee is added to the issuer cost.',
        };
    }

    return {
        recipientAmount: formatMoney(safeAmount),
        issuerCost: formatMoney(safeAmount + fee),
        note: 'Illustrative only: issuer absorbs the estimated fee.',
    };
});

const effectiveCashType = computed<string>(() => {
    if (cashType.value === 'custom') {
        return customCashType.value.trim();
    }

    if (cashType.value === 'default') {
        return '';
    }

    return cashType.value;
});

const cashTypeHelper = computed<string>(() => {
    return (
        cashTypeOptions.find((option) => option.value === cashType.value)
            ?.helper ?? 'Select how cash.type should be represented.'
    );
});

const effectiveMandates = computed<string[]>(() => {
    const mandates = customMandates.value
        .split(',')
        .map((mandate) => mandate.trim())
        .filter((mandate) => mandate.length > 0);

    return [...new Set([...selectedMandates.value, ...mandates])];
});

const effectiveMandatesDisplay = computed<string>(() => {
    if (effectiveMandates.value.length === 0) {
        return 'No mandates selected';
    }

    return effectiveMandates.value.join(', ');
});

const selectedInputFields = computed<string[]>(() => {
    const fields = new Set(selectedInputFieldValues.value);

    if (payeeType.value === 'mobile') {
        fields.add('mobile');
    }

    if (feedbackEmail.value.trim() !== '') {
        fields.add('email');
    }

    return voucherInputFieldPayloadOrder.filter((field) => fields.has(field));
});

const validationSummary = computed<Record<string, unknown>>(() => {
    const secret = validationSecret.value.trim();

    return {
        ...(secret === '' ? {} : { secret }),
        ...(requireMobileValidation.value && payeeType.value === 'mobile'
            ? { mobile: normalizedPayee.value }
            : {}),
        ...(payeeType.value === 'vendor'
            ? { payable: normalizedPayee.value }
            : {}),
        ...(requirePayableValidation.value && payeeType.value === 'anyone'
            ? { payable: 'required' }
            : {}),
        ...(requireCountryValidation.value ? { country: 'PH' } : {}),
        ...(requireLocationValidation.value
            ? { location: 'required', radius: '100' }
            : {}),
        ...(verificationOtp.value ? { mobile_verification: 'otp' } : {}),
    };
});

const structuredValidationSummary = computed<Record<string, unknown>>(() => {
    const confidence = Number(faceMatchConfidence.value);
    const limitMinutes = Number(timeLimitMinutes.value);

    return {
        ...(signatureRequired.value
            ? {
                  signature: {
                      required: true,
                      on_failure: signatureFailure.value,
                  },
              }
            : {}),
        ...(verificationSelfie.value
            ? {
                  selfie: {
                      required: true,
                      on_failure: selfieFailure.value,
                  },
              }
            : {}),
        ...(requireLocationValidation.value
            ? {
                  location: {
                      required: true,
                      target_lat: 0,
                      target_lng: 0,
                      radius_meters: 100,
                      on_failure: 'block',
                  },
              }
            : {}),
        ...(verificationOtp.value
            ? {
                  otp: {
                      required: true,
                      on_failure: otpFailure.value,
                  },
              }
            : {}),
        ...(faceMatchRequired.value
            ? {
                  face_match: {
                      required: true,
                      on_failure: faceMatchFailure.value,
                      min_confidence: Number.isFinite(confidence)
                          ? confidence
                          : 0.75,
                  },
              }
            : {}),
        ...(timeValidationEnabled.value
            ? {
                  time: {
                      window: {
                          start_time: timeWindowStart.value,
                          end_time: timeWindowEnd.value,
                          timezone: timeWindowTimezone.value,
                      },
                      limit_minutes: Number.isFinite(limitMinutes)
                          ? limitMinutes
                          : null,
                      track_duration: timeTrackDuration.value,
                  },
              }
            : {}),
    };
});

const validationPreviewLabels: Record<string, string> = {
    secret: 'secret configured',
    mobile: 'match mobile number',
    payable: 'require payable / vendor alias',
    country: 'country: PH',
    location: 'location radius',
    radius: 'radius: 100',
    mobile_verification: 'OTP mobile verification',
};

const structuredValidationPreviewLabels: Record<string, string> = {
    signature: 'signature required',
    selfie: 'selfie required',
    location: 'location evidence required',
    otp: 'OTP required',
    face_match: 'face match required',
    time: 'claim time window',
};

const validationPreviewDisplay = computed<string>(() => {
    const labels = Object.keys(validationSummary.value).map(
        (key) => validationPreviewLabels[key] ?? key,
    );

    if (labels.length === 0) {
        return 'No cash validation rules selected';
    }

    return [...new Set(labels)].join(', ');
});

const structuredValidationPreviewDisplay = computed<string>(() => {
    const labels = Object.keys(structuredValidationSummary.value).map(
        (key) => structuredValidationPreviewLabels[key] ?? key,
    );

    if (labels.length === 0) {
        return 'No structured verification rules selected';
    }

    return labels.join(', ');
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
    const redirectTimeout = Number(riderRedirectTimeout.value);
    const splash = riderSplash.value.trim();
    const timeout = Number(riderSplashTimeout.value);
    const htmlProfile = riderSplashMetaProfile.value.trim();
    const ogSource = riderOgSource.value.trim();

    return {
        message: message === '' ? null : message,
        url: url === '' ? null : url,
        redirect_timeout:
            Number.isFinite(redirectTimeout) && redirectTimeout >= 0
                ? redirectTimeout
                : null,
        splash: splash === '' ? null : splash,
        splash_timeout:
            Number.isFinite(timeout) && timeout > 0 ? timeout : null,
        splash_meta:
            riderSplashMetaSanitized.value || htmlProfile !== ''
                ? {
                      sanitized: riderSplashMetaSanitized.value,
                      ...(htmlProfile === ''
                          ? {}
                          : { html_profile: htmlProfile }),
                  }
                : null,
        og_source: ogSource === '' ? null : ogSource,
    };
});

const sliceSummary = computed<Record<string, unknown>>(() => {
    const fixed = Number(slices.value);
    const max = Number(maxSlices.value);
    const minimum = Number(minWithdrawal.value);

    if (sliceMode.value !== 'open') {
        return {
            mode: sliceMode.value,
            slices:
                sliceMode.value === 'fixed' &&
                Number.isFinite(fixed) &&
                fixed > 0
                    ? Math.round(fixed)
                    : null,
            max_slices: sliceMode.value === 'whole' ? 1 : null,
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
                label: 'Payee',
                value:
                    payeeType.value === 'anyone'
                        ? 'anyone'
                        : `${payeeType.value}: ${normalizedPayee.value}`,
            },
            {
                label: 'Expiry',
                value: effectiveTtl.value === '' ? 'none' : effectiveTtl.value,
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

const settlementRulesSummary = computed<Record<string, unknown> | null>(() => {
    const minPayment = Number(rulesMinPayment.value);
    const maxPayment = Number(rulesMaxPayment.value);
    const rules: Record<string, unknown> = {};

    if (Number.isFinite(minPayment) && minPayment > 0) {
        rules.min_payment = minPayment;
    }

    if (Number.isFinite(maxPayment) && maxPayment > 0) {
        rules.max_payment = maxPayment;
    }

    if (rulesAllowOverpayment.value) {
        rules.allow_overpayment = true;
    }

    if (rulesAutoCloseOnFullPayment.value) {
        rules.auto_close_on_full_payment = true;
    }

    return Object.keys(rules).length > 0 ? rules : null;
});

const executionSummary = computed<Record<string, unknown> | null>(() => {
    if (!includeExecutionInstruction.value) {
        return null;
    }

    const pipeline = executionPipeline.value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
    const visibility = executionVisibility.value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item.length > 0);
    const metadata = executionMetadata.value.trim();

    return {
        schema: executionSchema.value.trim() || 'voucher.execution.v1',
        driver: executionDriver.value.trim() || 'default',
        ...(executionMode.value.trim() === ''
            ? {}
            : { mode: executionMode.value.trim() }),
        ...(pipeline.length === 0 ? {} : { pipeline }),
        ...(executionFallback.value.trim() === ''
            ? {}
            : { fallback: executionFallback.value.trim() }),
        ...(visibility.length === 0 ? {} : { visibility }),
        ...(metadata === ''
            ? {}
            : {
                  metadata: {
                      operator_note: metadata,
                  },
              }),
    };
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
    const normalizedSlices = Number(sliceSummary.value.slices);
    const normalizedMaxSlices = Number(sliceSummary.value.max_slices);
    const normalizedMinWithdrawal = Number(sliceSummary.value.min_withdrawal);
    const cash: Record<string, unknown> = {
        amount: Number.isFinite(normalizedAmount)
            ? normalizedAmount
            : amount.value,
        currency: currency.value.trim() || 'PHP',
        validation: validationSummary.value,
    };

    if (settlementRail.value.trim() !== '') {
        cash.settlement_rail = settlementRail.value.trim();
    }

    cash.fee_strategy = feeStrategy.value;

    if (effectiveCashType.value !== '') {
        cash.type = effectiveCashType.value;
    }

    if (effectiveMandates.value.length > 0) {
        cash.mandates = effectiveMandates.value;
    }

    if (sliceMode.value === 'fixed') {
        cash.slice_mode = 'fixed';
        cash.slices = Number.isFinite(normalizedSlices) ? normalizedSlices : 1;
    }

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

    const payload: Record<string, unknown> = {
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
        validation: structuredValidationSummary.value,
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

    if (prefix.value.trim() !== '') {
        payload.prefix = prefix.value.trim();
    }

    if (mask.value.trim() !== '') {
        payload.mask = mask.value.trim();
    }

    if (startsAt.value.trim() !== '') {
        payload.starts_at = startsAt.value.trim();
    }

    if (effectiveExpiry.value.payload.ttl) {
        payload.ttl = effectiveExpiry.value.payload.ttl;
    }

    if (effectiveExpiry.value.payload.expires_at) {
        payload.expires_at = effectiveExpiry.value.payload.expires_at;
    }

    if (voucherType.value !== 'redeemable') {
        payload.voucher_type = voucherType.value;
    }

    const normalizedTargetAmount = Number(targetAmount.value);

    if (Number.isFinite(normalizedTargetAmount) && normalizedTargetAmount > 0) {
        payload.target_amount = normalizedTargetAmount;
    }

    if (settlementRulesSummary.value !== null) {
        payload.rules = settlementRulesSummary.value;
    }

    if (executionSummary.value !== null) {
        payload.execution = executionSummary.value;
    }

    const flowType = metadataFlowType.value.trim();
    const issuerId = metadataIssuerId.value.trim();
    const collectionWalletId = metadataCollectionWalletId.value.trim();

    if (flowType !== '') {
        (payload.metadata as Record<string, unknown>).flow_type = flowType;
    }

    if (issuerId !== '') {
        (payload.metadata as Record<string, unknown>).issuer_id = issuerId;
    }

    if (collectionWalletId !== '') {
        (payload.metadata as Record<string, unknown>).collection_wallet_id =
            collectionWalletId;
    }

    return payload;
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

function formatMoney(value: number): string {
    return `${currency.value.trim() || 'PHP'} ${value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
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
                                Money, payee, and expiry
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                CreateV2-inspired primary controls for amount,
                                payee validation, expiry, template, and
                                quantity.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
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
                            <div class="flex min-w-0 rounded-xl shadow-sm">
                                <span
                                    class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                >
                                    ₱
                                </span>
                                <input
                                    v-model="amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    class="w-full min-w-0 border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-submit-amount"
                                    :disabled="processing"
                                />
                                <input
                                    v-model="currency"
                                    type="text"
                                    class="w-24 min-w-0 rounded-r-xl border border-l-0 border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                    data-testid="cockpit-quick-generate-submit-currency"
                                    :disabled="processing"
                                />
                            </div>
                        </label>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Payee
                            <input
                                v-model="recipientReference"
                                type="text"
                                placeholder="CASH, 0917..., or vendor alias"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-recipient"
                                :disabled="processing"
                            />
                            <span
                                class="text-[11px] font-normal text-slate-500 dark:text-slate-400"
                                data-testid="cockpit-quick-generate-payee-help"
                            >
                                {{ payeeHelpText }}
                            </span>
                        </label>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Expiry
                            <select
                                v-model="expiryPreset"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-expiry-preset"
                                :disabled="processing"
                            >
                                <option value="none">No preset expiry</option>
                                <option value="P12H">12 hours</option>
                                <option value="P1D">1 day</option>
                                <option value="P3D">3 days</option>
                                <option value="P7D">7 days</option>
                                <option value="custom">Custom days</option>
                            </select>
                            <span
                                class="text-[11px] font-normal text-slate-500 dark:text-slate-400"
                            >
                                Advanced TTL overrides this preset when filled.
                            </span>
                        </label>

                        <label
                            v-if="expiryPreset === 'custom'"
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Custom expiry days
                            <input
                                v-model="expiryCustomDays"
                                type="number"
                                min="1"
                                step="1"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-expiry-custom-days"
                                :disabled="processing"
                            />
                        </label>
                    </div>
                    <details
                        class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        data-testid="cockpit-quick-generate-generation-advanced"
                    >
                        <summary
                            class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Advanced contract controls
                        </summary>
                        <div
                            class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                            data-testid="cockpit-quick-generate-effective-expiry"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div>
                                    <p class="font-semibold">
                                        Effective expiry
                                    </p>
                                    <p class="mt-1">
                                        {{ effectiveExpiry.label }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-800 shadow-sm dark:bg-amber-900/60 dark:text-amber-100"
                                >
                                    {{ effectiveExpiry.source }}
                                </span>
                            </div>
                            <p
                                class="mt-2 text-[11px] text-amber-800 dark:text-amber-200"
                            >
                                Precedence: Exact expires at wins over raw TTL;
                                raw TTL wins over the primary expiry preset.
                                When exact expiry is set, TTL is not submitted.
                            </p>
                        </div>
                        <div
                            class="mt-3 grid grid-cols-1 items-start gap-3 lg:grid-cols-3"
                        >
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Prefix</span>
                                <input
                                    v-model="prefix"
                                    type="text"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-prefix"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Optional Pay Code prefix.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Mask</span>
                                <input
                                    v-model="mask"
                                    type="text"
                                    placeholder="****"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-mask"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Optional generated-code mask.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none"
                                    >Raw TTL override</span
                                >
                                <input
                                    v-model="ttl"
                                    type="text"
                                    placeholder="P1D"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-ttl"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    ISO-8601 duration override. Example: P1D or
                                    PT12H.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Starts at</span>
                                <input
                                    v-model="startsAt"
                                    type="datetime-local"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-starts-at"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Optional activation timestamp.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none"
                                    >Exact expires at</span
                                >
                                <input
                                    v-model="expiresAt"
                                    type="datetime-local"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-expires-at"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Absolute expiry. When filled, it dominates
                                    TTL and expiry preset.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none"
                                    >Settlement rail</span
                                >
                                <select
                                    v-model="settlementRail"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-settlement-rail"
                                    :disabled="processing"
                                >
                                    <option value="">Default</option>
                                    <option value="INSTAPAY">INSTAPAY</option>
                                    <option value="PESONET">PESONET</option>
                                </select>
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Optional routing hint; providers are not
                                    called here.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Fee strategy</span>
                                <select
                                    v-model="feeStrategy"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-fee-strategy"
                                    :disabled="processing"
                                >
                                    <option value="absorb">
                                        Absorb — issuer pays fee
                                    </option>
                                    <option value="include">
                                        Include — fee comes from amount
                                    </option>
                                    <option value="add">
                                        Add — fee added on top
                                    </option>
                                </select>
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Controls operator-visible fee interpretation
                                    only.
                                </span>
                            </label>
                            <div
                                class="grid gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-950 lg:col-span-3 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-100"
                                data-testid="cockpit-quick-generate-fee-preview"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <p class="font-semibold">
                                        {{ feeStrategyLabel }}
                                    </p>
                                    <span
                                        class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-800 shadow-sm dark:bg-emerald-900/60 dark:text-emerald-100"
                                    >
                                        {{ feeStrategy }}
                                    </span>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <div>
                                        <p
                                            class="text-[11px] tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                        >
                                            Estimated fee
                                        </p>
                                        <p class="font-semibold">
                                            {{ formatMoney(illustrativeFee) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-[11px] tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                        >
                                            Recipient amount
                                        </p>
                                        <p class="font-semibold">
                                            {{
                                                feeStrategyPreview.recipientAmount
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-[11px] tracking-wide text-emerald-700 uppercase dark:text-emerald-300"
                                        >
                                            Issuer cost
                                        </p>
                                        <p class="font-semibold">
                                            {{ feeStrategyPreview.issuerCost }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    class="text-[11px] text-emerald-700 dark:text-emerald-300"
                                >
                                    {{ feeStrategyPreview.note }} No pricing or
                                    provider quote service is called by this
                                    preview.
                                </p>
                            </div>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Cash type</span>
                                <select
                                    v-model="cashType"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-cash-type"
                                    :disabled="processing"
                                >
                                    <option
                                        v-for="option in cashTypeOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                    data-testid="cockpit-quick-generate-cash-type-helper"
                                >
                                    {{ cashTypeHelper }}
                                </span>
                            </label>
                            <label
                                v-if="cashType === 'custom'"
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none"
                                    >Custom cash type key</span
                                >
                                <input
                                    v-model="customCashType"
                                    type="text"
                                    placeholder="custom_cash_key"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-custom-cash-type"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Submitted as cash.type when filled.
                                </span>
                            </label>
                            <div
                                class="grid min-w-0 gap-2 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-700 lg:col-span-3 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                data-testid="cockpit-quick-generate-mandate-options"
                            >
                                <div>
                                    <p
                                        class="leading-none font-semibold text-slate-800 dark:text-slate-100"
                                    >
                                        Mandates
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        Choose expected contract obligations.
                                        These are submitted as cash.mandates;
                                        Cockpit does not enforce them directly.
                                    </p>
                                </div>
                                <div class="grid gap-2 md:grid-cols-2">
                                    <label
                                        v-for="option in mandateOptions"
                                        :key="option.value"
                                        class="flex min-w-0 items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-950"
                                    >
                                        <input
                                            v-model="selectedMandates"
                                            type="checkbox"
                                            :value="option.value"
                                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600"
                                            :data-testid="`cockpit-quick-generate-mandate-${option.value}`"
                                            :disabled="processing"
                                        />
                                        <span class="grid gap-0.5">
                                            <span
                                                class="text-xs font-semibold text-slate-800 dark:text-slate-100"
                                            >
                                                {{ option.label }}
                                            </span>
                                            <span
                                                class="text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                            >
                                                {{ option.helper }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <label
                                    class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    <span class="leading-none"
                                        >Additional mandate keys</span
                                    >
                                    <input
                                        v-model="customMandates"
                                        type="text"
                                        placeholder="comma-separated custom keys"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-mandates"
                                        :disabled="processing"
                                    />
                                    <span
                                        class="min-h-8 text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                    >
                                        Optional escape hatch for contract keys
                                        not listed above.
                                    </span>
                                </label>
                                <details
                                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950"
                                    data-testid="cockpit-quick-generate-mandates-preview"
                                >
                                    <summary
                                        class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        Mandates payload preview
                                    </summary>
                                    <div class="mt-2 grid gap-2">
                                        <p
                                            class="text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                                        >
                                            Reactive comma-delimited preview of
                                            the exact mandate keys that will be
                                            submitted as
                                            <code>cash.mandates</code>.
                                        </p>
                                        <div
                                            class="rounded-lg border border-slate-200 bg-white p-3 font-mono text-xs break-words text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                                            data-testid="cockpit-quick-generate-mandates-preview-value"
                                        >
                                            {{ effectiveMandatesDisplay }}
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </details>
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
                        <div
                            class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3"
                            data-testid="cockpit-quick-generate-input-fields"
                        >
                            <label
                                v-for="field in voucherInputFieldOptions"
                                :key="field.value"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                            >
                                <input
                                    v-model="selectedInputFieldValues"
                                    type="checkbox"
                                    :value="field.value"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                <span>
                                    <span class="block">{{ field.label }}</span>
                                    <span
                                        class="mt-0.5 block text-[11px] font-normal text-slate-500 dark:text-slate-400"
                                    >
                                        {{ field.value }} · {{ field.helper }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    data-testid="cockpit-quick-generate-validation-section"
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
                                Basic claim checks map to cash validation.
                                Evidence and advanced policies map to
                                verification intent.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <div
                            class="grid gap-3 rounded-xl border border-violet-100 bg-violet-50 p-3 text-xs text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                            data-testid="cockpit-quick-generate-recipient-match-group"
                        >
                            <div>
                                <p class="font-semibold">Recipient Match</p>
                                <p class="mt-1">
                                    Match claim data against the intended payee
                                    when the Pay Code should be restricted to a
                                    mobile number or payable alias.
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-violet-200 bg-white/70 p-3 dark:border-violet-900/60 dark:bg-violet-950/40"
                                data-testid="cockpit-quick-generate-payee-interpretation"
                            >
                                <p class="font-semibold">
                                    Payee validation interpretation
                                </p>
                                <p class="mt-1">
                                    {{ payeeHelpText }}
                                </p>
                                <p
                                    class="mt-1 text-violet-700 dark:text-violet-300"
                                >
                                    Mobile payees map to
                                    <code>cash.validation.mobile</code>; vendor
                                    aliases map to
                                    <code>cash.validation.payable</code>; blank
                                    or CASH remains unrestricted by payee.
                                </p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-violet-200 bg-white/70 p-3 text-xs font-medium text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                                >
                                    <input
                                        v-model="requireMobileValidation"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Match Mobile Number</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-violet-700 dark:text-violet-300"
                                        >
                                            Adds
                                            <code>cash.validation.mobile</code>
                                            when the payee is mobile.
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-violet-200 bg-white/70 p-3 text-xs font-medium text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                                >
                                    <input
                                        v-model="requirePayableValidation"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span
                                            >Require Payable / Vendor
                                            Alias</span
                                        >
                                        <span
                                            class="text-[11px] leading-snug font-normal text-violet-700 dark:text-violet-300"
                                        >
                                            Adds
                                            <code>cash.validation.payable</code>
                                            for unrestricted payees.
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-violet-200 bg-white/70 p-3 text-xs font-medium text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                                >
                                    <input
                                        v-model="requireCountryValidation"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Require Country: PH</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-violet-700 dark:text-violet-300"
                                        >
                                            Adds
                                            <code>cash.validation.country</code>
                                            as PH.
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-violet-200 bg-white/70 p-3 text-xs font-medium text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                                >
                                    <input
                                        v-model="requireLocationValidation"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Require Location Radius</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-violet-700 dark:text-violet-300"
                                        >
                                            Adds location and radius checks to
                                            cash validation.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div
                            class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                            data-testid="cockpit-quick-generate-secret-group"
                        >
                            <label
                                class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span>Claim Secret / Branch PIN</span>
                                <input
                                    v-model="validationSecret"
                                    type="text"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-validation-secret"
                                    :disabled="processing"
                                />
                                <span
                                    class="text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Use for branch PINs, release codes, or
                                    manual verification passphrases. Preview
                                    shows only that a secret is configured.
                                </span>
                            </label>
                        </div>
                        <div
                            class="grid gap-2 rounded-xl border border-sky-100 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                            data-testid="cockpit-quick-generate-evidence-required-group"
                        >
                            <div>
                                <p class="font-semibold">Evidence Required</p>
                                <p class="mt-1">
                                    These switches describe evidence expected in
                                    the claim journey. Cockpit does not perform
                                    KYC, OTP delivery, selfie capture, or
                                    signature verification here.
                                </p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-sky-200 bg-white/70 p-3 text-xs font-medium text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                                >
                                    <input
                                        v-model="verificationKyc"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-sky-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>KYC</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-sky-700 dark:text-sky-300"
                                        >
                                            Identity evidence is expected.
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-sky-200 bg-white/70 p-3 text-xs font-medium text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                                >
                                    <input
                                        v-model="verificationOtp"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-sky-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>OTP</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-sky-700 dark:text-sky-300"
                                        >
                                            One-time passcode confirmation is
                                            expected.
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-sky-200 bg-white/70 p-3 text-xs font-medium text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                                >
                                    <input
                                        v-model="verificationSelfie"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-sky-300"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Selfie</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-sky-700 dark:text-sky-300"
                                        >
                                            Selfie evidence is expected.
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-sky-200 bg-white/70 p-3 text-xs font-medium text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                                >
                                    <input
                                        v-model="signatureRequired"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-sky-300"
                                        data-testid="cockpit-quick-generate-signature-required"
                                        :disabled="processing"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Require Signature</span>
                                        <span
                                            class="text-[11px] leading-snug font-normal text-sky-700 dark:text-sky-300"
                                        >
                                            Signature evidence is expected.
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                            data-testid="cockpit-quick-generate-validation-advanced"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Advanced verification rules
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Signature failure
                                    <select
                                        v-model="signatureFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !signatureRequired
                                        "
                                    >
                                        <option value="block">block</option>
                                        <option value="warn">warn</option>
                                    </select>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    OTP failure
                                    <select
                                        v-model="otpFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !verificationOtp
                                        "
                                    >
                                        <option value="block">block</option>
                                        <option value="warn">warn</option>
                                    </select>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Selfie failure
                                    <select
                                        v-model="selfieFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !verificationSelfie
                                        "
                                    >
                                        <option value="block">block</option>
                                        <option value="warn">warn</option>
                                    </select>
                                </label>
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="faceMatchRequired"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        data-testid="cockpit-quick-generate-face-match-required"
                                        :disabled="processing"
                                    />
                                    Face Match Required
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Face Match Confidence
                                    <input
                                        v-model="faceMatchConfidence"
                                        type="number"
                                        min="0"
                                        max="1"
                                        step="0.01"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-face-match-confidence"
                                        :disabled="
                                            processing || !faceMatchRequired
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Face Match Failure
                                    <select
                                        v-model="faceMatchFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !faceMatchRequired
                                        "
                                    >
                                        <option value="block">block</option>
                                        <option value="warn">warn</option>
                                    </select>
                                </label>
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="timeValidationEnabled"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        data-testid="cockpit-quick-generate-time-validation-enabled"
                                        :disabled="processing"
                                    />
                                    Restrict Claim Time Window
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Start time
                                    <input
                                        v-model="timeWindowStart"
                                        type="time"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !timeValidationEnabled
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    End time
                                    <input
                                        v-model="timeWindowEnd"
                                        type="time"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !timeValidationEnabled
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Timezone
                                    <input
                                        v-model="timeWindowTimezone"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !timeValidationEnabled
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Limit minutes
                                    <input
                                        v-model="timeLimitMinutes"
                                        type="number"
                                        min="1"
                                        max="1440"
                                        step="1"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !timeValidationEnabled
                                        "
                                    />
                                </label>
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="timeTrackDuration"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        :disabled="
                                            processing || !timeValidationEnabled
                                        "
                                    />
                                    Track Duration
                                </label>
                            </div>
                        </details>
                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                            data-testid="cockpit-quick-generate-validation-preview"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Validation Payload Preview
                            </summary>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        cash.validation
                                    </p>
                                    <p
                                        class="mt-1 font-mono text-xs break-words text-slate-800 dark:text-slate-100"
                                        data-testid="cockpit-quick-generate-cash-validation-preview-value"
                                    >
                                        {{ validationPreviewDisplay }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                                    >
                                        validation
                                    </p>
                                    <p
                                        class="mt-1 font-mono text-xs break-words text-slate-800 dark:text-slate-100"
                                        data-testid="cockpit-quick-generate-structured-validation-preview-value"
                                    >
                                        {{ structuredValidationPreviewDisplay }}
                                    </p>
                                </div>
                            </div>
                            <p
                                class="mt-2 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                This preview shows rule names only. Secret
                                values, provider payloads, and verification
                                evidence are not displayed.
                            </p>
                        </details>
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
                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                            data-testid="cockpit-quick-generate-rider-advanced"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Advanced rider metadata
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Redirect timeout
                                    <input
                                        v-model="riderRedirectTimeout"
                                        type="number"
                                        min="0"
                                        max="300"
                                        step="1"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-redirect-timeout"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Splash HTML profile
                                    <input
                                        v-model="riderSplashMetaProfile"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-splash-profile"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    OG source
                                    <select
                                        v-model="riderOgSource"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-og-source"
                                        :disabled="processing"
                                    >
                                        <option value="">Default</option>
                                        <option value="message">message</option>
                                        <option value="url">url</option>
                                        <option value="splash">splash</option>
                                    </select>
                                </label>
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="riderSplashMetaSanitized"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        :disabled="processing"
                                    />
                                    Splash content sanitized
                                </label>
                            </div>
                        </details>
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
                                <option value="fixed">Fixed slices</option>
                                <option value="open">Open slices</option>
                            </select>
                        </label>
                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Fixed slices
                            <input
                                v-model="slices"
                                type="number"
                                min="1"
                                step="1"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-fixed-slices"
                                :disabled="processing || sliceMode !== 'fixed'"
                            />
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

                <section
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    data-testid="cockpit-quick-generate-advanced-contract-section"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-900/60 dark:text-violet-200"
                            >7</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Settlement, execution, and metadata
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Advanced DTO fields remain opt-in and do not
                                change the default issuance handoff.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Settlement fields
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Voucher type
                                    <select
                                        v-model="voucherType"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-voucher-type"
                                        :disabled="processing"
                                    >
                                        <option value="redeemable">
                                            redeemable
                                        </option>
                                        <option value="payable">payable</option>
                                        <option value="settlement">
                                            settlement
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Target amount
                                    <input
                                        v-model="targetAmount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-target-amount"
                                        :disabled="
                                            processing ||
                                            voucherType === 'redeemable'
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Min payment
                                    <input
                                        v-model="rulesMinPayment"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rules-min-payment"
                                        :disabled="
                                            processing ||
                                            voucherType === 'redeemable'
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Max payment
                                    <input
                                        v-model="rulesMaxPayment"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            voucherType === 'redeemable'
                                        "
                                    />
                                </label>
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="rulesAllowOverpayment"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        :disabled="
                                            processing ||
                                            voucherType === 'redeemable'
                                        "
                                    />
                                    Allow overpayment
                                </label>
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="rulesAutoCloseOnFullPayment"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        :disabled="
                                            processing ||
                                            voucherType === 'redeemable'
                                        "
                                    />
                                    Auto-close on full payment
                                </label>
                            </div>
                        </details>

                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Execution instruction
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <input
                                        v-model="includeExecutionInstruction"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        data-testid="cockpit-quick-generate-include-execution"
                                        :disabled="processing"
                                    />
                                    Include execution instruction
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Schema
                                    <input
                                        v-model="executionSchema"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Driver
                                    <input
                                        v-model="executionDriver"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-execution-driver"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Mode
                                    <input
                                        v-model="executionMode"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Pipeline
                                    <input
                                        v-model="executionPipeline"
                                        type="text"
                                        placeholder="comma-separated step keys"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-execution-pipeline"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Fallback
                                    <input
                                        v-model="executionFallback"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Visibility
                                    <input
                                        v-model="executionVisibility"
                                        type="text"
                                        placeholder="comma-separated visibility keys"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-2 dark:text-slate-300"
                                >
                                    Execution metadata note
                                    <input
                                        v-model="executionMetadata"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            !includeExecutionInstruction
                                        "
                                    />
                                </label>
                            </div>
                        </details>

                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Metadata fields
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Flow type
                                    <input
                                        v-model="metadataFlowType"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-metadata-flow-type"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Issuer ID
                                    <input
                                        v-model="metadataIssuerId"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Collection wallet ID
                                    <input
                                        v-model="metadataCollectionWalletId"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="processing"
                                    />
                                </label>
                            </div>
                        </details>
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
