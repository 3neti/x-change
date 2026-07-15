<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type {
    CockpitQuickGenerateCampaignAttribution,
    CockpitQuickGenerateCampaignContext,
    CockpitQuickGenerateDraftContract,
    CockpitQuickGenerateFeedbackDefaults,
    CockpitQuickGenerateMutationContract,
    CockpitQuickGeneratePostIssuanceNavigation,
    CockpitQuickGeneratePostIssuanceNavigationItem,
    CockpitQuickGenerateRuntimeActivity,
    CockpitQuickGenerateRuntimeDraft,
    CockpitQuickGenerateRuntimeFundingPreflight,
    CockpitQuickGenerateRuntimePricingPreflight,
    CockpitQuickGenerateTemplate,
} from '../types';
import CockpitManualCopyButton from './CockpitManualCopyButton.vue';
import CockpitPhoneInput from './CockpitPhoneInput.vue';

const props = defineProps<{
    mutationContract?: CockpitQuickGenerateMutationContract;
    draftContract?: CockpitQuickGenerateDraftContract;
    campaignContext?: CockpitQuickGenerateCampaignContext;
    feedbackDefaults?: CockpitQuickGenerateFeedbackDefaults;
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

type RiderUrlPreset = {
    value: string;
    label: string;
    url: string;
    helper: string;
};

type RiderOgPreview = {
    source: 'default' | 'message' | 'url' | 'splash';
    label: string;
    title: string;
    description: string;
    reference: string;
};

type FeedbackChannel = 'email' | 'mobile' | 'webhook';
type SliceMode = 'whole' | 'fixed' | 'open' | 'named';

type NamedClaimSlice = {
    id: string;
    amount: string;
    description: string;
    tag: string;
    claim_on: string;
    claim_by: string;
};

type NormalizedNamedClaimSlice = {
    id: string;
    amount: number;
    description: string;
    tag: string | null;
    claim_on: string | null;
    claim_by: string | null;
};

type EffectiveExpiry = {
    source: 'none' | 'preset' | 'ttl_override' | 'absolute_expires_at';
    label: string;
    payload: Record<string, string>;
};

const wholeSliceDescription = 'Whole Amount';
const openSliceDescription = 'Open Slice';
const issuerDefaultMinimumWithdrawal = 25;
const providerMinimumWithdrawalByCurrency: Record<
    string,
    Record<string, number>
> = {
    netbank: { PHP: 5 },
    paynamics: { PHP: 50 },
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
    sliceMode: SliceMode;
    maxSlices: string;
    minWithdrawal: string;
    provider: string;
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

const riderUrlPresets: RiderUrlPreset[] = [
    {
        value: '',
        label: 'None',
        url: '',
        helper: 'No CTA destination URL.',
    },
    {
        value: 'branch-instructions',
        label: 'Branch Instructions',
        url: 'https://example.com/branch-instructions',
        helper: 'Common branch counter or manual release instructions.',
    },
    {
        value: 'promo-page',
        label: 'Promo / Ad Page',
        url: 'https://example.com/promo',
        helper: 'Marketing or campaign landing page.',
    },
    {
        value: 'support-page',
        label: 'Support / Help Page',
        url: 'https://example.com/support',
        helper: 'Beneficiary support and help instructions.',
    },
    {
        value: 'kyc-instructions',
        label: 'KYC Instructions',
        url: 'https://example.com/kyc-instructions',
        helper: 'Identity-verification instructions.',
    },
    {
        value: 'remittance-status',
        label: 'Remittance Status',
        url: 'https://example.com/remittance-status',
        helper: 'Status page for remittance-style payouts.',
    },
    {
        value: 'custom',
        label: 'Custom URL',
        url: '',
        helper: 'Type a custom destination URL below.',
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
        minWithdrawal: '25',
        provider: 'netbank',
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
        minWithdrawal: '25',
        provider: 'paynamics',
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
        provider: 'manual',
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
const riderUrlPreset = ref('');
const riderRedirectTimeout = ref('');
const riderSplashHeadline = ref('');
const riderSplash = ref('');
const riderSplashCtaText = ref('');
const riderSplashTimeout = ref('3');
const riderSplashMetaSanitized = ref(true);
const riderSplashMetaProfile = ref('');
const riderOgSource = ref('');
const feedbackEmail = ref('');
const feedbackMobile = ref(recipientReference.value);
const feedbackWebhook = ref('');
const feedbackEmailEnabled = ref(false);
const feedbackMobileEnabled = ref(false);
const feedbackWebhookEnabled = ref(false);
const autoFilledFeedback = ref<Record<FeedbackChannel, string | null>>({
    email: null,
    mobile: null,
    webhook: null,
});
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
const provider = ref(
    quickGenerateTemplateDefaults[selectedTemplate.value]?.provider ??
        'netbank',
);
const feeStrategy = ref<'absorb' | 'include' | 'add'>('absorb');
const cashType = ref('default');
const customCashType = ref('');
const selectedMandates = ref<string[]>([]);
const customMandates = ref('');
const sliceMode = ref<SliceMode>('whole');
const slices = ref('1');
const maxSlices = ref('1');
const minWithdrawal = ref(String(issuerDefaultMinimumWithdrawal));
const namedClaimSlices = ref<NamedClaimSlice[]>(defaultWholeNamedClaimSlices());
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

watch(amount, (): void => {
    if (sliceMode.value === 'whole') {
        configureWholeAmountSlices();
    }

    if (sliceMode.value === 'fixed') {
        redistributeFixedNamedClaimSlices(fixedSliceCount());
    }

    if (sliceMode.value === 'open') {
        configureOpenAmountSlice();
    }
});

watch([currency, provider], (): void => {
    if (sliceMode.value === 'whole') {
        configureWholeAmountSlices();
    }

    if (sliceMode.value === 'fixed') {
        redistributeFixedNamedClaimSlices(fixedSliceCount());
    }

    if (sliceMode.value === 'open') {
        configureOpenAmountSlice();
    }
});

watch(slices, (): void => {
    if (sliceMode.value !== 'fixed') {
        return;
    }

    const count = fixedSliceCount();

    if (count !== namedClaimSlices.value.length) {
        redistributeFixedNamedClaimSlices(count);
    }
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
    provider.value = defaults.provider;
    riderUrl.value = defaults.riderUrl;
    riderUrlPreset.value = '';
    riderSplashHeadline.value = '';
    riderSplash.value = defaults.riderSplash;
    riderSplashCtaText.value = '';
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
    applyTemplateSliceDefaults(defaults);
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

const defaultFeedbackEmail = computed<string>(
    () =>
        stringValue(props.feedbackDefaults?.email)?.trim().toLowerCase() ?? '',
);
const defaultFeedbackMobile = computed<string>(() =>
    normalizePhilippineMobile(
        stringValue(props.feedbackDefaults?.mobile) ?? '',
    ),
);
const defaultFeedbackWebhook = computed<string>(
    () => stringValue(props.feedbackDefaults?.webhook)?.trim() ?? '',
);

const feedbackEmailError = computed<string | null>(() => {
    const email = feedbackEmail.value.trim();

    if (email === '') {
        return feedbackEmailEnabled.value
            ? 'Email feedback is selected. Enter a valid email address.'
            : null;
    }

    return isValidEmail(email) ? null : 'Enter a valid email address.';
});

const normalizedFeedbackMobile = computed<string>(() =>
    normalizePhilippineMobile(feedbackMobile.value),
);

const feedbackMobileError = computed<string | null>(() => {
    const mobile = feedbackMobile.value.trim();

    if (mobile === '') {
        return feedbackMobileEnabled.value
            ? 'SMS feedback is selected. Enter a Philippine mobile number.'
            : null;
    }

    return normalizedFeedbackMobile.value === ''
        ? 'Use a valid PH mobile number, e.g. 09173011987 or +639173011987.'
        : null;
});

const feedbackWebhookError = computed<string | null>(() => {
    const webhook = feedbackWebhook.value.trim();

    if (webhook === '') {
        return feedbackWebhookEnabled.value
            ? 'Webhook feedback is selected. Enter a webhook URL.'
            : null;
    }

    return isValidWebhookUrl(webhook)
        ? null
        : 'Use an http(s) webhook URL. javascript:, data:, and mailto: are blocked.';
});

const feedbackValidationErrors = computed<string[]>(() => {
    return [
        feedbackEmailError.value,
        feedbackMobileError.value,
        feedbackWebhookError.value,
    ].filter((error): error is string => error !== null);
});

const feedbackValid = computed<boolean>(() => {
    return feedbackValidationErrors.value.length === 0;
});

watch(feedbackEmail, (): void => {
    feedbackEmailEnabled.value = feedbackMatchesDefault('email');
});

watch(feedbackMobile, (): void => {
    feedbackMobileEnabled.value = feedbackMatchesDefault('mobile');
});

watch(feedbackWebhook, (): void => {
    feedbackWebhookEnabled.value = feedbackMatchesDefault('webhook');
});

const canSubmit = computed<boolean>(() => {
    return (
        props.mutationContract?.runtime_enabled === true &&
        routeUrl.value !== null &&
        allowedMethods.value.includes('POST') &&
        feedbackValid.value &&
        namedClaimSliceValidationMessage.value === null
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

const generationStatusLabel = computed<string>(() => {
    if (resultCode.value !== null) {
        return 'Generation complete';
    }

    return 'Operator-safe result received';
});

const generationSummary = computed<
    Array<{ label: string; value: string; detail: string }>
>(() => {
    return [
        {
            label: 'Pay Code',
            value: resultCode.value ?? 'received',
            detail: 'Generated by the existing x-change issuance handoff.',
        },
        {
            label: 'Beneficiary URL',
            value:
                beneficiaryClaimUrl.value !== null
                    ? 'ready to copy'
                    : 'not returned',
            detail:
                beneficiaryClaimUrl.value !== null
                    ? 'Manual distribution only; no delivery was sent.'
                    : 'No full claim URL was included in the operator response.',
        },
        {
            label: 'Pricing',
            value:
                pricingPreflight.value !== null
                    ? displayValue(pricingPreflight.value.status)
                    : 'not returned',
            detail:
                pricingPreflight.value !== null
                    ? `${displayValue(pricingPreflight.value.currency, 'PHP')} ${displayValue(pricingPreflight.value.total, '0')} total`
                    : 'No pricing preflight was included in the operator response.',
        },
        {
            label: 'Funding',
            value:
                fundingPreflight.value !== null
                    ? displayValue(fundingPreflight.value.status)
                    : 'not returned',
            detail:
                fundingPreflight.value !== null
                    ? `${displayValue(fundingPreflight.value.authority)} · ${displayValue(fundingPreflight.value.sync_status)}`
                    : 'No funding preflight was included in the operator response.',
        },
        {
            label: 'Activity',
            value:
                activityRuntime.value !== null
                    ? displayValue(activityRuntime.value.status)
                    : 'not returned',
            detail:
                activityRuntime.value?.presentation_only === true
                    ? 'Presentation-only activity status; no downstream delivery is implied.'
                    : 'No durable activity status was included in the operator response.',
        },
    ];
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

const effectiveExpiry = computed<EffectiveExpiry>(() => {
    const absoluteExpiresAt = expiresAt.value.trim();

    if (absoluteExpiresAt !== '') {
        const payload: Record<string, string> = {
            expires_at: absoluteExpiresAt,
        };

        return {
            source: 'absolute_expires_at',
            label: `Absolute expiry: ${absoluteExpiresAt}`,
            payload,
        };
    }

    const advancedTtl = ttl.value.trim();

    if (advancedTtl !== '') {
        const payload: Record<string, string> = {
            ttl: advancedTtl,
        };

        return {
            source: 'ttl_override',
            label: `Raw TTL override: ${advancedTtl}`,
            payload,
        };
    }

    if (effectiveTtl.value !== '') {
        const payload: Record<string, string> = {
            ttl: effectiveTtl.value,
        };

        return {
            source: 'preset',
            label: `Expiry preset: ${effectiveTtl.value}`,
            payload,
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
        normalizedFeedbackMobile.value ||
        normalizePhilippineMobile(recipientReference.value);
    const email = feedbackEmail.value.trim().toLowerCase();
    const webhook = feedbackWebhook.value.trim();

    return {
        mobile: mobile === '' ? null : mobile,
        email: email === '' ? null : email,
        webhook: webhook === '' ? null : webhook,
    };
});

const selectedRiderUrlPreset = computed<RiderUrlPreset>(() => {
    return (
        riderUrlPresets.find(
            (preset) => preset.value === riderUrlPreset.value,
        ) ?? riderUrlPresets[0]
    );
});

function applyRiderUrlPreset(): void {
    if (selectedRiderUrlPreset.value.value === 'custom') {
        return;
    }

    riderUrl.value = selectedRiderUrlPreset.value.url;
}

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function looksLikeHtml(value: string): boolean {
    return /<\/?[a-z][\s\S]*>/i.test(value);
}

const riderSplashContent = computed<string>(() => {
    const headline = riderSplashHeadline.value.trim();
    const body = riderSplash.value.trim();
    const cta = riderSplashCtaText.value.trim();

    if (headline === '' && cta === '') {
        return body;
    }

    return [
        headline === '' ? null : `<h1>${escapeHtml(headline)}</h1>`,
        body === ''
            ? null
            : looksLikeHtml(body)
              ? body
              : `<p>${escapeHtml(body)}</p>`,
        cta === '' ? null : `<p><strong>${escapeHtml(cta)}</strong></p>`,
    ]
        .filter((item): item is string => item !== null)
        .join('\n');
});

const riderSplashPreviewIsHtml = computed<boolean>(() => {
    return looksLikeHtml(riderSplash.value.trim());
});

function buildSandboxedPreviewDocument(content: string): string {
    const body = content.trim() === '' ? '<p>No splash body yet.</p>' : content;

    return `<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none';" />
<style>
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; background: #020617; color: #f8fafc; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
body { padding: 0; }
img { max-width: 100%; height: auto; }
.text-center { text-align: center; }
.mx-auto { margin-left: auto; margin-right: auto; }
.relative { position: relative; }
.absolute { position: absolute; }
.inset-0 { inset: 0; }
.pointer-events-none { pointer-events: none; }
.flex { display: flex; }
.items-center { align-items: center; }
.justify-end { justify-content: flex-end; }
.overflow-hidden { overflow: hidden; }
.rounded-lg { border-radius: 0.5rem; }
.shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,.3), 0 4px 6px -4px rgba(0,0,0,.3); }
.bg-black { background: #000; }
.text-white { color: #fff; }
.font-serif { font-family: ui-serif, Georgia, Cambria, "Times New Roman", Times, serif; }
.font-normal { font-weight: 400; }
.italic { font-style: italic; }
.tracking-wide { letter-spacing: .025em; }
.tracking-widest { letter-spacing: .1em; }
.text-xs { font-size: .75rem; line-height: 1rem; }
.text-sm { font-size: .875rem; line-height: 1.25rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.mb-3 { margin-bottom: .75rem; }
.mb-8 { margin-bottom: 2rem; }
h1, h2, h3, p { margin-top: 0; }
body > p:last-child strong {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 9999;
    display: inline-flex;
    max-width: calc(100% - 2rem);
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgba(249, 115, 22, .94);
    color: #fff;
    padding: .55rem .9rem;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,.35), 0 4px 6px -4px rgba(0,0,0,.35);
    font-size: .8125rem;
    line-height: 1.25rem;
    text-align: center;
}
@media (min-width: 640px) {
    .sm\\:text-sm { font-size: .875rem; line-height: 1.25rem; }
    .sm\\:text-2xl { font-size: 1.5rem; line-height: 2rem; }
    .sm\\:text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
}
</style>
</head>
<body>
${body}
</body>
</html>`;
}

const riderSplashPreviewDocument = computed<string>(() => {
    const body = riderSplashPreviewIsHtml.value
        ? riderSplashContent.value
        : `<p>${escapeHtml(riderSplashContent.value || 'No splash body yet.')}</p>`;

    return buildSandboxedPreviewDocument(body);
});

const riderOgPreview = computed<RiderOgPreview>(() => {
    const source =
        riderOgSource.value === 'message' ||
        riderOgSource.value === 'url' ||
        riderOgSource.value === 'splash'
            ? riderOgSource.value
            : 'default';
    const message = purpose.value.trim();
    const url = riderUrl.value.trim();
    const splashHeadline = riderSplashHeadline.value.trim();
    const splashBody = riderSplash.value.trim();
    const splashCta = riderSplashCtaText.value.trim();

    if (source === 'message') {
        return {
            source,
            label: 'Message preview',
            title: message === '' ? 'No message yet' : message,
            description:
                'Beneficiary preview is based on the rider message/purpose.',
            reference: 'rider.message',
        };
    }

    if (source === 'url') {
        return {
            source,
            label: 'CTA URL preview',
            title: url === '' ? 'No CTA URL yet' : url,
            description:
                'Beneficiary preview is based on the selected CTA destination.',
            reference: 'rider.url',
        };
    }

    if (source === 'splash') {
        return {
            source,
            label: 'Splash preview',
            title:
                splashHeadline === ''
                    ? splashBody || 'No splash content yet'
                    : splashHeadline,
            description:
                splashCta === ''
                    ? splashBody || 'Splash body is empty.'
                    : `${splashBody || 'Splash body is empty.'} · ${splashCta}`,
            reference: 'rider.splash',
        };
    }

    return {
        source,
        label: 'Default preview',
        title:
            splashHeadline ||
            message ||
            (url === '' ? 'Default beneficiary preview' : url),
        description:
            splashBody ||
            message ||
            'Cockpit will submit only operator-safe rider fields.',
        reference: 'rider.og_source: default',
    };
});

const riderOgPreviewSource = computed<RiderOgPreview['source']>(() => {
    return riderOgPreview.value.source;
});

const riderOgPreviewUsesSplashRender = computed<boolean>(() => {
    return (
        (riderOgPreviewSource.value === 'default' ||
            riderOgPreviewSource.value === 'splash') &&
        riderSplashContent.value.trim() !== ''
    );
});

const riderOgPreviewDocument = computed<string>(() => {
    return buildSandboxedPreviewDocument(
        riderOgPreviewUsesSplashRender.value
            ? riderSplashContent.value
            : `<h1>${escapeHtml(riderOgPreview.value.title)}</h1><p>${escapeHtml(riderOgPreview.value.description)}</p>`,
    );
});

const riderSummary = computed<Record<string, unknown>>(() => {
    const message = purpose.value.trim();
    const url = riderUrl.value.trim();
    const redirectTimeout = Number(riderRedirectTimeout.value);
    const splash = riderSplashContent.value.trim();
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

const normalizedNamedClaimSlices = computed<NormalizedNamedClaimSlice[]>(() => {
    const source =
        namedClaimSlices.value.length > 0
            ? namedClaimSlices.value
            : defaultWholeNamedClaimSlices();

    return source.map((slice, index) => ({
        id: slice.id || namedClaimSliceId(index),
        amount: Number(slice.amount || 0),
        description:
            slice.description.trim() ||
            (source.length === 1
                ? wholeSliceDescription
                : defaultFixedSliceDescription(index)),
        tag: slice.tag.trim() || null,
        claim_on: slice.claim_on.trim() || null,
        claim_by: slice.claim_by.trim() || null,
    }));
});

const namedClaimSliceTotal = computed<number>(() => {
    return normalizedNamedClaimSlices.value.reduce(
        (total, slice) => total + Number(slice.amount || 0),
        0,
    );
});

const namedClaimSliceRemaining = computed<number>(() => {
    const normalizedAmount = Number(amount.value);

    return Number(
        (
            (Number.isFinite(normalizedAmount) ? normalizedAmount : 0) -
            namedClaimSliceTotal.value
        ).toFixed(2),
    );
});

const providerMinimumWithdrawal = computed<number | null>(() => {
    const normalizedProvider = provider.value.trim().toLowerCase();
    const normalizedCurrency = (currency.value.trim() || 'PHP').toUpperCase();

    return (
        providerMinimumWithdrawalByCurrency[normalizedProvider]?.[
            normalizedCurrency
        ] ?? null
    );
});

const minimumWithdrawalFloor = computed<number>(() => {
    return Math.max(
        issuerDefaultMinimumWithdrawal,
        providerMinimumWithdrawal.value ?? 0,
    );
});

const minimumWithdrawalPolicySource = computed<string>(() => {
    if (
        providerMinimumWithdrawal.value !== null &&
        providerMinimumWithdrawal.value > issuerDefaultMinimumWithdrawal
    ) {
        return provider.value.trim().toLowerCase() || 'provider';
    }

    return 'issuer default';
});

const maxValidClaimCount = computed<number>(() => {
    const amount = normalizedPayCodeAmount();
    const minimum = minimumWithdrawalFloor.value;

    if (amount <= 0 || minimum <= 0) {
        return 1;
    }

    return Math.max(1, Math.floor(amount / minimum));
});

const addSliceDisabledReason = computed<string | null>(() => {
    const nextCount = Math.max(2, namedClaimSlices.value.length + 1);
    const nextAmount = normalizedPayCodeAmount() / nextCount;

    if (nextAmount + 0.0001 < minimumWithdrawalFloor.value) {
        return `Adding another slice would create ${currency.value || 'PHP'} ${formatSliceAmount(nextAmount)} claims, below the ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)} effective minimum.`;
    }

    return null;
});

const addSliceDisabled = computed<boolean>(() => {
    return processing.value || addSliceDisabledReason.value !== null;
});

const namedClaimSliceValidationMessage = computed<string | null>(() => {
    const normalizedAmount = Number(amount.value);

    if (!Number.isFinite(normalizedAmount) || normalizedAmount <= 0) {
        return sliceMode.value === 'whole'
            ? null
            : 'Enter a Pay Code amount before configuring slices.';
    }

    if (sliceMode.value === 'fixed') {
        const count = fixedSliceCount();
        const computedAmount = count > 0 ? normalizedAmount / count : 0;

        if (computedAmount + 0.0001 < minimumWithdrawalFloor.value) {
            return `${count} fixed slices would create ${currency.value || 'PHP'} ${formatSliceAmount(computedAmount)} claims, below the ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)} effective minimum.`;
        }

        return null;
    }

    if (sliceMode.value === 'open') {
        const minimum = Number(minWithdrawal.value);

        if (
            Number.isFinite(minimum) &&
            minimum + 0.0001 < minimumWithdrawalFloor.value
        ) {
            return `Minimum Withdrawal must be at least ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)}.`;
        }

        return null;
    }

    if (sliceMode.value !== 'named') {
        return null;
    }

    if (normalizedNamedClaimSlices.value.length === 0) {
        return 'Add at least one named slice.';
    }

    if (normalizedNamedClaimSlices.value.some((slice) => slice.amount <= 0)) {
        return 'Each named slice must have an amount greater than zero.';
    }

    if (
        normalizedNamedClaimSlices.value.some(
            (slice) => slice.amount + 0.0001 < minimumWithdrawalFloor.value,
        )
    ) {
        return `Each named slice must be at least ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)}.`;
    }

    if (Math.abs(namedClaimSliceRemaining.value) >= 0.01) {
        return 'Named slice amounts must equal the Pay Code amount.';
    }

    return null;
});

const sliceSummary = computed<Record<string, unknown>>(() => {
    const fixed = Number(slices.value);
    const max = Number(maxSlices.value);
    const minimum = Number(minWithdrawal.value);

    if (sliceMode.value === 'named') {
        const minimumNamedSliceAmount = Math.min(
            ...normalizedNamedClaimSlices.value.map((slice) => slice.amount),
        );

        return {
            mode: 'named',
            max_slices: normalizedNamedClaimSlices.value.length,
            min_withdrawal:
                Number.isFinite(minimumNamedSliceAmount) &&
                minimumNamedSliceAmount > 0
                    ? minimumNamedSliceAmount
                    : null,
            slices: normalizedNamedClaimSlices.value,
            total: namedClaimSliceTotal.value,
            remaining: namedClaimSliceRemaining.value,
            validation_message: namedClaimSliceValidationMessage.value,
        };
    }

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

const slicePlanRows = computed<Array<Record<string, unknown>>>(() => {
    return normalizedNamedClaimSlices.value.map((slice) => {
        const row: Record<string, unknown> = {
            id: slice.id,
            amount: slice.amount,
            description: slice.description,
        };

        if (slice.tag !== null) {
            row.tag = slice.tag;
        }

        if (slice.claim_on !== null) {
            row.claim_on = slice.claim_on;
        }

        if (slice.claim_by !== null) {
            row.claim_by = slice.claim_by;
        }

        return row;
    });
});

const canonicalSlicePlan = computed<Record<string, unknown>>(() => {
    const normalizedSlices = Number(sliceSummary.value.slices);
    const normalizedMaxSlices = Number(sliceSummary.value.max_slices);
    const normalizedMinWithdrawal = Number(sliceSummary.value.min_withdrawal);
    const maxClaims =
        sliceMode.value === 'fixed'
            ? Number.isFinite(normalizedSlices)
                ? normalizedSlices
                : slicePlanRows.value.length
            : sliceMode.value === 'open'
              ? Number.isFinite(normalizedMaxSlices)
                  ? normalizedMaxSlices
                  : 1
              : sliceMode.value === 'named'
                ? slicePlanRows.value.length
                : 1;
    const minimumWithdrawal =
        sliceMode.value === 'open' || sliceMode.value === 'named'
            ? Number.isFinite(normalizedMinWithdrawal)
                ? normalizedMinWithdrawal
                : minimumWithdrawalFloor.value
            : minimumWithdrawalFloor.value;

    return {
        schema: 'x-change.cockpit.slice-plan.v1',
        mode: sliceMode.value,
        cash_mode:
            sliceMode.value === 'named'
                ? 'open'
                : sliceMode.value === 'whole'
                  ? null
                  : sliceMode.value,
        currency: currency.value.trim() || 'PHP',
        total_amount: normalizedPayCodeAmount(),
        row_total: namedClaimSliceTotal.value,
        remaining: namedClaimSliceRemaining.value,
        max_claims: maxClaims,
        min_withdrawal: minimumWithdrawal,
        effective_minimum: minimumWithdrawalFloor.value,
        policy_source: minimumWithdrawalPolicySource.value,
        validation_message: namedClaimSliceValidationMessage.value,
        rows: slicePlanRows.value,
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
                    sliceMode.value === 'named'
                        ? `named · ${normalizedNamedClaimSlices.value.length} slices · ${currency.value.trim() || 'PHP'} ${namedClaimSliceTotal.value.toLocaleString('en-US', { maximumFractionDigits: 2 })}`
                        : sliceMode.value === 'open'
                          ? `open · max ${sliceSummary.value.max_slices}`
                          : sliceMode.value === 'fixed'
                            ? `fixed · ${sliceSummary.value.slices ?? 1}`
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

    if (sliceMode.value === 'named') {
        cash.slice_mode = 'open';
        cash.max_slices = normalizedNamedClaimSlices.value.length;
        cash.min_withdrawal = sliceSummary.value.min_withdrawal;
    }

    const validation = { ...validationSummary.value };

    if (redactSensitive && 'secret' in validation) {
        validation.secret = '[redacted secret]';
    }

    cash.validation = validation;

    const payload: Record<string, unknown> = {
        cash,
        ...(provider.value.trim() === ''
            ? {}
            : { provider: provider.value.trim() }),
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
            ...(sliceMode.value === 'named'
                ? {
                      slices: normalizedNamedClaimSlices.value,
                      slice_policy: {
                          mode: 'named',
                          selection: 'one_or_many',
                          enforced: true,
                      },
                  }
                : {}),
            custom: {
                cockpit: {
                    template_key: selectedTemplate.value,
                    source: 'cockpit.quick-generate',
                    builder: 'guided-voucher-instruction-builder',
                    contract_summary: contractSummaryItems.value,
                    slice_plan: canonicalSlicePlan.value,
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

function isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function normalizePhilippineMobile(value: string): string {
    const normalized = value.trim().replace(/[\s().-]+/g, '');

    if (/^\+639\d{9}$/.test(normalized)) {
        return normalized;
    }

    if (/^639\d{9}$/.test(normalized)) {
        return `+${normalized}`;
    }

    if (/^09\d{9}$/.test(normalized)) {
        return `+63${normalized.slice(1)}`;
    }

    if (/^9\d{9}$/.test(normalized)) {
        return `+63${normalized}`;
    }

    return '';
}

function isValidWebhookUrl(value: string): boolean {
    try {
        const url = new URL(value.trim());

        return url.protocol === 'https:' || url.protocol === 'http:';
    } catch {
        return false;
    }
}

function feedbackValue(channel: FeedbackChannel): string {
    if (channel === 'email') {
        return feedbackEmail.value.trim().toLowerCase();
    }

    if (channel === 'mobile') {
        return normalizePhilippineMobile(feedbackMobile.value);
    }

    return feedbackWebhook.value.trim();
}

function defaultFeedbackValue(channel: FeedbackChannel): string {
    if (channel === 'email') {
        return defaultFeedbackEmail.value;
    }

    if (channel === 'mobile') {
        return defaultFeedbackMobile.value;
    }

    return defaultFeedbackWebhook.value;
}

function feedbackMatchesDefault(channel: FeedbackChannel): boolean {
    const current = feedbackValue(channel);
    const fallback = defaultFeedbackValue(channel);

    return current !== '' && fallback !== '' && current === fallback;
}

function setFeedbackValue(channel: FeedbackChannel, value: string): void {
    if (channel === 'email') {
        feedbackEmail.value = value;

        return;
    }

    if (channel === 'mobile') {
        feedbackMobile.value = value;

        return;
    }

    feedbackWebhook.value = value;
}

function toggleFeedbackChannel(
    channel: FeedbackChannel,
    enabled: boolean,
): void {
    const current = feedbackValue(channel);
    const lastAuto = autoFilledFeedback.value[channel];

    if (!enabled) {
        if (lastAuto !== null && current === lastAuto) {
            setFeedbackValue(channel, '');
        }

        autoFilledFeedback.value[channel] = null;

        return;
    }

    const fallback = defaultFeedbackValue(channel);

    if (fallback === '') {
        return;
    }

    setFeedbackValue(channel, fallback);
    autoFilledFeedback.value[channel] = fallback;
}

function namedClaimSliceId(index: number): string {
    return `slice_${index + 1}`;
}

function normalizedPayCodeAmount(): number {
    const normalizedAmount = Number(amount.value);

    return Number.isFinite(normalizedAmount) && normalizedAmount > 0
        ? normalizedAmount
        : 0;
}

function formatSliceAmount(value: number): string {
    return String(Number(value.toFixed(2)));
}

function defaultFixedSliceDescription(index: number): string {
    return `Slice ${index + 1}`;
}

function defaultWholeNamedClaimSlices(): NamedClaimSlice[] {
    return [
        {
            id: 'slice_1',
            amount: formatSliceAmount(normalizedPayCodeAmount()),
            description: wholeSliceDescription,
            tag: '',
            claim_on: '',
            claim_by: '',
        },
    ];
}

function defaultOpenNamedClaimSlices(): NamedClaimSlice[] {
    return [
        {
            id: 'slice_1',
            amount: formatSliceAmount(normalizedPayCodeAmount()),
            description: openSliceDescription,
            tag: '',
            claim_on: '',
            claim_by: '',
        },
    ];
}

function equalFixedNamedClaimSlices(count: number): NamedClaimSlice[] {
    const safeCount = Math.max(1, Math.round(count));
    const normalizedAmount = Number(amount.value);
    const total =
        Number.isFinite(normalizedAmount) && normalizedAmount > 0
            ? normalizedAmount
            : 0;
    const base = Number((total / safeCount).toFixed(2));
    let allocated = 0;

    return Array.from({ length: safeCount }, (_, index) => {
        const isLast = index === safeCount - 1;
        const sliceAmount = isLast
            ? Number((total - allocated).toFixed(2))
            : base;

        allocated = Number((allocated + sliceAmount).toFixed(2));

        return {
            id: namedClaimSliceId(index),
            amount: formatSliceAmount(sliceAmount),
            description: defaultFixedSliceDescription(index),
            tag: '',
            claim_on: '',
            claim_by: '',
        };
    });
}

function redistributeExistingNamedClaimSliceAmounts(
    existingSlices: NamedClaimSlice[],
): NamedClaimSlice[] {
    const redistributedSlices = equalFixedNamedClaimSlices(
        existingSlices.length,
    );

    return existingSlices.map((slice, index) => ({
        ...slice,
        id: namedClaimSliceId(index),
        amount:
            redistributedSlices[index]?.amount ??
            formatSliceAmount(normalizedPayCodeAmount()),
    }));
}

function configureWholeAmountSlices(): void {
    sliceMode.value = 'whole';
    slices.value = '1';
    maxSlices.value = '1';
    minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);
    namedClaimSlices.value = defaultWholeNamedClaimSlices();
}

function configureOpenAmountSlice(): void {
    sliceMode.value = 'open';
    slices.value = '1';
    maxSlices.value = '1';
    minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);
    namedClaimSlices.value = defaultOpenNamedClaimSlices();
}

function fixedSliceCount(): number {
    const normalizedSlices = Number(slices.value);

    return Number.isFinite(normalizedSlices) && normalizedSlices > 0
        ? Math.round(normalizedSlices)
        : Math.max(1, namedClaimSlices.value.length);
}

function applyTemplateSliceDefaults(
    defaults: QuickGenerateTemplateDefaults,
): void {
    if (defaults.sliceMode === 'open') {
        configureOpenAmountSlice();
        minWithdrawal.value = formatSliceAmount(
            Math.max(
                Number(defaults.minWithdrawal || 0),
                minimumWithdrawalFloor.value,
            ),
        );

        return;
    }

    configureWholeAmountSlices();
    minWithdrawal.value = formatSliceAmount(
        Math.max(
            Number(defaults.minWithdrawal || 0),
            minimumWithdrawalFloor.value,
        ),
    );
}

function redistributeFixedNamedClaimSlices(count: number): void {
    const safeCount = Math.max(1, Math.round(count));

    sliceMode.value = 'fixed';
    slices.value = String(safeCount);
    maxSlices.value = String(safeCount);
    minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);
    namedClaimSlices.value = equalFixedNamedClaimSlices(safeCount);
}

function namedClaimSliceHasCustomMetadata(slice: NamedClaimSlice): boolean {
    return (
        slice.tag.trim() !== '' ||
        slice.claim_on.trim() !== '' ||
        slice.claim_by.trim() !== ''
    );
}

function namedClaimSliceAmountEquals(
    slice: NamedClaimSlice,
    value: number,
): boolean {
    return Math.abs(Number(slice.amount || 0) - value) < 0.01;
}

function namedClaimSlicesAreEqualFixed(): boolean {
    if (namedClaimSlices.value.length < 1) {
        return false;
    }

    const total = namedClaimSlices.value.reduce(
        (sum, slice) => sum + Number(slice.amount || 0),
        0,
    );

    if (Math.abs(total - normalizedPayCodeAmount()) >= 0.01) {
        return false;
    }

    const firstAmount = Number(namedClaimSlices.value[0]?.amount || 0);

    return namedClaimSlices.value.every((slice, index) => {
        return (
            namedClaimSliceAmountEquals(slice, firstAmount) &&
            slice.description.trim() === defaultFixedSliceDescription(index) &&
            !namedClaimSliceHasCustomMetadata(slice)
        );
    });
}

function reconcileSliceModeFromNamedClaimSlices(): void {
    const firstSlice = namedClaimSlices.value[0];

    if (
        namedClaimSlices.value.length === 1 &&
        firstSlice !== undefined &&
        namedClaimSliceAmountEquals(firstSlice, normalizedPayCodeAmount()) &&
        firstSlice.description.trim() === openSliceDescription &&
        !namedClaimSliceHasCustomMetadata(firstSlice)
    ) {
        sliceMode.value = 'open';
        slices.value = '1';
        maxSlices.value = '1';
        minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);

        return;
    }

    if (
        namedClaimSlices.value.length === 1 &&
        firstSlice !== undefined &&
        namedClaimSliceAmountEquals(firstSlice, normalizedPayCodeAmount()) &&
        firstSlice.description.trim() === wholeSliceDescription &&
        !namedClaimSliceHasCustomMetadata(firstSlice)
    ) {
        sliceMode.value = 'whole';
        slices.value = '1';
        maxSlices.value = '1';
        minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);

        return;
    }

    if (namedClaimSlicesAreEqualFixed()) {
        sliceMode.value = 'fixed';
        slices.value = String(namedClaimSlices.value.length);
        maxSlices.value = String(namedClaimSlices.value.length);
        minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);

        return;
    }

    sliceMode.value = 'named';
    slices.value = String(Math.max(1, namedClaimSlices.value.length));
    maxSlices.value = String(Math.max(1, namedClaimSlices.value.length));
    minWithdrawal.value = formatSliceAmount(
        Math.min(
            ...normalizedNamedClaimSlices.value.map((slice) => slice.amount),
        ),
    );
}

function addNamedClaimSlice(): void {
    if (addSliceDisabled.value) {
        return;
    }

    const nextCount = Math.max(2, namedClaimSlices.value.length + 1);

    redistributeFixedNamedClaimSlices(nextCount);
}

function removeNamedClaimSlice(index: number): void {
    if (namedClaimSlices.value.length <= 1) {
        return;
    }

    const wasEqualFixed = namedClaimSlicesAreEqualFixed();

    namedClaimSlices.value = namedClaimSlices.value
        .filter((_, sliceIndex) => sliceIndex !== index)
        .map((slice, sliceIndex) => ({
            ...slice,
            id: slice.id || namedClaimSliceId(sliceIndex),
        }));

    if (wasEqualFixed && namedClaimSlices.value.length === 1) {
        configureWholeAmountSlices();

        return;
    }

    if (wasEqualFixed) {
        redistributeFixedNamedClaimSlices(namedClaimSlices.value.length);

        return;
    }

    namedClaimSlices.value = redistributeExistingNamedClaimSliceAmounts(
        namedClaimSlices.value,
    );
    reconcileSliceModeFromNamedClaimSlices();
}

function updateNamedClaimSlice(
    index: number,
    key: keyof NamedClaimSlice,
    value: string,
): void {
    namedClaimSlices.value = namedClaimSlices.value.map((slice, sliceIndex) =>
        sliceIndex === index ? { ...slice, [key]: value } : slice,
    );
    reconcileSliceModeFromNamedClaimSlices();
}

function setSliceMode(mode: string): void {
    if (mode === 'whole') {
        configureWholeAmountSlices();

        return;
    }

    if (mode === 'fixed') {
        redistributeFixedNamedClaimSlices(fixedSliceCount());

        return;
    }

    if (mode === 'open') {
        configureOpenAmountSlice();

        return;
    }

    if (mode === 'named') {
        sliceMode.value = 'named';

        if (namedClaimSlices.value.length === 0) {
            namedClaimSlices.value = defaultWholeNamedClaimSlices();
        }
    }
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
                        <div
                            class="grid gap-3 rounded-xl border border-amber-100 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                            data-testid="cockpit-quick-generate-rider-cta-section"
                        >
                            <div>
                                <p class="font-semibold">CTA / Destination</p>
                                <p
                                    class="mt-1 text-amber-800 dark:text-amber-200"
                                >
                                    Choose a frequent beneficiary destination or
                                    type a custom URL. This still maps only to
                                    <code>rider.url</code>.
                                </p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-amber-950 dark:text-amber-100"
                                >
                                    Frequently Used CTA
                                    <select
                                        v-model="riderUrlPreset"
                                        class="w-full min-w-0 rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-amber-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-url-preset"
                                        :disabled="processing"
                                        @change="applyRiderUrlPreset"
                                    >
                                        <option
                                            v-for="preset in riderUrlPresets"
                                            :key="preset.value"
                                            :value="preset.value"
                                        >
                                            {{ preset.label }}
                                        </option>
                                    </select>
                                    <span
                                        class="text-[11px] leading-snug font-normal text-amber-800 dark:text-amber-200"
                                        data-testid="cockpit-quick-generate-rider-url-preset-helper"
                                    >
                                        {{ selectedRiderUrlPreset.helper }}
                                    </span>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-amber-950 dark:text-amber-100"
                                >
                                    CTA URL
                                    <input
                                        v-model="riderUrl"
                                        type="url"
                                        class="w-full min-w-0 rounded-xl border border-amber-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-amber-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-url"
                                        :disabled="processing"
                                    />
                                    <span
                                        class="text-[11px] leading-snug font-normal text-amber-800 dark:text-amber-200"
                                    >
                                        The claim experience can redirect or
                                        point the beneficiary to this
                                        destination.
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div
                            class="grid gap-3 rounded-xl border border-orange-100 bg-orange-50 p-3 text-xs text-orange-950 dark:border-orange-900/60 dark:bg-orange-950/40 dark:text-orange-100"
                            data-testid="cockpit-quick-generate-rider-splash-builder"
                        >
                            <div>
                                <p class="font-semibold">Splash Page Builder</p>
                                <p
                                    class="mt-1 text-orange-800 dark:text-orange-200"
                                >
                                    Compose the beneficiary-facing splash shown
                                    around the claim journey. Preview is local;
                                    no delivery or external rendering occurs.
                                </p>
                            </div>
                            <div class="grid gap-3 lg:grid-cols-3">
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-orange-950 dark:text-orange-100"
                                >
                                    Splash Headline
                                    <input
                                        v-model="riderSplashHeadline"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-orange-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-splash-headline"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-orange-950 dark:text-orange-100"
                                >
                                    CTA Button Text
                                    <input
                                        v-model="riderSplashCtaText"
                                        type="text"
                                        class="w-full min-w-0 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-orange-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-splash-cta-text"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 content-start gap-1 text-xs font-medium text-orange-950 dark:text-orange-100"
                                >
                                    Splash Timeout
                                    <input
                                        v-model="riderSplashTimeout"
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="h-10 w-full min-w-0 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-orange-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="processing"
                                    />
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-orange-950 lg:col-span-3 dark:text-orange-100"
                                >
                                    Splash Body
                                    <textarea
                                        v-model="riderSplash"
                                        rows="3"
                                        class="w-full min-w-0 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-orange-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-splash-body"
                                        :disabled="processing"
                                    />
                                </label>
                                <div
                                    class="rounded-xl border border-orange-200 bg-white p-3 lg:col-span-3 dark:border-orange-900/60 dark:bg-slate-950"
                                    data-testid="cockpit-quick-generate-rider-splash-preview"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wide text-orange-700 uppercase dark:text-orange-300"
                                    >
                                        Local splash preview
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] leading-snug text-orange-800 dark:text-orange-200"
                                    >
                                        HTML is rendered in a sandboxed preview
                                        iframe when tags are detected.
                                    </p>
                                    <div class="mt-2 grid gap-1">
                                        <iframe
                                            v-if="riderSplashPreviewIsHtml"
                                            title="Sandboxed local splash HTML preview"
                                            sandbox=""
                                            class="h-80 w-full rounded-lg border border-orange-200 bg-slate-950 dark:border-orange-900/60"
                                            data-testid="cockpit-quick-generate-rider-splash-html-preview"
                                            :srcdoc="riderSplashPreviewDocument"
                                        />
                                        <template v-else>
                                            <p
                                                v-if="
                                                    riderSplashHeadline.trim() !==
                                                    ''
                                                "
                                                class="text-sm font-bold text-slate-950 dark:text-slate-50"
                                            >
                                                {{ riderSplashHeadline }}
                                            </p>
                                            <p
                                                class="text-xs leading-snug whitespace-pre-line text-slate-700 dark:text-slate-300"
                                            >
                                                {{
                                                    riderSplash.trim() ||
                                                    'No splash body yet.'
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    riderSplashCtaText.trim() !==
                                                    ''
                                                "
                                                class="mt-2 inline-flex w-fit rounded-full bg-orange-100 px-3 py-1 text-[11px] font-semibold text-orange-800 dark:bg-orange-900/60 dark:text-orange-100"
                                            >
                                                {{ riderSplashCtaText }}
                                            </p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="grid gap-3 rounded-xl border border-sky-100 bg-sky-50 p-3 text-xs text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                            data-testid="cockpit-quick-generate-rider-og-preview"
                        >
                            <div
                                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div>
                                    <p class="font-semibold">OG Preview</p>
                                    <p
                                        class="mt-1 text-sky-800 dark:text-sky-200"
                                    >
                                        Local preview only. Cockpit does not
                                        fetch external Open Graph metadata or
                                        generate social share assets here.
                                    </p>
                                </div>
                                <label
                                    class="grid min-w-48 gap-1 text-xs font-medium text-sky-950 dark:text-sky-100"
                                >
                                    OG Source
                                    <select
                                        v-model="riderOgSource"
                                        class="w-full min-w-0 rounded-xl border border-sky-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-sky-900/60 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-rider-og-source"
                                        :disabled="processing"
                                    >
                                        <option value="">Default</option>
                                        <option value="message">Message</option>
                                        <option value="url">CTA URL</option>
                                        <option value="splash">Splash</option>
                                    </select>
                                </label>
                            </div>
                            <div
                                class="rounded-xl border border-sky-200 bg-white p-3 dark:border-sky-900/60 dark:bg-slate-950"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-2 text-[11px] font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-300"
                                >
                                    <span>{{ riderOgPreview.label }}</span>
                                    <span
                                        class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800 dark:bg-sky-900/60 dark:text-sky-100"
                                    >
                                        {{ riderOgPreview.reference }}
                                    </span>
                                </div>
                                <iframe
                                    title="Sandboxed local OG preview"
                                    sandbox=""
                                    class="mt-2 h-64 w-full rounded-lg border border-sky-200 bg-slate-950 dark:border-sky-900/60"
                                    data-testid="cockpit-quick-generate-rider-og-html-preview"
                                    :srcdoc="riderOgPreviewDocument"
                                />
                                <p
                                    class="mt-2 text-[11px] text-sky-800 dark:text-sky-200"
                                >
                                    No external OG fetch, scraping, upload,
                                    delivery, short-link generation, or claim
                                    runtime mutation occurs.
                                </p>
                            </div>
                        </div>
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
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2"
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
                    <div
                        class="mt-4 grid gap-3"
                        data-testid="cockpit-quick-generate-feedback-channels"
                    >
                        <div
                            class="grid gap-2 rounded-xl border border-violet-100 bg-violet-50 p-3 text-xs text-violet-950 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                        >
                            <p class="font-semibold">
                                Quick-fill feedback recipients
                            </p>
                            <p class="text-violet-800 dark:text-violet-200">
                                Select a channel to populate it from the
                                authenticated operator defaults. Values remain
                                editable and are saved only as feedback intent;
                                Cockpit does not deliver messages here.
                            </p>
                            <div class="grid gap-2 lg:grid-cols-2">
                                <label
                                    class="flex items-start gap-2 rounded-lg border border-violet-200 bg-white p-2 lg:row-start-2 dark:border-violet-900/60 dark:bg-slate-950"
                                >
                                    <input
                                        v-model="feedbackEmailEnabled"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        data-testid="cockpit-quick-generate-feedback-email-toggle"
                                        :disabled="processing"
                                        @change="
                                            toggleFeedbackChannel(
                                                'email',
                                                feedbackEmailEnabled,
                                            )
                                        "
                                    />
                                    <span>
                                        <span class="font-semibold"
                                            >Use my email</span
                                        >
                                        <span
                                            class="block text-[11px] text-violet-700 dark:text-violet-200"
                                        >
                                            {{
                                                defaultFeedbackEmail ||
                                                'No operator email available'
                                            }}
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-lg border border-violet-200 bg-white p-2 dark:border-violet-900/60 dark:bg-slate-950"
                                >
                                    <input
                                        v-model="feedbackMobileEnabled"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        data-testid="cockpit-quick-generate-feedback-mobile-toggle"
                                        :disabled="processing"
                                        @change="
                                            toggleFeedbackChannel(
                                                'mobile',
                                                feedbackMobileEnabled,
                                            )
                                        "
                                    />
                                    <span>
                                        <span class="font-semibold"
                                            >Use my mobile</span
                                        >
                                        <span
                                            class="block text-[11px] text-violet-700 dark:text-violet-200"
                                        >
                                            {{
                                                defaultFeedbackMobile ||
                                                'No operator mobile available'
                                            }}
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-lg border border-violet-200 bg-white p-2 dark:border-violet-900/60 dark:bg-slate-950"
                                >
                                    <input
                                        v-model="feedbackWebhookEnabled"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        data-testid="cockpit-quick-generate-feedback-webhook-toggle"
                                        :disabled="processing"
                                        @change="
                                            toggleFeedbackChannel(
                                                'webhook',
                                                feedbackWebhookEnabled,
                                            )
                                        "
                                    />
                                    <span class="min-w-0">
                                        <span class="font-semibold"
                                            >Use operator webhook</span
                                        >
                                        <span
                                            class="block text-[11px] break-all text-violet-700 dark:text-violet-200"
                                        >
                                            {{
                                                defaultFeedbackWebhook ||
                                                'No default webhook available'
                                            }}
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Feedback email
                                <input
                                    v-model="feedbackEmail"
                                    type="email"
                                    class="w-full min-w-0 rounded-xl border bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:bg-slate-900 dark:text-slate-50"
                                    :class="
                                        feedbackEmailError
                                            ? 'border-rose-300 dark:border-rose-700'
                                            : 'border-slate-200 dark:border-slate-800'
                                    "
                                    data-testid="cockpit-quick-generate-feedback-email"
                                    :disabled="processing"
                                />
                                <span
                                    v-if="feedbackEmailError"
                                    class="text-[11px] font-normal text-rose-600 dark:text-rose-300"
                                    data-testid="cockpit-quick-generate-feedback-email-error"
                                >
                                    {{ feedbackEmailError }}
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Feedback mobile
                                <CockpitPhoneInput
                                    v-model="feedbackMobile"
                                    :error="feedbackMobileError"
                                    input-test-id="cockpit-quick-generate-feedback-mobile"
                                    :disabled="processing"
                                />
                                <span
                                    v-if="feedbackMobileError"
                                    class="text-[11px] font-normal text-rose-600 dark:text-rose-300"
                                    data-testid="cockpit-quick-generate-feedback-mobile-error"
                                >
                                    {{ feedbackMobileError }}
                                </span>
                                <span
                                    v-else-if="normalizedFeedbackMobile !== ''"
                                    class="text-[11px] font-normal text-emerald-700 dark:text-emerald-300"
                                    data-testid="cockpit-quick-generate-feedback-mobile-normalized"
                                >
                                    Normalized:
                                    {{ normalizedFeedbackMobile }}
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-2 dark:text-slate-300"
                            >
                                Feedback webhook
                                <input
                                    v-model="feedbackWebhook"
                                    type="url"
                                    class="w-full min-w-0 rounded-xl border bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:bg-slate-900 dark:text-slate-50"
                                    :class="
                                        feedbackWebhookError
                                            ? 'border-rose-300 dark:border-rose-700'
                                            : 'border-slate-200 dark:border-slate-800'
                                    "
                                    data-testid="cockpit-quick-generate-feedback-webhook"
                                    :disabled="processing"
                                />
                                <span
                                    v-if="feedbackWebhookError"
                                    class="text-[11px] font-normal text-rose-600 dark:text-rose-300"
                                    data-testid="cockpit-quick-generate-feedback-webhook-error"
                                >
                                    {{ feedbackWebhookError }}
                                </span>
                                <span
                                    v-else
                                    class="text-[11px] font-normal text-slate-500 dark:text-slate-400"
                                >
                                    Generated defaults are editable. No webhook
                                    receiver route is registered by this UI.
                                </span>
                            </label>
                        </div>
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
                    <div class="mt-4 grid gap-4">
                        <div
                            class="grid grid-cols-1 gap-2 lg:grid-cols-4"
                            data-testid="cockpit-quick-generate-slice-mode-cards"
                        >
                            <button
                                v-for="option in [
                                    {
                                        value: 'whole',
                                        label: 'Whole amount',
                                        helper: 'One claim consumes the full Pay Code.',
                                    },
                                    {
                                        value: 'fixed',
                                        label: 'Fixed slices',
                                        helper: 'Split into equal numbered slices.',
                                    },
                                    {
                                        value: 'open',
                                        label: 'Open amount',
                                        helper: 'Allow partial withdrawals up to a max count.',
                                    },
                                    {
                                        value: 'named',
                                        label: 'Named claim slices',
                                        helper: 'Operator-defined slices shown during claim.',
                                    },
                                ]"
                                :key="option.value"
                                type="button"
                                class="rounded-xl border p-3 text-left text-xs transition"
                                :class="
                                    sliceMode === option.value
                                        ? 'border-cyan-400 bg-cyan-50 text-cyan-950 ring-2 ring-cyan-100 dark:border-cyan-500 dark:bg-cyan-950/40 dark:text-cyan-100 dark:ring-cyan-950'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-cyan-200 hover:bg-cyan-50/50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-cyan-900'
                                "
                                :disabled="processing"
                                :data-testid="`cockpit-quick-generate-slice-mode-${option.value}`"
                                @click="setSliceMode(option.value)"
                            >
                                <span class="block font-semibold">
                                    {{ option.label }}
                                </span>
                                <span class="mt-1 block leading-snug">
                                    {{ option.helper }}
                                </span>
                            </button>
                        </div>

                        <div
                            class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Fixed Slices
                                    <input
                                        v-model="slices"
                                        type="number"
                                        min="1"
                                        :max="maxValidClaimCount"
                                        step="1"
                                        inputmode="numeric"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-fixed-slices"
                                        :disabled="
                                            processing || sliceMode !== 'fixed'
                                        "
                                    />
                                    <span
                                        class="text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                    >
                                        Number of equal claim portions.
                                    </span>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Max Claims
                                    <input
                                        v-model="maxSlices"
                                        type="number"
                                        min="1"
                                        :max="maxValidClaimCount"
                                        step="1"
                                        inputmode="numeric"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-max-slices"
                                        :disabled="
                                            processing || sliceMode !== 'open'
                                        "
                                    />
                                    <span
                                        class="text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                    >
                                        Mirrors fixed/named claim count;
                                        editable only for Open Slice.
                                    </span>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Minimum Withdrawal
                                    <div
                                        class="flex min-w-0 rounded-xl shadow-sm"
                                    >
                                        <span
                                            class="inline-flex items-center rounded-l-xl border border-r-0 border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                        >
                                            ₱
                                        </span>
                                        <input
                                            v-model="minWithdrawal"
                                            type="number"
                                            :min="minimumWithdrawalFloor"
                                            step="0.01"
                                            inputmode="decimal"
                                            class="w-full min-w-0 border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                            data-testid="cockpit-quick-generate-min-withdrawal"
                                            :disabled="
                                                processing ||
                                                sliceMode !== 'open'
                                            "
                                        />
                                        <span
                                            class="inline-flex w-24 min-w-0 items-center justify-center rounded-r-xl border border-l-0 border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 uppercase dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                            data-testid="cockpit-quick-generate-min-withdrawal-currency"
                                        >
                                            {{ currency || 'PHP' }}
                                        </span>
                                    </div>
                                    <span
                                        class="text-[11px] leading-snug font-normal text-slate-500 dark:text-slate-400"
                                    >
                                        Smallest claim amount, shown in
                                        {{ currency || 'PHP' }}.
                                    </span>
                                </label>
                            </div>
                            <div
                                class="grid gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-[11px] leading-snug text-emerald-900 lg:grid-cols-3 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100"
                                data-testid="cockpit-quick-generate-minimum-withdrawal-policy"
                            >
                                <p>
                                    <span class="font-semibold"
                                        >Effective Minimum</span
                                    >
                                    <span class="block">
                                        {{ currency || 'PHP' }}
                                        {{
                                            formatSliceAmount(
                                                minimumWithdrawalFloor,
                                            )
                                        }}
                                    </span>
                                </p>
                                <p>
                                    <span class="font-semibold">Source</span>
                                    <span class="block capitalize">
                                        {{ minimumWithdrawalPolicySource }}
                                    </span>
                                </p>
                                <p>
                                    <span class="font-semibold"
                                        >Max Valid Claims</span
                                    >
                                    <span class="block">
                                        {{ maxValidClaimCount }}
                                    </span>
                                </p>
                            </div>
                            <p
                                class="text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Fixed and open amount settings are submitted as
                                cash slice instructions. Named claim slices use
                                open-slice cash semantics plus named metadata.
                            </p>
                        </div>

                        <div
                            class="rounded-xl border border-cyan-200 bg-cyan-50/70 p-3 dark:border-cyan-900/60 dark:bg-cyan-950/30"
                            data-testid="cockpit-quick-generate-named-slices-panel"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-semibold text-cyan-950 dark:text-cyan-100"
                                    >
                                        Claim slice builder
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] text-cyan-800 dark:text-cyan-200"
                                    >
                                        These rows drive the selected mode:
                                        Whole Amount for one full slice, Fixed
                                        Slices for equal default rows, and Named
                                        claim slices when you customize labels,
                                        dates, tags, or amounts.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-full border border-cyan-200 bg-white px-3 py-1.5 text-xs font-semibold text-cyan-800 shadow-sm hover:bg-cyan-50 disabled:opacity-50 dark:border-cyan-800 dark:bg-slate-950 dark:text-cyan-100"
                                    data-testid="cockpit-quick-generate-add-named-slice"
                                    :disabled="addSliceDisabled"
                                    @click="addNamedClaimSlice"
                                >
                                    Add slice
                                </button>
                            </div>
                            <p
                                v-if="addSliceDisabledReason"
                                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                                data-testid="cockpit-quick-generate-add-slice-warning"
                            >
                                {{ addSliceDisabledReason }}
                            </p>

                            <div class="mt-3 grid gap-3">
                                <div
                                    v-for="(slice, index) in namedClaimSlices"
                                    :key="slice.id || index"
                                    class="rounded-xl border border-cyan-100 bg-white p-3 shadow-sm dark:border-cyan-900/50 dark:bg-slate-950"
                                    :data-testid="`cockpit-quick-generate-named-slice-${index}`"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between gap-3"
                                    >
                                        <p
                                            class="text-xs font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{
                                                sliceMode === 'open'
                                                    ? openSliceDescription
                                                    : `Slice ${index + 1}`
                                            }}
                                        </p>
                                        <button
                                            type="button"
                                            class="text-[11px] font-semibold text-rose-600 disabled:text-slate-400 dark:text-rose-300"
                                            :data-testid="`cockpit-quick-generate-remove-named-slice-${index}`"
                                            :disabled="
                                                processing ||
                                                namedClaimSlices.length <= 1
                                            "
                                            @click="
                                                removeNamedClaimSlice(index)
                                            "
                                        >
                                            Remove
                                        </button>
                                    </div>
                                    <div
                                        class="grid grid-cols-1 gap-3 lg:grid-cols-6"
                                    >
                                        <label
                                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-1 dark:text-slate-300"
                                        >
                                            Amount
                                            <input
                                                :value="slice.amount"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                                :data-testid="`cockpit-quick-generate-named-slice-${index}-amount`"
                                                :disabled="processing"
                                                @input="
                                                    updateNamedClaimSlice(
                                                        index,
                                                        'amount',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                        </label>
                                        <label
                                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-2 dark:text-slate-300"
                                        >
                                            Description
                                            <input
                                                :value="slice.description"
                                                type="text"
                                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                                :data-testid="`cockpit-quick-generate-named-slice-${index}-description`"
                                                :disabled="processing"
                                                @input="
                                                    updateNamedClaimSlice(
                                                        index,
                                                        'description',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                        </label>
                                        <label
                                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-1 dark:text-slate-300"
                                        >
                                            Tag
                                            <input
                                                :value="slice.tag"
                                                type="text"
                                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                                :data-testid="`cockpit-quick-generate-named-slice-${index}-tag`"
                                                :disabled="processing"
                                                @input="
                                                    updateNamedClaimSlice(
                                                        index,
                                                        'tag',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                        </label>
                                        <label
                                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-1 dark:text-slate-300"
                                        >
                                            Claim on
                                            <input
                                                :value="slice.claim_on"
                                                type="date"
                                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                                :data-testid="`cockpit-quick-generate-named-slice-${index}-claim-on`"
                                                :disabled="processing"
                                                @input="
                                                    updateNamedClaimSlice(
                                                        index,
                                                        'claim_on',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                        </label>
                                        <label
                                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-1 dark:text-slate-300"
                                        >
                                            Claim by
                                            <input
                                                :value="slice.claim_by"
                                                type="date"
                                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                                :data-testid="`cockpit-quick-generate-named-slice-${index}-claim-by`"
                                                :disabled="processing"
                                                @input="
                                                    updateNamedClaimSlice(
                                                        index,
                                                        'claim_by',
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    )
                                                "
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-2 text-xs lg:grid-cols-3">
                                <div
                                    class="rounded-lg bg-white p-3 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        Slice total
                                    </p>
                                    <p
                                        class="mt-1 font-semibold text-slate-950 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-named-slices-total"
                                    >
                                        {{ currency || 'PHP' }}
                                        {{
                                            namedClaimSliceTotal.toLocaleString(
                                                'en-US',
                                                {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                },
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg bg-white p-3 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        Remaining
                                    </p>
                                    <p
                                        class="mt-1 font-semibold"
                                        :class="
                                            Math.abs(namedClaimSliceRemaining) <
                                            0.01
                                                ? 'text-emerald-700 dark:text-emerald-300'
                                                : 'text-rose-700 dark:text-rose-300'
                                        "
                                        data-testid="cockpit-quick-generate-named-slices-remaining"
                                    >
                                        {{ currency || 'PHP' }}
                                        {{
                                            namedClaimSliceRemaining.toLocaleString(
                                                'en-US',
                                                {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                },
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg bg-white p-3 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        Payload mode
                                    </p>
                                    <p
                                        class="mt-1 font-semibold text-cyan-800 dark:text-cyan-200"
                                    >
                                        {{
                                            sliceMode === 'whole'
                                                ? 'whole amount'
                                                : sliceMode === 'fixed'
                                                  ? 'fixed cash slices'
                                                  : sliceMode === 'open'
                                                    ? 'open cash slices'
                                                    : 'open cash + named metadata'
                                        }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="namedClaimSliceValidationMessage"
                                class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
                                data-testid="cockpit-quick-generate-named-slices-error"
                            >
                                {{ namedClaimSliceValidationMessage }}
                            </p>
                        </div>

                        <div
                            class="grid gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 lg:grid-cols-3 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
                            data-testid="cockpit-quick-generate-availability-summary"
                        >
                            <div>
                                <p class="font-semibold">Availability window</p>
                                <p class="mt-1 text-[11px]">
                                    Starts:
                                    {{ startsAt || 'immediate' }}
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Effective expiry</p>
                                <p class="mt-1 text-[11px]">
                                    {{ effectiveExpiry.label }}
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Precedence</p>
                                <p class="mt-1 text-[11px]">
                                    Exact expiry wins over TTL; TTL wins over
                                    preset.
                                </p>
                            </div>
                        </div>
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
            class="mt-4 rounded-2xl border border-emerald-200 bg-white p-4 text-xs text-slate-700 shadow-sm dark:border-emerald-900 dark:bg-slate-950 dark:text-slate-300"
            data-testid="cockpit-quick-generate-result-panel"
        >
            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/30"
                data-testid="cockpit-quick-generate-productized-result-card"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.22em] text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            {{ generationStatusLabel }}
                        </p>
                        <h3
                            class="mt-2 text-2xl font-semibold tracking-tight text-emerald-950 dark:text-emerald-50"
                        >
                            Pay Code {{ resultCode ?? 'issued' }}
                        </h3>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-emerald-800 dark:text-emerald-200"
                        >
                            Generated through the existing x-change issuance
                            handoff. Cockpit presents the result, claim URL,
                            preflight summaries, and activity status without
                            sending feedback, executing actions, writing
                            journal entries, calling providers directly, or
                            moving money from this UI.
                        </p>
                    </div>
                    <span
                        class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
                    >
                        operator result
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <div
                        v-for="item in generationSummary"
                        :key="item.label"
                        class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/70 dark:bg-slate-950/60"
                    >
                        <dt
                            class="text-[11px] font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            {{ item.label }}
                        </dt>
                        <dd
                            class="mt-1 font-semibold break-words text-emerald-950 dark:text-emerald-50"
                        >
                            {{ item.value }}
                        </dd>
                        <p
                            class="mt-2 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                        >
                            {{ item.detail }}
                        </p>
                    </div>
                </dl>

                <div
                    class="mt-4 flex flex-col gap-3 rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/70 dark:bg-slate-950/60 lg:flex-row lg:items-center lg:justify-between"
                    data-testid="cockpit-quick-generate-primary-next-actions"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold tracking-[0.18em] text-emerald-700 uppercase dark:text-emerald-300"
                        >
                            Primary next step
                        </p>
                        <p
                            class="mt-1 text-sm font-semibold text-emerald-950 dark:text-emerald-50"
                        >
                            Copy or inspect the beneficiary claim URL
                        </p>
                        <p
                            v-if="beneficiaryClaimUrl"
                            class="mt-1 font-mono text-[11px] break-all text-emerald-800 dark:text-emerald-200"
                        >
                            {{ beneficiaryClaimUrl }}
                        </p>
                        <p
                            v-else
                            class="mt-1 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                        >
                            No beneficiary URL was returned. Use the generated
                            Pay Code detail for inspection.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            v-if="beneficiaryClaimUrl"
                            :href="beneficiaryClaimUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400"
                            data-testid="cockpit-quick-generate-primary-claim-link"
                        >
                            Open claim URL
                        </a>
                        <a
                            v-if="cockpitDetailUrl"
                            :href="cockpitDetailUrl"
                            class="rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 transition hover:border-emerald-500 hover:text-emerald-950 dark:border-emerald-800 dark:bg-slate-950 dark:text-emerald-200"
                            data-testid="cockpit-quick-generate-primary-detail-link"
                        >
                            Inspect Pay Code
                        </a>
                    </div>
                </div>
            </section>

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
                to refresh Cockpit data, open the generated Pay Code detail, or
                copy the beneficiary URL for an approved external distribution
                workflow.
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
