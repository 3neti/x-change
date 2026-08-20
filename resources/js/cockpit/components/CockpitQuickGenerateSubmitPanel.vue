<script setup lang="ts">
import CockpitPayCodeTemplateStoreController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeTemplateStoreController';
import CockpitPayCodeTemplateUpdateController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeTemplateUpdateController';
import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/vue3';
import {
    Check,
    Clock3,
    FilePlus2,
    LayoutTemplate,
    Link2,
    LoaderCircle,
    MessageSquareText,
    Palette,
    RotateCcw,
    Save,
    Sparkles,
    TicketCheck,
    Type,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import type {
    CockpitQuickGenerateCampaignAttribution,
    CockpitQuickGenerateCampaignContext,
    CockpitClaimExperiencePreviewManifest,
    CockpitInstructionCapabilityReadiness,
    CockpitInstructionCapabilityReadinessMap,
    CockpitQuickGenerateClaimPreviewContract,
    CockpitQuickGenerateDraftContract,
    CockpitQuickGenerateFeedbackDefaults,
    CockpitQuickGenerateLastInstructions,
    CockpitQuickGenerateMutationContract,
    CockpitQuickGeneratePostIssuanceNavigation,
    CockpitQuickGeneratePostIssuanceNavigationItem,
    CockpitQuickGenerateRuntimeActivity,
    CockpitQuickGenerateRuntimeDraft,
    CockpitQuickGenerateRuntimeFundingPreflight,
    CockpitQuickGenerateRuntimePricingPreflight,
    CockpitQuickGenerateTemplate,
    CockpitRiderLibraryEntry,
    CockpitSavedPayCodeTemplate,
    CockpitSettlementRailCapabilities,
    CockpitSettlementRailCapability,
} from '../types';
import { usePayCodeCostEstimate } from '../../composables/usePayCodeCostEstimate';
import { useTheme } from '../../composables/useTheme';
import type { PayCodeCostEstimate } from '../../composables/usePayCodeCostEstimate';
import { useRiderUrlArtworkPreview } from '../composables/useRiderUrlArtworkPreview';
import {
    buildRiderStampPreviewDocument,
    buildRiderSplashContent,
    buildSandboxedPreviewDocument,
    firstRiderArtworkImageUrl,
    resolveRiderStampPreview,
} from '../riderStampPreview';
import type {
    RiderStampArtworkSource,
    RiderStampArtworkTreatment,
    RiderStampClaimMarker,
    RiderStampClaimMarkerPosition,
    RiderStampCopySource,
    RiderStampFit,
    RiderStampPosition,
    RiderStampPreview,
    RiderStampTheme,
} from '../riderStampPreview';
import type { RiderContentFormat } from '../riderContent';
import { classifyCockpitPayee, type CockpitPayeeKind } from '../payeePolicy';
import {
    type FeedbackChannel,
    type FeedbackDestinations,
    normalizePhilippineFeedbackMobile,
} from '../feedbackDestinations';
import { resolvePayCodeIndicator } from '../payCodeIndicators';
import CockpitAmountPicker from './CockpitAmountPicker.vue';
import CockpitClaimExperiencePreview from './CockpitClaimExperiencePreview.vue';
import CockpitClaimRequirementsControl from './CockpitClaimRequirementsControl.vue';
import type {
    CockpitClaimRequirementCategory,
    CockpitClaimRequirementOption,
    CockpitClaimRequirementPreset,
} from './CockpitClaimRequirementsControl.vue';
import CockpitFeedbackDestinationInput from './CockpitFeedbackDestinationInput.vue';
import CockpitFieldHelp from './CockpitFieldHelp.vue';
import CockpitIssuedPayCodeDialog from './CockpitIssuedPayCodeDialog.vue';
import CockpitManualCopyButton from './CockpitManualCopyButton.vue';
import CockpitPayCodeCanvas from './CockpitPayCodeCanvas.vue';
import CockpitPhoneInput from './CockpitPhoneInput.vue';
import CockpitRiderArtworkInspector from './CockpitRiderArtworkInspector.vue';
import CockpitRiderArtworkThumbnail from './CockpitRiderArtworkThumbnail.vue';
import CockpitRiderEditorDisclosure from './CockpitRiderEditorDisclosure.vue';
import CockpitRiderMessageEditor from './CockpitRiderMessageEditor.vue';
import CockpitRiderLibrary from './CockpitRiderLibrary.vue';
import CockpitRiderPreviewFrame from './CockpitRiderPreviewFrame.vue';
import type { CockpitScheduledPortion } from './CockpitScheduledPortionsEditor.vue';
import CockpitValueUseControl from './CockpitValueUseControl.vue';
import type { CockpitValueUseMode } from './CockpitValueUseControl.vue';

const props = withDefaults(
    defineProps<{
        clientFundsMinor?: number | null;
        mutationContract?: CockpitQuickGenerateMutationContract;
        claimPreviewContract?: CockpitQuickGenerateClaimPreviewContract;
        draftContract?: CockpitQuickGenerateDraftContract;
        campaignContext?: CockpitQuickGenerateCampaignContext;
        feedbackDefaults?: CockpitQuickGenerateFeedbackDefaults;
        onboardingOtpRequired?: boolean;
        onboardingPreset?: boolean;
        lastInstructions?: CockpitQuickGenerateLastInstructions | null;
        savedTemplates?: CockpitSavedPayCodeTemplate[];
        riderLibrary?: CockpitRiderLibraryEntry[];
        instructionCapabilities?: CockpitInstructionCapabilityReadinessMap;
        settlementRailCapabilities?: CockpitSettlementRailCapabilities;
        templates: CockpitQuickGenerateTemplate[];
    }>(),
    {
        onboardingOtpRequired: true,
        onboardingPreset: false,
        instructionCapabilities: () => ({}),
        riderLibrary: () => [],
    },
);

const { currentTheme } = useTheme();

function capabilityReadiness(
    key: string,
): CockpitInstructionCapabilityReadiness | null {
    return props.instructionCapabilities[key] ?? null;
}

function capabilityUnavailable(key: string): boolean {
    return capabilityReadiness(key)?.issuance_allowed === false;
}

function capabilitySelectionDisabled(key: string, selected: boolean): boolean {
    return processing.value || (capabilityUnavailable(key) && !selected);
}

function capabilityReason(key: string, fallback: string): string {
    return capabilityReadiness(key)?.reason ?? fallback;
}

function inputFieldCapability(field: string): string | null {
    return ['location', 'kyc', 'otp', 'selfie', 'signature'].includes(field)
        ? field
        : null;
}

function claimRequirementCategory(
    field: string,
): CockpitClaimRequirementCategory {
    if (['selfie', 'signature', 'location'].includes(field)) {
        return 'evidence';
    }

    if (['kyc', 'otp'].includes(field)) {
        return 'verification';
    }

    return 'details';
}

const emit = defineEmits<{
    submitStart: [payload: Record<string, unknown>];
    submitSuccess: [response: Record<string, unknown>];
    submitError: [error: Record<string, unknown>];
}>();

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

type RiderArtworkSourceOption = {
    value: RiderStampArtworkSource;
    label: string;
    imageUrl: string | null;
    title: string;
    description: string;
    resolving: boolean;
};

type SliceMode = 'whole' | 'fixed' | 'open' | 'named';
type ClaimOutcomeMode = 'provider_disbursement' | 'account_funding';
type RiderDesignEditor = 'appearance' | 'message' | 'link' | 'splash';

type NamedClaimSlice = CockpitScheduledPortion;

type NormalizedNamedClaimSlice = {
    id: string;
    amount: number;
    description: string;
    tag: string | null;
    claim_on: string | null;
    claim_by: string | null;
};

type EffectiveExpiry = {
    source: string;
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
    claimOutcome: ClaimOutcomeMode;
    onboarding?: boolean;
};

const voucherInputFieldOptions: VoucherInputFieldOption[] = [
    {
        value: 'signature',
        label: 'Signature',
        helper: 'Require the recipient to provide a signature.',
    },
    {
        value: 'selfie',
        label: 'Selfie Photo',
        helper: 'Require the recipient to provide a selfie.',
    },
    {
        value: 'location',
        label: 'Location',
        helper: 'Record the recipient’s location during the claim.',
    },
    {
        value: 'otp',
        label: 'OTP',
        helper: 'Require a one-time passcode.',
    },
    {
        value: 'kyc',
        label: 'KYC',
        helper: 'Require identity verification.',
    },
    {
        value: 'reference_code',
        label: 'Reference Code',
        helper: 'Require a branch or external reference.',
    },
    {
        value: 'name',
        label: 'Full Name',
        helper: 'Collect the recipient’s legal or display name.',
    },
    {
        value: 'address',
        label: 'Address',
        helper: 'Collect the recipient’s address.',
    },
    {
        value: 'birth_date',
        label: 'Birthdate',
        helper: 'Collect the recipient’s birth date.',
    },
    {
        value: 'gross_monthly_income',
        label: 'Gross Monthly Income',
        helper: 'Collect monthly income when required.',
    },
    {
        value: 'mobile',
        label: 'Mobile Number',
        helper: 'Collect the recipient’s Philippine mobile number.',
    },
    {
        value: 'email',
        label: 'Email Address',
        helper: 'Collect the recipient’s email address.',
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
        label: 'Default',
        helper: 'Use the standard Pay Code behavior.',
    },
    {
        value: 'cash',
        label: 'Cash',
        helper: 'Create a standard claimable cash Pay Code.',
    },
    {
        value: 'disbursement',
        label: 'Disbursement',
        helper: 'Create a payout for a known recipient or route.',
    },
    {
        value: 'claimable_cash',
        label: 'Claimable Cash',
        helper: 'Require a claim before value is released.',
    },
    {
        value: 'settlement_cash',
        label: 'Settlement Cash',
        helper: 'Use the Pay Code for a settlement flow.',
    },
    {
        value: 'custom',
        label: 'Custom',
        helper: 'Use a purpose defined by an approved integration.',
    },
];

const mandateOptions: MandateOption[] = [
    {
        value: 'branch-release',
        label: 'Branch Release',
        helper: 'Require release by an operator or branch counter.',
    },
    {
        value: 'counter-check',
        label: 'Counter Check',
        helper: 'Require counter staff to verify the Pay Code.',
    },
    {
        value: 'kyc-required',
        label: 'KYC Required',
        helper: 'Require identity verification before completion.',
    },
    {
        value: 'otp-required',
        label: 'OTP Required',
        helper: 'Require one-time passcode verification.',
    },
    {
        value: 'manual-review',
        label: 'Manual Review',
        helper: 'Require a human review before completion.',
    },
    {
        value: 'recipient-match',
        label: 'Recipient Match',
        helper: 'Require claim details to match the intended recipient.',
    },
    {
        value: 'settlement-readiness',
        label: 'Settlement Readiness',
        helper: 'Require settlement readiness before completion.',
    },
];

const riderUrlPresets: RiderUrlPreset[] = [
    {
        value: '',
        label: 'None',
        url: '',
        helper: 'Do not show an action link.',
    },
    {
        value: 'branch-instructions',
        label: 'Branch Instructions',
        url: 'https://example.com/branch-instructions',
        helper: 'Send the recipient to branch or release instructions.',
    },
    {
        value: 'promo-page',
        label: 'Promotion Page',
        url: 'https://example.com/promo',
        helper: 'Send the recipient to a promotion or campaign page.',
    },
    {
        value: 'support-page',
        label: 'Help Page',
        url: 'https://example.com/support',
        helper: 'Send the recipient to support instructions.',
    },
    {
        value: 'kyc-instructions',
        label: 'KYC Instructions',
        url: 'https://example.com/kyc-instructions',
        helper: 'Send the recipient to identity verification instructions.',
    },
    {
        value: 'remittance-status',
        label: 'Remittance Status',
        url: 'https://example.com/remittance-status',
        helper: 'Send the recipient to a remittance status page.',
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
    'blank-pay-code': {
        amount: '',
        currency: 'PHP',
        count: '1',
        payee: '',
        purpose: '',
        inputFields: [],
        expiryPreset: 'none',
        requireMobileValidation: false,
        requirePayableValidation: false,
        requireCountryValidation: false,
        verificationKyc: false,
        verificationOtp: false,
        verificationSelfie: false,
        feedbackMobile: '',
        feedbackEmail: '',
        feedbackWebhook: '',
        riderUrl: '',
        riderSplash: '',
        riderSplashTimeout: '3',
        sliceMode: 'whole',
        maxSlices: '1',
        minWithdrawal: '25',
        provider: 'netbank',
        voucherType: 'redeemable',
        targetAmount: '',
        includeExecutionInstruction: false,
        executionDriver: 'default',
        claimOutcome: 'provider_disbursement',
        onboarding: false,
    },
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
        claimOutcome: 'provider_disbursement',
        onboarding: false,
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
        feedbackMobile: '',
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
        claimOutcome: 'provider_disbursement',
        onboarding: false,
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
        claimOutcome: 'provider_disbursement',
        onboarding: false,
    },
};

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
const payeeInputFocused = ref(false);
const payeePolicy = computed(() =>
    classifyCockpitPayee(recipientReference.value),
);
const normalizedPayee = computed<string>(
    () => payeePolicy.value.normalizedValue ?? '',
);
const payeeType = computed<'anyone' | Exclude<CockpitPayeeKind, 'open'>>(() =>
    payeePolicy.value.kind === 'open' ? 'anyone' : payeePolicy.value.kind,
);
const payeeRequiresMobile = computed<boolean>(
    () => payeePolicy.value.kind === 'mobile',
);
const payeeRequiresEmail = computed<boolean>(
    () => payeePolicy.value.kind === 'email',
);
const payeeRequiresVendor = computed<boolean>(
    () => payeePolicy.value.kind === 'vendor',
);
const payeeRequiresSecret = computed<boolean>(
    () => payeePolicy.value.kind === 'secret',
);
const payeeDisplayReference = computed<string>(() => {
    if (payeeRequiresSecret.value) {
        return 'Release Code Required';
    }

    return payeePolicy.value.displayValue;
});
const purpose = ref(
    stringValue(props.campaignContext?.draft?.purpose) ??
        stringValue(props.draftContract?.purpose) ??
        '',
);
const riderMessageFormat = ref<RiderContentFormat>('plain');
const count = ref('1');
const selectedInputFieldValues = ref<string[]>(['mobile']);
const onboardingEnabled = ref(props.onboardingPreset);
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
const riderSplashFormat = ref<RiderContentFormat>('plain');
const riderSplashCtaText = ref('');
const riderSplashTimeout = ref('3');
const riderSplashMetaSanitized = ref(true);
const riderSplashMetaProfile = ref('');
const riderStampArtworkSource = ref<RiderStampArtworkSource>('x_change');
const riderStampArtworkSourceWasExplicitlySelected = ref(false);
const riderStampArtworkTreatment = ref<RiderStampArtworkTreatment>('automatic');
const riderStampCopySource = ref<RiderStampCopySource>('automatic');
const riderStampShowLogo = ref(true);
const riderStampShowTagline = ref(true);
const riderStampClaimMarker = ref<RiderStampClaimMarker>('qr');
const riderStampClaimMarkerPosition =
    ref<RiderStampClaimMarkerPosition>('bottom_right');
const riderStampTitle = ref('');
const riderStampDescription = ref('');
const riderStampFit = ref<RiderStampFit>('cover');
const riderStampPosition = ref<RiderStampPosition>('center');
const riderStampScrim = ref('18');
const riderStampTheme = ref<RiderStampTheme>('automatic');
const riderStampDesignId = ref<string | null>(null);
const riderStampDesignVersion = ref<number | null>(null);
const {
    preview: riderUrlArtworkPreview,
    resolving: riderUrlArtworkResolving,
    message: riderUrlArtworkMessage,
} = useRiderUrlArtworkPreview(riderUrl);
const feedbackEmail = ref('');
const feedbackMobile = ref('');
const feedbackWebhook = ref('');
const feedbackEmailEnabled = ref(false);
const feedbackMobileEnabled = ref(false);
const feedbackWebhookEnabled = ref(false);
const feedbackTokenErrors = ref<string[]>([]);
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
const rememberedScheduledClaimSlices = ref<NamedClaimSlice[] | null>(null);
const reusableBalance = ref(false);
const storedValueReplenishable = ref(false);
const storedValueMaximumBalance = ref('');
const storedValueOtpAbove = ref('');
const voucherType = ref<'redeemable' | 'payable' | 'settlement'>('redeemable');
const claimOutcome = ref<ClaimOutcomeMode>('provider_disbursement');
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
const lastMessage = ref('Ready to issue when the design is complete.');
const lastResponse = ref<Record<string, unknown> | null>(null);
const previewProcessing = ref(false);
const previewStatus = ref<'idle' | 'ready' | 'failed'>('idle');
const previewMessage = ref(
    'Generate a no-money walkthrough from the current Pay Code design.',
);
const previewResult = ref<CockpitClaimExperiencePreviewManifest | null>(null);
const previewDraftSnapshot = ref<string | null>(null);
const issuedPayCodeDialogOpen = ref(false);
const instructionBuilderElement = ref<HTMLDetailsElement | null>(null);
const canvasSectionElement = ref<HTMLElement | null>(null);
const canvasView = ref<'stamp' | 'design' | 'claim' | 'cost'>('stamp');
const amountInputElement = ref<InstanceType<typeof CockpitAmountPicker> | null>(
    null,
);
const amountCalculatorPreview = ref<number | null>(null);
const amountCalculatorEstimatePending = ref(false);
const riderDesignEditor = ref<RiderDesignEditor>('appearance');
const riderDesignTeleportReady = ref(false);
const riderDesignTeleportTarget = ref<HTMLElement | null>(null);
const startingPoint = ref<'blank' | 'last' | 'template'>(
    props.lastInstructions ? 'last' : 'template',
);
const templatePickerOpen = ref(false);
const saveTemplateOpen = ref(false);
const saveTemplateName = ref('');
const saveTemplateDescription = ref('');
const saveTemplateIncludeAmount = ref(false);
const saveTemplateIncludePurpose = ref(true);
const templateSaving = ref(false);
const templateSaveError = ref('');
const saveTemplateMode = ref<'create' | 'update'>('create');
const activeSavedTemplate = ref<CockpitSavedPayCodeTemplate | null>(null);
const applyingStartingPoint = ref(false);
const submissionErrors = ref<Array<{ field: string; message: string }>>([]);
const submissionErrorHeading = ref('Fix these fields before issuing');

hydrateLastInstructions();

onMounted((): void => {
    riderDesignTeleportReady.value = true;
    void focusAmountEditor();
});

async function focusAmountEditor(): Promise<void> {
    await nextTick();
    amountInputElement.value?.focus();
}

watch(
    selectedTemplate,
    (templateKey): void => {
        if (applyingStartingPoint.value) {
            return;
        }

        applyTemplateDefaults(templateKey);
        activeSavedTemplate.value = null;
        startingPoint.value =
            templateKey === 'blank-pay-code' ? 'blank' : 'template';
    },
    { flush: 'sync' },
);

watch(amount, (): void => {
    if (sliceMode.value === 'whole') {
        configureWholeAmountSlices();
    }

    if (sliceMode.value === 'fixed') {
        redistributeFixedNamedClaimSlices(fixedSliceCount());
    }

    if (sliceMode.value === 'open') {
        reconcileOpenAmountSliceConstraints();
    }

    if (
        reusableBalance.value &&
        Number(storedValueMaximumBalance.value || 0) < normalizedPayCodeAmount()
    ) {
        storedValueMaximumBalance.value = formatSliceAmount(
            normalizedPayCodeAmount(),
        );
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
        reconcileOpenAmountSliceConstraints();
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

watch(claimOutcome, (outcome): void => {
    if (outcome !== 'account_funding') {
        return;
    }

    reusableBalance.value = false;
    configureWholeAmountSlices();
    voucherType.value = 'redeemable';
    settlementRail.value = '';
    feeStrategy.value = 'absorb';
});

watch(onboardingEnabled, (enabled): void => {
    if (enabled) {
        reusableBalance.value = false;
    }
});

watch(
    [riderSplashHeadline, riderSplash, riderSplashCtaText],
    (currentValues, previousValues): void => {
        const hasCurrentSplash = currentValues.some(
            (value) => value.trim() !== '',
        );
        const hadPreviousSplash = previousValues.some(
            (value) => value.trim() !== '',
        );

        if (
            applyingStartingPoint.value ||
            startingPoint.value !== 'blank' ||
            riderStampArtworkSourceWasExplicitlySelected.value ||
            riderStampArtworkSource.value !== 'x_change' ||
            !hasCurrentSplash ||
            hadPreviousSplash
        ) {
            return;
        }

        riderStampArtworkSource.value = 'splash';
    },
);

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
    riderMessageFormat.value = 'plain';
    selectedInputFieldValues.value = [...defaults.inputFields];
    if (defaults.onboarding) {
        onboardingEnabled.value = true;
    }
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
    riderSplashFormat.value = 'plain';
    riderSplashCtaText.value = '';
    riderSplashTimeout.value = defaults.riderSplashTimeout;
    riderStampArtworkSource.value = 'x_change';
    riderStampArtworkSourceWasExplicitlySelected.value = false;
    riderStampArtworkTreatment.value = 'automatic';
    riderStampCopySource.value = 'automatic';
    riderStampShowLogo.value = true;
    riderStampShowTagline.value = true;
    riderStampClaimMarker.value = 'qr';
    riderStampClaimMarkerPosition.value = 'bottom_right';
    riderStampTitle.value = '';
    riderStampDescription.value = '';
    riderStampFit.value = 'cover';
    riderStampPosition.value = 'center';
    riderStampScrim.value = '18';
    riderStampTheme.value = 'automatic';
    riderStampDesignId.value = null;
    riderStampDesignVersion.value = null;
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
    reusableBalance.value = false;
    storedValueReplenishable.value = false;
    storedValueMaximumBalance.value = formatSliceAmount(
        normalizedPayCodeAmount(),
    );
    storedValueOtpAbove.value = '';
    voucherType.value = defaults.voucherType;
    targetAmount.value = defaults.targetAmount;
    includeExecutionInstruction.value = defaults.includeExecutionInstruction;
    executionDriver.value = defaults.executionDriver;
    claimOutcome.value = defaults.claimOutcome;
    executionPipeline.value =
        defaults.executionDriver === 'settlement_envelope'
            ? 'readiness, authorize, execute'
            : '';
    metadataFlowType.value = templateKey;
    lastStatus.value = 'ready';
    lastMessage.value = `${selectedTemplateName.value} defaults applied. Submit will call the existing x-change issuance handoff route.`;
    lastResponse.value = null;
}

function startBlank(): void {
    applyingStartingPoint.value = true;
    selectedTemplate.value = 'blank-pay-code';
    startingPoint.value = 'blank';
    activeSavedTemplate.value = null;
    applyTemplateDefaults('blank-pay-code');
    applyingStartingPoint.value = false;
    lastMessage.value = 'Blank Pay Code ready. Add only what this claim needs.';
    void focusAmountEditor();
}

function markRiderStampArtworkSourceSelection(): void {
    riderStampArtworkSourceWasExplicitlySelected.value = true;
}

function selectRiderDesignEditor(editor: RiderDesignEditor): void {
    riderDesignEditor.value = editor;
}

async function openDesignEditor(): Promise<void> {
    canvasView.value = 'design';
    riderDesignEditor.value = 'appearance';
    await nextTick();

    canvasSectionElement.value?.scrollIntoView?.({
        behavior: 'smooth',
        block: 'center',
    });
}

function hydrateLastInstructions(): void {
    const instructions = props.lastInstructions?.instructions;

    if (!instructions || props.campaignContext?.status === 'available') {
        return;
    }

    applyInstructionBlueprint(instructions, true);
    onboardingEnabled.value = props.onboardingPreset;
    startingPoint.value = 'last';
    activeSavedTemplate.value = savedTemplateRecordedBy(instructions);
    lastStatus.value = 'ready';
    lastMessage.value =
        'Your last successful Pay Code is ready to review or change.';
}

function repeatLastDesign(): void {
    const instructions = props.lastInstructions?.instructions;

    if (!instructions) {
        return;
    }

    applyInstructionBlueprint(instructions, true);
    startingPoint.value = 'last';
    activeSavedTemplate.value = savedTemplateRecordedBy(instructions);
    lastStatus.value = 'ready';
    lastMessage.value =
        'Last Pay Code settings restored. Add the new recipient before issuing.';
    void focusAmountEditor();
}

function savedTemplateRecordedBy(
    instructions: Record<string, unknown>,
): CockpitSavedPayCodeTemplate | null {
    const reference = instructionString(instructions, [
        'metadata',
        'custom',
        'cockpit',
        'saved_template',
        'reference',
    ]);

    if (reference === '') {
        return null;
    }

    return (
        props.savedTemplates?.find(
            (template) => template.reference === reference,
        ) ?? null
    );
}

function applySystemTemplate(templateKey: string): void {
    const template = props.templates.find(
        (candidate) => candidate.key === templateKey,
    );

    if (!template || template.disabled) {
        return;
    }

    applyingStartingPoint.value = true;
    selectedTemplate.value = templateKey;
    applyTemplateDefaults(templateKey);
    applyingStartingPoint.value = false;
    startingPoint.value =
        templateKey === 'blank-pay-code' ? 'blank' : 'template';
    activeSavedTemplate.value = null;
    templatePickerOpen.value = false;
    void focusAmountEditor();
}

function applySavedTemplate(template: CockpitSavedPayCodeTemplate): void {
    applyingStartingPoint.value = true;
    selectedTemplate.value = template.base_template_key;
    applyingStartingPoint.value = false;
    amount.value = '';
    purpose.value = '';
    applyInstructionBlueprint(template.instructions, true);
    startingPoint.value = 'template';
    activeSavedTemplate.value = template;
    templatePickerOpen.value = false;
    lastStatus.value = 'ready';
    lastMessage.value = `${template.name} is ready. Add the recipient and review before issuing.`;
    void focusAmountEditor();
}

function reusableTemplateInstructions(): Record<string, unknown> {
    const instructions = JSON.parse(JSON.stringify(buildPayload())) as Record<
        string,
        unknown
    >;
    const cash = instructionRecord(instructions, ['cash']);
    const cashValidation = instructionRecord(cash, ['validation']);
    const feedback = instructionRecord(instructions, ['feedback']);
    const metadata = instructionRecord(instructions, ['metadata']);
    const cockpit = instructionRecord(instructionRecord(metadata, ['custom']), [
        'cockpit',
    ]);

    delete cashValidation.secret;
    delete cashValidation.mobile;
    delete feedback.email;
    delete feedback.mobile;
    delete feedback.webhook;
    delete cockpit.recipient_reference;
    delete cockpit.campaign_context;
    delete cockpit.saved_template;
    delete metadata.campaign;
    delete instructions.starts_at;
    delete instructions.expires_at;

    return instructions;
}

function openSaveTemplateDialog(): void {
    const template = activeSavedTemplate.value;

    saveTemplateMode.value = template === null ? 'create' : 'update';
    saveTemplateName.value = template?.name ?? '';
    saveTemplateDescription.value = template?.description ?? '';
    saveTemplateIncludeAmount.value = template?.include_amount ?? false;
    saveTemplateIncludePurpose.value = template?.include_purpose ?? true;
    templateSaveError.value = '';
    saveTemplateOpen.value = true;
}

function chooseSaveTemplateMode(mode: 'create' | 'update'): void {
    const template = activeSavedTemplate.value;

    if (mode === 'update' && template === null) {
        return;
    }

    saveTemplateMode.value = mode;
    saveTemplateName.value =
        mode === 'create' && template !== null
            ? `${template.name} Copy`
            : (template?.name ?? '');
}

function saveTemplate(): void {
    const name = saveTemplateName.value.trim();
    const template = activeSavedTemplate.value;

    if (name === '') {
        templateSaveError.value = 'Give this template a short name.';

        return;
    }

    templateSaving.value = true;
    templateSaveError.value = '';

    const payload = {
        name,
        description: saveTemplateDescription.value.trim() || null,
        base_template_key: selectedTemplate.value,
        instructions: reusableTemplateInstructions(),
        include_amount: saveTemplateIncludeAmount.value,
        include_purpose: saveTemplateIncludePurpose.value,
    } as RequestPayload;
    const options = {
        preserveScroll: true,
        onSuccess: (page: { props: Record<string, unknown> }): void => {
            const savedTemplates = Array.isArray(page.props.saved_templates)
                ? (page.props.saved_templates as CockpitSavedPayCodeTemplate[])
                : (props.savedTemplates ?? []);
            const savedTemplate =
                saveTemplateMode.value === 'update' && template !== null
                    ? savedTemplates.find(
                          (candidate) =>
                              candidate.reference === template.reference,
                      )
                    : savedTemplates.find(
                          (candidate) => candidate.name === name,
                      );

            if (savedTemplate !== undefined) {
                activeSavedTemplate.value = savedTemplate;
                startingPoint.value = 'template';
            }

            saveTemplateOpen.value = false;
            templateSaveError.value = '';
            lastStatus.value = 'ready';
            lastMessage.value =
                saveTemplateMode.value === 'update'
                    ? `${name} updated for future Pay Codes.`
                    : `${name} saved to My Templates.`;
        },
        onError: (errors: Record<string, string>): void => {
            templateSaveError.value =
                String(Object.values(errors)[0] ?? '') ||
                'The template could not be saved.';
        },
        onFinish: (): void => {
            templateSaving.value = false;
        },
    };

    if (saveTemplateMode.value === 'update' && template !== null) {
        router.patch(
            CockpitPayCodeTemplateUpdateController(template.reference),
            {
                ...payload,
                expected_updated_at: template.updated_at,
            },
            options,
        );

        return;
    }

    router.post(CockpitPayCodeTemplateStoreController(), payload, options);
}

function applyRiderUrlLibraryPayload(payload: Record<string, unknown>): void {
    const url = typeof payload.url === 'string' ? payload.url.trim() : '';

    if (url === '') {
        return;
    }

    riderUrlPreset.value = '';
    riderUrl.value = url;
}

function applyRiderSplashLibraryPayload(
    payload: Record<string, unknown>,
): void {
    const splash =
        typeof payload.splash === 'string' ? payload.splash.trim() : '';
    const format =
        payload.format === 'markdown' || payload.format === 'html'
            ? payload.format
            : 'plain';

    if (splash === '') {
        return;
    }

    const meta =
        typeof payload.meta === 'object' && payload.meta !== null
            ? (payload.meta as Record<string, unknown>)
            : {};

    riderSplashHeadline.value = '';
    riderSplash.value = splash;
    riderSplashCtaText.value = '';
    riderSplashFormat.value = format;
    riderSplashMetaSanitized.value = meta.sanitized === true;
    riderSplashMetaProfile.value =
        typeof meta.html_profile === 'string' ? meta.html_profile : '';
}

function applyInstructionBlueprint(
    instructions: Record<string, unknown>,
    clearRecipient: boolean,
): void {
    const templateKey = instructionString(
        instructions,
        ['metadata', 'custom', 'cockpit', 'template_key'],
        selectedTemplate.value,
    );

    if (props.templates.some((template) => template.key === templateKey)) {
        applyingStartingPoint.value = true;
        selectedTemplate.value = templateKey;
        applyingStartingPoint.value = false;
    }

    amount.value = instructionString(
        instructions,
        ['cash', 'amount'],
        amount.value,
    );
    currency.value = instructionString(
        instructions,
        ['cash', 'currency'],
        currency.value,
    );
    count.value = instructionString(instructions, ['count'], count.value);
    provider.value = instructionString(
        instructions,
        ['provider'],
        provider.value,
    );

    const mobileRecipient = instructionString(instructions, [
        'cash',
        'validation',
        'mobile',
    ]);
    const payableRecipient = instructionString(instructions, [
        'cash',
        'validation',
        'payable',
    ]);
    recipientReference.value = instructionString(
        instructions,
        ['metadata', 'custom', 'cockpit', 'recipient_reference'],
        mobileRecipient ||
            (payableRecipient !== 'required' && payableRecipient !== ''
                ? `@${payableRecipient.replace(/^@/, '')}`
                : '') ||
            recipientReference.value,
    );
    purpose.value = instructionString(
        instructions,
        ['rider', 'message'],
        purpose.value,
    );
    riderMessageFormat.value = instructionContentFormat(
        instructions,
        ['rider', 'message_format'],
        'plain',
    );
    selectedInputFieldValues.value = instructionStringArray(instructions, [
        'inputs',
        'fields',
    ]);
    // Invitation mode is durable: a template or saved/last design may turn
    // it on, but never turns it off. Only the explicit mode control (see
    // setOnboardingMode) may disable it.
    if (
        dataGet(instructions, ['onboarding']) === true ||
        instructionString(instructions, ['claim', 'onboarding', 'mode']) ===
            'required'
    ) {
        onboardingEnabled.value = true;
    }

    validationSecret.value = '';
    requireMobileValidation.value =
        mobileRecipient !== '' ||
        dataGet(instructions, [
            'metadata',
            'custom',
            'cockpit',
            'template_preferences',
            'mobile_validation',
        ]) === true;
    requirePayableValidation.value = payableRecipient === 'required';
    requireCountryValidation.value =
        instructionString(instructions, ['cash', 'validation', 'country']) !==
        '';
    requireLocationValidation.value =
        instructionString(instructions, ['cash', 'validation', 'location']) !==
        '';

    const requirements = instructionStringArray(instructions, [
        'inputs',
        'requirements',
    ]);
    verificationKyc.value = requirements.includes('kyc');
    verificationOtp.value = requirements.includes('otp');
    verificationSelfie.value = requirements.includes('selfie');

    const structuredValidation = instructionRecord(instructions, [
        'validation',
    ]);
    const signature = instructionRecord(structuredValidation, ['signature']);
    const selfie = instructionRecord(structuredValidation, ['selfie']);
    const otp = instructionRecord(structuredValidation, ['otp']);
    const faceMatch = instructionRecord(structuredValidation, ['face_match']);
    const time = instructionRecord(structuredValidation, ['time']);
    const timeWindow = instructionRecord(time, ['window']);

    signatureRequired.value = signature.required === true;
    signatureFailure.value = signature.on_failure === 'warn' ? 'warn' : 'block';
    verificationSelfie.value =
        verificationSelfie.value || selfie.required === true;
    selfieFailure.value = selfie.on_failure === 'warn' ? 'warn' : 'block';
    verificationOtp.value = verificationOtp.value || otp.required === true;
    otpFailure.value = otp.on_failure === 'warn' ? 'warn' : 'block';
    faceMatchRequired.value = faceMatch.required === true;
    faceMatchFailure.value = faceMatch.on_failure === 'warn' ? 'warn' : 'block';
    faceMatchConfidence.value = instructionString(
        faceMatch,
        ['min_confidence'],
        faceMatchConfidence.value,
    );
    timeValidationEnabled.value = Object.keys(time).length > 0;
    timeWindowStart.value = instructionString(
        timeWindow,
        ['start_time'],
        timeWindowStart.value,
    );
    timeWindowEnd.value = instructionString(
        timeWindow,
        ['end_time'],
        timeWindowEnd.value,
    );
    timeWindowTimezone.value = instructionString(
        timeWindow,
        ['timezone'],
        timeWindowTimezone.value,
    );
    timeLimitMinutes.value = instructionString(
        time,
        ['limit_minutes'],
        timeLimitMinutes.value,
    );
    timeTrackDuration.value =
        typeof time.track_duration === 'boolean'
            ? time.track_duration
            : timeTrackDuration.value;

    riderUrl.value = instructionString(instructions, ['rider', 'url']);
    riderRedirectTimeout.value = instructionString(instructions, [
        'rider',
        'redirect_timeout',
    ]);
    riderSplashHeadline.value = '';
    riderSplash.value = instructionString(instructions, ['rider', 'splash']);
    riderSplashFormat.value = instructionContentFormat(
        instructions,
        ['rider', 'splash_format'],
        riderSplash.value === '' ? 'plain' : 'html',
    );
    riderSplashCtaText.value = '';
    riderSplashTimeout.value = instructionString(
        instructions,
        ['rider', 'splash_timeout'],
        riderSplashTimeout.value,
    );
    riderSplashMetaSanitized.value =
        dataGet(instructions, ['rider', 'splash_meta', 'sanitized']) !== false;
    riderSplashMetaProfile.value = instructionString(instructions, [
        'rider',
        'splash_meta',
        'html_profile',
    ]);
    const stampSource = instructionString(instructions, [
        'rider',
        'stamp',
        'source',
    ]);
    const legacyStampSource =
        stampSource === 'automatic'
            ? ''
            : stampSource ||
              instructionString(instructions, ['rider', 'og_source']);
    riderStampArtworkSource.value = instructionStampArtworkSource(
        instructions,
        ['rider', 'stamp', 'artwork_source'],
        legacyStampSource,
    );
    riderStampArtworkTreatment.value = instructionStampArtworkTreatment(
        instructions,
        ['rider', 'stamp', 'artwork_treatment'],
    );
    riderStampCopySource.value = instructionStampCopySource(
        instructions,
        ['rider', 'stamp', 'copy_source'],
        legacyStampSource,
    );
    riderStampShowLogo.value =
        dataGet(instructions, ['rider', 'stamp', 'show_logo']) !== false;
    riderStampShowTagline.value =
        dataGet(instructions, ['rider', 'stamp', 'show_tagline']) !== false;
    riderStampClaimMarker.value = instructionStampClaimMarker(instructions, [
        'rider',
        'stamp',
        'claim_marker',
    ]);
    riderStampClaimMarkerPosition.value = instructionStampClaimMarkerPosition(
        instructions,
        ['rider', 'stamp', 'claim_marker_position'],
    );
    riderStampTitle.value = instructionString(instructions, [
        'rider',
        'stamp',
        'title',
    ]);
    riderStampDescription.value = instructionString(instructions, [
        'rider',
        'stamp',
        'description',
    ]);
    riderStampFit.value = instructionStampFit(instructions, [
        'rider',
        'stamp',
        'fit',
    ]);
    riderStampPosition.value = instructionStampPosition(instructions, [
        'rider',
        'stamp',
        'position',
    ]);
    riderStampScrim.value = instructionString(
        instructions,
        ['rider', 'stamp', 'scrim'],
        '18',
    );
    riderStampTheme.value = instructionStampTheme(instructions, [
        'rider',
        'stamp',
        'theme',
    ]);
    riderStampDesignId.value =
        instructionString(instructions, ['rider', 'stamp', 'design_id']) ||
        null;
    const hydratedStampDesignVersion = Number(
        dataGet(instructions, ['rider', 'stamp', 'design_version']),
    );
    riderStampDesignVersion.value =
        Number.isInteger(hydratedStampDesignVersion) &&
        hydratedStampDesignVersion > 0
            ? hydratedStampDesignVersion
            : null;

    feedbackEmail.value = instructionString(instructions, [
        'feedback',
        'email',
    ]);
    feedbackMobile.value = instructionString(instructions, [
        'feedback',
        'mobile',
    ]);
    feedbackWebhook.value = instructionString(instructions, [
        'feedback',
        'webhook',
    ]);
    feedbackEmailEnabled.value = feedbackEmail.value !== '';
    feedbackMobileEnabled.value = feedbackMobile.value !== '';
    feedbackWebhookEnabled.value = feedbackWebhook.value !== '';

    prefix.value = instructionString(instructions, ['prefix']);
    mask.value = instructionString(instructions, ['mask']);
    startsAt.value = instructionString(instructions, ['starts_at']);
    expiresAt.value = instructionString(instructions, ['expires_at']);

    const lastTtl = instructionString(instructions, ['ttl']);
    const knownExpiryPresets = ['P12H', 'P1D', 'P3D', 'P7D'];

    if (knownExpiryPresets.includes(lastTtl)) {
        expiryPreset.value = lastTtl as 'P12H' | 'P1D' | 'P3D' | 'P7D';
        ttl.value = '';
    } else if (lastTtl !== '') {
        expiryPreset.value = 'custom';
        ttl.value = lastTtl;
    } else {
        expiryPreset.value = 'none';
        ttl.value = '';
    }

    settlementRail.value = instructionString(instructions, [
        'cash',
        'settlement_rail',
    ]);
    const rememberedFeeStrategy = instructionString(instructions, [
        'cash',
        'fee_strategy',
    ]);

    if (
        rememberedFeeStrategy === 'absorb' ||
        rememberedFeeStrategy === 'include' ||
        rememberedFeeStrategy === 'add'
    ) {
        feeStrategy.value = rememberedFeeStrategy;
    }

    const rememberedCashType = instructionString(instructions, [
        'cash',
        'type',
    ]);
    const knownCashType = cashTypeOptions.some(
        (option) => option.value === rememberedCashType,
    );
    cashType.value =
        rememberedCashType === ''
            ? 'default'
            : knownCashType
              ? rememberedCashType
              : 'custom';
    customCashType.value = knownCashType ? '' : rememberedCashType;

    const rememberedMandates = instructionStringArray(instructions, [
        'cash',
        'mandates',
    ]);
    const knownMandates = new Set(mandateOptions.map((option) => option.value));
    selectedMandates.value = rememberedMandates.filter((mandate) =>
        knownMandates.has(mandate),
    );
    customMandates.value = rememberedMandates
        .filter((mandate) => !knownMandates.has(mandate))
        .join(', ');

    hydrateLastSlices(instructions);

    const rememberedVoucherType = instructionString(
        instructions,
        ['voucher_type'],
        'redeemable',
    );

    if (
        rememberedVoucherType === 'redeemable' ||
        rememberedVoucherType === 'payable' ||
        rememberedVoucherType === 'settlement'
    ) {
        voucherType.value = rememberedVoucherType;
    }

    const rememberedClaimOutcome = instructionString(instructions, [
        'claim',
        'default_outcome',
    ]);

    if (
        rememberedClaimOutcome === 'provider_disbursement' ||
        rememberedClaimOutcome === 'account_funding'
    ) {
        claimOutcome.value = rememberedClaimOutcome;
    }

    targetAmount.value = instructionString(instructions, ['target_amount']);
    rulesMinPayment.value = instructionString(instructions, [
        'rules',
        'min_payment',
    ]);
    rulesMaxPayment.value = instructionString(instructions, [
        'rules',
        'max_payment',
    ]);
    rulesAllowOverpayment.value =
        dataGet(instructions, ['rules', 'allow_overpayment']) === true;
    rulesAutoCloseOnFullPayment.value =
        dataGet(instructions, ['rules', 'auto_close_on_full_payment']) !==
        false;

    const execution = instructionRecord(instructions, ['execution']);
    const storedValuePolicy = instructionRecord(instructions, ['stored_value']);
    const compiledStoredValuePolicy = instructionRecord(execution, [
        'metadata',
        'stored_value',
    ]);
    reusableBalance.value =
        dataGet(storedValuePolicy, ['enabled']) === true ||
        instructionString(execution, ['driver']) === 'stored_value';
    storedValueReplenishable.value =
        dataGet(storedValuePolicy, ['replenishable']) === true ||
        dataGet(compiledStoredValuePolicy, ['replenishable']) === true;
    const rememberedMaximumBalance =
        instructionString(storedValuePolicy, ['maximum_balance']) ||
        minorToMajorInstructionValue(
            dataGet(compiledStoredValuePolicy, ['max_balance']),
        );
    const rememberedOtpAbove =
        instructionString(storedValuePolicy, ['otp_required_above']) ||
        minorToMajorInstructionValue(
            dataGet(compiledStoredValuePolicy, ['otp_required_above']),
        );
    storedValueMaximumBalance.value =
        rememberedMaximumBalance ||
        formatSliceAmount(normalizedPayCodeAmount());
    storedValueOtpAbove.value = rememberedOtpAbove;
    includeExecutionInstruction.value = Object.keys(execution).length > 0;
    executionSchema.value = instructionString(
        execution,
        ['schema'],
        executionSchema.value,
    );
    executionDriver.value = instructionString(
        execution,
        ['driver'],
        executionDriver.value,
    );
    executionMode.value = instructionString(execution, ['mode']);
    executionPipeline.value = instructionStringArray(execution, [
        'pipeline',
    ]).join(', ');
    executionFallback.value = instructionString(execution, ['fallback']);
    executionVisibility.value = instructionStringArray(execution, [
        'visibility',
    ]).join(', ');
    executionMetadata.value = instructionString(execution, [
        'metadata',
        'operator_note',
    ]);

    metadataFlowType.value = instructionString(instructions, [
        'metadata',
        'flow_type',
    ]);
    metadataIssuerId.value = '';
    metadataCollectionWalletId.value = '';

    if (clearRecipient) {
        recipientReference.value = '';
        validationSecret.value = '';
        feedbackEmail.value = '';
        feedbackMobile.value = '';
        feedbackWebhook.value = '';
        feedbackEmailEnabled.value = false;
        feedbackMobileEnabled.value = false;
        feedbackWebhookEnabled.value = false;
        startsAt.value = '';
        expiresAt.value = '';
    }
}

function hydrateLastSlices(instructions: Record<string, unknown>): void {
    const rememberedSlices = dataGet(instructions, ['metadata', 'slices']);
    const rememberedMode = instructionString(instructions, [
        'cash',
        'slice_mode',
    ]);

    if (Array.isArray(rememberedSlices) && rememberedSlices.length > 0) {
        sliceMode.value = 'named';
        namedClaimSlices.value = rememberedSlices.map((slice, index) => {
            const item =
                typeof slice === 'object' && slice !== null
                    ? (slice as Record<string, unknown>)
                    : {};

            return {
                id: instructionString(item, ['id'], namedClaimSliceId(index)),
                amount: instructionString(item, ['amount'], '0'),
                description: instructionString(
                    item,
                    ['description'],
                    defaultFixedSliceDescription(index),
                ),
                tag: instructionString(item, ['tag']),
                claim_on: instructionString(item, ['claim_on']),
                claim_by: instructionString(item, ['claim_by']),
            };
        });
        slices.value = String(namedClaimSlices.value.length);
        maxSlices.value = String(namedClaimSlices.value.length);
        minWithdrawal.value = instructionString(
            instructions,
            ['cash', 'min_withdrawal'],
            String(issuerDefaultMinimumWithdrawal),
        );

        return;
    }

    if (rememberedMode === 'fixed') {
        const rememberedCount = Math.max(
            1,
            Number(instructionString(instructions, ['cash', 'slices'], '1')),
        );

        sliceMode.value = 'fixed';
        slices.value = String(rememberedCount);
        maxSlices.value = String(rememberedCount);
        minWithdrawal.value = String(issuerDefaultMinimumWithdrawal);
        namedClaimSlices.value = equalFixedNamedClaimSlices(rememberedCount);

        return;
    }

    if (rememberedMode === 'open') {
        sliceMode.value = 'open';
        slices.value = '1';
        maxSlices.value = instructionString(
            instructions,
            ['cash', 'max_slices'],
            '2',
        );
        minWithdrawal.value = instructionString(
            instructions,
            ['cash', 'min_withdrawal'],
            String(issuerDefaultMinimumWithdrawal),
        );
        namedClaimSlices.value = defaultOpenNamedClaimSlices();

        return;
    }

    sliceMode.value = 'whole';
    slices.value = '1';
    maxSlices.value = '1';
    minWithdrawal.value = String(issuerDefaultMinimumWithdrawal);
    namedClaimSlices.value = defaultWholeNamedClaimSlices();
}

function minorToMajorInstructionValue(value: unknown): string {
    const minor = Number(value);

    return Number.isFinite(minor) && minor >= 0
        ? formatSliceAmount(minor / 100)
        : '';
}

const routeUrl = computed<string | null>(() =>
    stringValue(props.mutationContract?.route_url),
);
const routeName = computed<string>(
    () => stringValue(props.mutationContract?.route) ?? 'not-loaded',
);
const claimPreviewRouteUrl = computed<string | null>(() =>
    stringValue(props.claimPreviewContract?.route_url),
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

const feedbackDestinations = computed<FeedbackDestinations>({
    get: () => ({
        email: feedbackEmail.value.trim().toLowerCase(),
        mobile: normalizePhilippineMobile(feedbackMobile.value),
        webhook: feedbackWebhook.value.trim(),
    }),
    set: (destinations) => {
        feedbackEmail.value = destinations.email;
        feedbackMobile.value = destinations.mobile;
        feedbackWebhook.value = destinations.webhook;
    },
});

const feedbackDestinationDefaults = computed<Partial<FeedbackDestinations>>(
    () => ({
        email: defaultFeedbackEmail.value,
        mobile: defaultFeedbackMobile.value,
        webhook: defaultFeedbackWebhook.value,
    }),
);

const feedbackUnavailableReasons = computed<
    Partial<Record<FeedbackChannel, string>>
>(() => ({
    ...(capabilityUnavailable('feedback.email')
        ? {
              email: capabilityReason(
                  'feedback.email',
                  'Email delivery is unavailable.',
              ),
          }
        : {}),
    ...(capabilityUnavailable('feedback.sms')
        ? {
              mobile: capabilityReason(
                  'feedback.sms',
                  'SMS delivery is unavailable.',
              ),
          }
        : {}),
    ...(capabilityUnavailable('feedback.webhook')
        ? {
              webhook: capabilityReason(
                  'feedback.webhook',
                  'Webhook delivery is unavailable.',
              ),
          }
        : {}),
}));

const feedbackEmailError = computed<string | null>(() => {
    const email = feedbackEmail.value.trim();

    if (email === '') {
        return feedbackEmailEnabled.value
            ? 'Email updates are selected. Enter a valid email address.'
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
            ? 'Mobile updates are selected. Enter a Philippine mobile number.'
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
            ? 'Webhook updates are selected. Enter a webhook URL.'
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
        ...feedbackTokenErrors.value,
    ].filter((error): error is string => error !== null);
});

const feedbackValid = computed<boolean>(() => {
    return feedbackValidationErrors.value.length === 0;
});

watch(feedbackEmail, (): void => {
    feedbackEmailEnabled.value = feedbackEmail.value.trim() !== '';
});

watch(feedbackMobile, (): void => {
    feedbackMobileEnabled.value = feedbackMobile.value.trim() !== '';
});

watch(feedbackWebhook, (): void => {
    feedbackWebhookEnabled.value = feedbackWebhook.value.trim() !== '';
});

const selectedUnavailableCapabilities = computed<
    CockpitInstructionCapabilityReadiness[]
>(() => {
    const selected = new Set<string>();

    selectedInputFields.value.forEach((field) => {
        const capability = inputFieldCapability(field);

        if (capability !== null) {
            selected.add(capability);
        }
    });

    if (requireLocationValidation.value) {
        selected.add('location');
    }

    if (verificationKyc.value) {
        selected.add('kyc');
    }

    if (effectiveVerificationOtp.value) {
        selected.add('otp');
    }

    if (verificationSelfie.value) {
        selected.add('selfie');
    }

    if (signatureRequired.value) {
        selected.add('signature');
    }

    if (normalizedFeedbackMobile.value !== '') {
        selected.add('feedback.sms');
    }

    if (feedbackEmail.value.trim() !== '') {
        selected.add('feedback.email');
    }

    if (feedbackWebhook.value.trim() !== '') {
        selected.add('feedback.webhook');
    }

    if (reusableBalance.value) {
        selected.add('stored_value');
    }

    return [...selected]
        .map((key) => capabilityReadiness(key))
        .filter(
            (capability): capability is CockpitInstructionCapabilityReadiness =>
                capability?.issuance_allowed === false,
        );
});

const canSubmit = computed<boolean>(() => {
    return (
        props.mutationContract?.runtime_enabled === true &&
        routeUrl.value !== null &&
        allowedMethods.value.includes('POST') &&
        selectedUnavailableCapabilities.value.length === 0 &&
        payeePolicy.value.issuable &&
        feedbackValid.value &&
        namedClaimSliceValidationMessage.value === null &&
        storedValuePolicyError.value === null &&
        claimRecipientError.value === null &&
        settlementRailSelectionError.value === null &&
        (!isAccountFundingClaim.value || sliceMode.value === 'whole')
    );
});

const canGenerateClaimPreview = computed<boolean>(() => {
    const normalizedAmount = Number(amount.value);

    return (
        claimPreviewRouteUrl.value !== null &&
        !previewProcessing.value &&
        Number.isFinite(normalizedAmount) &&
        normalizedAmount > 0 &&
        payeePolicy.value.issuable &&
        feedbackValid.value &&
        namedClaimSliceValidationMessage.value === null &&
        storedValuePolicyError.value === null &&
        claimRecipientError.value === null &&
        settlementRailSelectionError.value === null
    );
});

const isAccountFundingClaim = computed<boolean>(
    () => claimOutcome.value === 'account_funding',
);

const storedValueCapability = computed(() =>
    capabilityReadiness('stored_value'),
);
const storedValueAvailable = computed<boolean>(() => {
    return (
        storedValueCapability.value?.issuance_allowed === true &&
        !isAccountFundingClaim.value &&
        !onboardingEnabled.value
    );
});
const storedValueUnavailableReason = computed<string>(() => {
    if (isAccountFundingClaim.value) {
        return 'Account Funding and Reusable Balance cannot be combined.';
    }

    if (onboardingEnabled.value) {
        return 'Invitation ownership for Reusable Balance is not commissioned yet.';
    }

    return (
        storedValueCapability.value?.reason ??
        'Reusable Balance is unavailable until its durable wallet engine is commissioned.'
    );
});
const normalizedStoredValueMaximumBalance = computed<number>(() => {
    const configured = Number(storedValueMaximumBalance.value);

    return Number.isFinite(configured) && configured > 0
        ? configured
        : normalizedPayCodeAmount();
});
const normalizedStoredValueOtpAbove = computed<number | null>(() => {
    if (storedValueOtpAbove.value.trim() === '') {
        return null;
    }

    const configured = Number(storedValueOtpAbove.value);

    return Number.isFinite(configured) && configured >= 0 ? configured : null;
});
const storedValuePolicyError = computed<string | null>(() => {
    if (!reusableBalance.value) {
        return null;
    }

    if (!storedValueAvailable.value) {
        return storedValueUnavailableReason.value;
    }

    if (
        normalizedStoredValueMaximumBalance.value + 0.0001 <
        normalizedPayCodeAmount()
    ) {
        return 'Maximum balance cannot be lower than the starting balance.';
    }

    if (
        normalizedStoredValueOtpAbove.value !== null &&
        normalizedStoredValueOtpAbove.value >
            normalizedStoredValueMaximumBalance.value
    ) {
        return 'OTP threshold cannot be higher than the maximum balance.';
    }

    return null;
});

const claimRecipientError = computed<string | null>(() => {
    if (
        !isAccountFundingClaim.value ||
        ['anyone', 'mobile'].includes(payeeType.value)
    ) {
        return null;
    }

    return 'Account Funding recipients must be CASH or a verified Philippine mobile.';
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

const resultClaimOutcome = computed<string>(
    () =>
        stringValue(
            dataGet(lastResponse.value, ['result', 'claim', 'outcome']),
        ) ?? 'provider_disbursement',
);

const isAccountFundingResult = computed<boolean>(
    () => resultClaimOutcome.value === 'account_funding',
);

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

const beneficiaryClaimQr = computed<string | null>(() => {
    return stringValue(
        dataGet(lastResponse.value, ['result', 'links', 'claim_qr']),
    );
});

const beneficiaryShareCardUrl = computed<string | null>(() => {
    return stringValue(
        dataGet(lastResponse.value, ['result', 'links', 'share_card']),
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

const beneficiaryClaimRouteLabel = computed<string>(() => {
    const value =
        beneficiaryClaimUrl.value ?? beneficiaryRedeemPath.value ?? '';

    if (value.includes('/x/claim/') && value.includes('/experience')) {
        return 'Claim experience URL';
    }

    if (value.includes('/disburse')) {
        return 'Legacy disburse URL';
    }

    return 'Beneficiary URL';
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
            label: isAccountFundingResult.value
                ? 'Claim path'
                : 'Beneficiary URL',
            value: isAccountFundingResult.value
                ? 'Account Funding'
                : beneficiaryClaimUrl.value !== null
                  ? 'ready to copy'
                  : 'not returned',
            detail: isAccountFundingResult.value
                ? 'The whole Pay Code amount can be added to one authenticated Account.'
                : beneficiaryClaimUrl.value !== null
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

const downstreamHandoffSummary = computed<
    Array<{ label: string; status: string; detail: string }>
>(() => {
    return [
        {
            label: 'Journal',
            status:
                stringValue(
                    dataGet(lastResponse.value, [
                        'activity',
                        'journal_handoff_status',
                    ]),
                ) ??
                stringValue(
                    dataGet(lastResponse.value, [
                        'activity',
                        'metadata',
                        'journal_handoff',
                        'status',
                    ]),
                ) ??
                'not wired',
            detail: 'Journal evidence remains separately configured.',
        },
        {
            label: 'Action',
            status:
                stringValue(
                    dataGet(lastResponse.value, [
                        'activity',
                        'action_handoff_status',
                    ]),
                ) ??
                stringValue(
                    dataGet(lastResponse.value, [
                        'activity',
                        'metadata',
                        'action_handoff',
                        'status',
                    ]),
                ) ??
                'not wired',
            detail: 'Action continuation remains presentation-only unless explicitly enabled.',
        },
        {
            label: 'Feedback',
            status:
                stringValue(
                    dataGet(lastResponse.value, [
                        'activity',
                        'feedback_handoff_status',
                    ]),
                ) ??
                stringValue(
                    dataGet(lastResponse.value, [
                        'activity',
                        'metadata',
                        'feedback_handoff',
                        'status',
                    ]),
                ) ??
                'not wired',
            detail: 'Feedback delivery is not sent by this result card.',
        },
    ];
});

const pricingPreflight =
    computed<CockpitQuickGenerateRuntimePricingPreflight | null>(() => {
        return objectValue(
            dataGet(lastResponse.value, ['preflight', 'pricing']),
        ) as CockpitQuickGenerateRuntimePricingPreflight | null;
    });

const issuedCostEstimate = computed<PayCodeCostEstimate | null>(() => {
    return objectValue(
        dataGet(lastResponse.value, ['result', 'issue_cost']),
    ) as PayCodeCostEstimate | null;
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

const fundingWorkspaceUrl = computed<string | null>(() => {
    const item = postIssuanceNavigationItems.value.find(
        (candidate) =>
            candidate.key === 'account_funding' && candidate.enabled === true,
    );

    return stringValue(item?.href);
});

const campaignReturnNavigationItems = computed<
    CockpitQuickGeneratePostIssuanceNavigationItem[]
>(() => {
    if (!campaignAttributionAvailable.value) {
        return [];
    }

    return postIssuanceNavigationItems.value.filter((item) => {
        const key = stringValue(item.key) ?? '';

        return key.startsWith('campaign_');
    });
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

const currentTemplateName = computed<string>(
    () => activeSavedTemplate.value?.name ?? selectedTemplateName.value,
);

const canvasInstructionKeys = computed<string[]>(() => {
    const keys = selectedInputFields.value.map((field) => `input.${field}`);

    if (effectiveMobileValidation.value && payeeRequiresMobile.value) {
        keys.push('validation.mobile');
    }

    if (effectiveVerificationOtp.value) {
        keys.push('validation.otp');
    }

    if (verificationKyc.value) {
        keys.push('validation.identity');
    }

    if (verificationSelfie.value) {
        keys.push('validation.selfie');
    }

    if (signatureRequired.value) {
        keys.push('validation.signature');
    }

    if (requireLocationValidation.value) {
        keys.push('validation.location');
    }

    if (timeValidationEnabled.value) {
        keys.push('validation.time');
    }

    if (sliceMode.value !== 'whole') {
        keys.push('claim.multiple');
    }

    return [...new Set(keys)];
});

const canvasExpiryLabel = computed<string>(() => {
    if (expiresAt.value.trim() !== '') {
        return 'Exact Expiration';
    }

    if (expiryPreset.value === 'none') {
        return 'No Expiration';
    }

    const labels: Record<string, string> = {
        P12H: '12 Hours',
        P1D: '1 Day',
        P3D: '3 Days',
        P7D: '7 Days',
        custom: `${expiryCustomDays.value || 'Custom'} Days`,
    };

    return labels[expiryPreset.value] ?? expiryPreset.value;
});

const voucherKindLabel = computed<string>(() => {
    if (onboardingEnabled.value) {
        return 'Account Invitation';
    }

    if (reusableBalance.value) {
        return 'Stored Value';
    }

    const labels: Record<typeof voucherType.value, string> = {
        redeemable: 'Disburseable',
        payable: 'Payable',
        settlement: 'Settlement',
    };

    return labels[voucherType.value];
});

const voucherKindTone = computed<string>(() => {
    if (onboardingEnabled.value) {
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-200';
    }

    if (reusableBalance.value) {
        return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-800 dark:bg-violet-950/60 dark:text-violet-200';
    }

    if (voucherType.value === 'payable') {
        return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-200';
    }

    if (voucherType.value === 'settlement') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200';
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200';
});

const onboardingOtpEnforced = computed<boolean>(() => {
    return (
        onboardingEnabled.value &&
        (props.onboardingOtpRequired !== false || payeeType.value === 'mobile')
    );
});

const effectiveVerificationOtp = computed<boolean>(
    () =>
        verificationOtp.value ||
        onboardingOtpEnforced.value ||
        payeeRequiresMobile.value,
);

const effectiveMobileValidation = computed<boolean>(
    () => requireMobileValidation.value || payeeRequiresMobile.value,
);

const mobileValidationSelection = computed<boolean>({
    get: () => effectiveMobileValidation.value,
    set: (selected): void => {
        if (!payeeRequiresMobile.value) {
            requireMobileValidation.value = selected;
        }
    },
});

const otpSelection = computed<boolean>({
    get: () => effectiveVerificationOtp.value,
    set: (selected): void => {
        if (!payeeRequiresMobile.value && !onboardingOtpEnforced.value) {
            verificationOtp.value = selected;
        }
    },
});

const effectiveValidationSecret = computed<string>(() =>
    payeeRequiresSecret.value
        ? (payeePolicy.value.normalizedValue ?? '')
        : validationSecret.value.trim(),
);

// The only place allowed to disable Invitation mode explicitly. Templates,
// saved templates, and Repeat Last only ever durably turn it on (see
// applyTemplateDefaults/applyInstructionBlueprint) so switching Pay Code mode
// off requires this deliberate operator action.
function setOnboardingMode(enabled: boolean): void {
    if (!enabled && onboardingEnabled.value) {
        // While Invitation mode is on, selectedInputFields projects Name,
        // Email, Mobile, and (when applicable) OTP on top of whatever is
        // canonically selected — those projected fields may never have been
        // written back into selectedInputFieldValues (e.g. after Blank
        // replaced it with an empty array). Materialize the currently
        // effective set now, before turning onboarding off, so an explicit
        // switch to Pay Code preserves them as ordinary, unlocked, removable
        // requirements instead of silently dropping them.
        selectedInputFieldValues.value = [...selectedInputFields.value];
    }

    onboardingEnabled.value = enabled;
}

function applyOnboardingDependencies(): void {
    if (!onboardingEnabled.value) {
        return;
    }

    const fields = new Set(selectedInputFieldValues.value);
    fields.add('name');
    fields.add('email');
    fields.add('mobile');

    if (onboardingOtpEnforced.value) {
        fields.add('otp');
        verificationOtp.value = true;
    }

    selectedInputFieldValues.value = voucherInputFieldPayloadOrder.filter(
        (field) => fields.has(field),
    );
}

function isAutomaticInputField(field: string): boolean {
    if (field === 'mobile' && payeeRequiresMobile.value) {
        return true;
    }

    if (field === 'email' && payeeRequiresEmail.value) {
        return true;
    }

    if (field === 'otp' && payeeRequiresMobile.value) {
        return true;
    }

    return (
        onboardingEnabled.value &&
        (['name', 'email', 'mobile'].includes(field) ||
            (field === 'otp' && onboardingOtpEnforced.value))
    );
}

function isInputFieldSelected(field: string): boolean {
    return selectedInputFields.value.includes(field);
}

function toggleInputField(field: string, selected: boolean): void {
    if (isAutomaticInputField(field)) {
        return;
    }

    const fields = new Set(selectedInputFieldValues.value);

    if (selected) {
        fields.add(field);
    } else {
        fields.delete(field);
    }

    selectedInputFieldValues.value = voucherInputFieldPayloadOrder.filter(
        (candidate) => fields.has(candidate),
    );
}

// Human-readable explanation for a locked/automatic Claim Requirements chip.
// This mirrors isAutomaticInputField's existing conditions exactly and adds
// no new dependency policy — it only narrates rules that already exist.
function automaticInputFieldReason(field: string): string {
    if (field === 'mobile' && payeeRequiresMobile.value) {
        return 'Required because Pay To is a mobile number.';
    }

    if (field === 'otp' && payeeRequiresMobile.value) {
        return 'Required because Pay To is a mobile number.';
    }

    if (field === 'email' && payeeRequiresEmail.value) {
        return 'Required because Pay To is an email address.';
    }

    if (
        onboardingEnabled.value &&
        ['name', 'email', 'mobile'].includes(field)
    ) {
        return 'Required because Invitation mode is enabled.';
    }

    if (field === 'otp' && onboardingOtpEnforced.value) {
        return 'Required because Invitation mode requires a one-time passcode.';
    }

    return 'Required by an existing claim rule.';
}

// Optional price hint sourced only from the existing live pricing estimate's
// charges (see usePayCodeCostEstimate below) — never calculated locally.
function claimRequirementPriceLabel(field: string): string | null {
    const charges = livePricingEstimate.value?.charges;

    if (!Array.isArray(charges)) {
        return null;
    }

    const charge = charges.find(
        (candidate) =>
            candidate.catalog_item_reference === `inputs.fields.${field}`,
    );
    const price = optionalMoney(
        charge?.price ?? charge?.amount ?? charge?.total,
    );

    return price === null ? null : `+${formatAccountMoney(price)}`;
}

// Compact Order-card Claim Requirements control: a second *view* of the
// same voucherInputFieldOptions / selectedInputFieldValues state used by
// the detailed Claim Experience controls below, never a competing model.
const claimRequirementOptions = computed<CockpitClaimRequirementOption[]>(
    () => {
        return voucherInputFieldOptions.map((field) => {
            const indicator = resolvePayCodeIndicator(`input.${field.value}`);
            const capabilityKey = inputFieldCapability(field.value);
            const selected = isInputFieldSelected(field.value);
            const locked = isAutomaticInputField(field.value);
            const unavailable =
                capabilityKey !== null && capabilityUnavailable(capabilityKey);

            return {
                value: field.value,
                label: indicator.label,
                icon: indicator.icon,
                category: claimRequirementCategory(field.value),
                helper: field.helper,
                selected,
                locked,
                lockedReason: locked
                    ? automaticInputFieldReason(field.value)
                    : null,
                disabled:
                    locked ||
                    (capabilityKey !== null &&
                        capabilitySelectionDisabled(capabilityKey, selected)),
                unavailable,
                unavailableReason:
                    capabilityKey !== null && unavailable
                        ? capabilityReason(capabilityKey, field.helper)
                        : null,
                priceLabel: claimRequirementPriceLabel(field.value),
            };
        });
    },
);

const claimRequirementPresets: CockpitClaimRequirementPreset[] = [
    { key: 'basic_identity', label: 'Basic Identity' },
    { key: 'proof_of_receipt', label: 'Proof of Receipt' },
    { key: 'full_identity_check', label: 'Full Identity Check' },
    { key: 'clear_optional', label: 'Clear Optional Requirements' },
];

// Presets only bundle multiple existing toggleInputField() calls over the
// existing catalog; they invent no new Voucher Instruction semantics.
const claimRequirementPresetFields: Record<string, string[]> = {
    basic_identity: ['name', 'reference_code'],
    proof_of_receipt: ['signature', 'selfie'],
    full_identity_check: ['name', 'address', 'kyc', 'signature', 'selfie'],
};

function applyClaimRequirementPreset(key: string): void {
    if (key === 'clear_optional') {
        selectedInputFields.value
            .filter((field) => !isAutomaticInputField(field))
            .forEach((field) => toggleInputField(field, false));

        return;
    }

    (claimRequirementPresetFields[key] ?? []).forEach((field) => {
        const capabilityKey = inputFieldCapability(field);

        if (capabilityKey !== null && capabilityUnavailable(capabilityKey)) {
            return;
        }

        toggleInputField(field, true);
    });
}

watch(
    [onboardingEnabled, onboardingOtpEnforced],
    (): void => {
        applyOnboardingDependencies();
    },
    { flush: 'sync', immediate: true },
);

const payeeHelpText = computed<string>(() => {
    if (isAccountFundingClaim.value && payeeType.value === 'mobile') {
        return `Restricted to the verified Account for ${normalizedPayee.value}.`;
    }

    if (
        isAccountFundingClaim.value &&
        !['anyone', 'mobile'].includes(payeeType.value)
    ) {
        return (
            claimRecipientError.value ??
            'Account Funding requires an Account recipient.'
        );
    }

    if (isAccountFundingClaim.value) {
        return 'CASH or blank creates a bearer Pay Code. Whoever holds it can add it to their Account.';
    }

    if (payeeType.value === 'mobile') {
        return `${payeePolicy.value.message} Mobile and OTP requirements are locked.`;
    }

    if (payeeType.value === 'vendor') {
        return `${payeePolicy.value.message} Vendor matching is locked.`;
    }

    if (payeeType.value === 'email') {
        return 'Email recipient recognized. Issuance stays unavailable until the email OTP capability is implemented.';
    }

    if (payeeType.value === 'secret') {
        return 'Explicit release secret. Quotes are removed before issuance and the value is kept out of previews.';
    }

    if (payeeType.value === 'invalid') {
        return payeePolicy.value.message;
    }

    return 'Blank or CASH allows anyone who meets the other claim requirements.';
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
            source: 'Exact Time',
            label: `Expires At: ${absoluteExpiresAt}`,
            payload,
        };
    }

    const advancedTtl = ttl.value.trim();

    if (advancedTtl !== '') {
        const payload: Record<string, string> = {
            ttl: advancedTtl,
        };

        return {
            source: 'Custom Duration',
            label: `Duration: ${advancedTtl}`,
            payload,
        };
    }

    if (effectiveTtl.value !== '') {
        const payload: Record<string, string> = {
            ttl: effectiveTtl.value,
        };

        return {
            source: 'Preset',
            label: `Duration: ${effectiveTtl.value}`,
            payload,
        };
    }

    return {
        source: 'No Expiration',
        label: 'No Expiration',
        payload: {},
    };
});

const configuredSettlementRails = computed<CockpitSettlementRailCapability[]>(
    () => props.settlementRailCapabilities?.rails ?? [],
);

const payoutProviderLabel = computed<string>(
    () => props.settlementRailCapabilities?.provider.label ?? 'Unavailable',
);

const settlementRailOptions = computed(() => [
    {
        code: '',
        label: 'Automatic',
        enabled: true,
        helper: 'Recommended · chosen for each payout amount.',
    },
    ...configuredSettlementRails.value.map((capability) => ({
        code: capability.code,
        label: capability.label,
        enabled: capability.enabled,
        helper: settlementRailHelper(capability),
    })),
]);

const automaticSettlementRailCapability =
    computed<CockpitSettlementRailCapability | null>(() => {
        const amountMinor = Math.round(Number(amount.value) * 100);
        const threshold =
            props.settlementRailCapabilities?.automatic_policy
                .instapay_below_amount_minor ?? 5_000_000;
        const code =
            Number.isFinite(amountMinor) && amountMinor >= threshold
                ? 'PESONET'
                : 'INSTAPAY';

        return (
            configuredSettlementRails.value.find(
                (capability) => capability.code === code,
            ) ?? null
        );
    });

const selectedSettlementRailCapability =
    computed<CockpitSettlementRailCapability | null>(() => {
        if (settlementRail.value === '') {
            return automaticSettlementRailCapability.value;
        }

        return (
            configuredSettlementRails.value.find(
                (capability) => capability.code === settlementRail.value,
            ) ?? null
        );
    });

const settlementRailSelectionError = computed<string | null>(() => {
    if (
        isAccountFundingClaim.value ||
        props.settlementRailCapabilities === undefined
    ) {
        return null;
    }

    const capability = selectedSettlementRailCapability.value;

    if (capability === null) {
        return 'The selected transfer network is not available from the configured payout provider.';
    }

    if (!capability.enabled) {
        return (
            capability.availability_reason ??
            `${capability.label} is not available from the configured payout provider.`
        );
    }

    return null;
});

function formatSettlementRailMoney(
    value: number,
    currencyCode: string,
): string {
    // Mirrors formatAccountMoney's ₱-for-PHP convention so rail descriptions
    // render consistently with the rest of the Cockpit, but is parameterized
    // by the capability's own currency instead of the unrelated live pricing
    // currency, since a settlement rail's currency is authoritative here.
    const code = currencyCode.trim().toUpperCase() || 'PHP';
    const formattedValue = value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return code === 'PHP' ? `₱${formattedValue}` : `${code} ${formattedValue}`;
}

function settlementRailHelper(
    capability: CockpitSettlementRailCapability,
): string {
    if (!capability.enabled) {
        return capability.availability_reason ?? 'Unavailable';
    }

    const minimum = capability.minimum_amount_minor;
    const maximum = capability.maximum_amount_minor;

    if (minimum !== null && maximum !== null) {
        const feeLabel =
            capability.provider_fee_minor === null
                ? 'not published'
                : formatSettlementRailMoney(
                      capability.provider_fee_minor / 100,
                      capability.currency,
                  );

        return `${formatSettlementRailMoney(minimum / 100, capability.currency)}–${formatSettlementRailMoney(maximum / 100, capability.currency)} · provider cost ${feeLabel}`;
    }

    return 'Available for compatible receiving institutions.';
}

const settlementRailCycleCodes = ['', 'INSTAPAY', 'PESONET'] as const;

const settlementRailCycleOptions = computed(() => {
    return settlementRailCycleCodes
        .map((code) =>
            settlementRailOptions.value.find((option) => option.code === code),
        )
        .filter(
            (option): option is (typeof settlementRailOptions.value)[number] =>
                option !== undefined,
        );
});

const availableSettlementRailCycleOptions = computed(() => {
    return settlementRailCycleOptions.value.filter(
        (option) => option.code === '' || option.enabled,
    );
});

function cycleSettlementRail(): void {
    const options = availableSettlementRailCycleOptions.value;

    if (options.length === 0) {
        return;
    }

    const currentIndex = options.findIndex(
        (option) => option.code === settlementRail.value,
    );
    const nextIndex =
        currentIndex === -1 ? 0 : (currentIndex + 1) % options.length;

    settlementRail.value = options[nextIndex].code;
}

const currentSettlementRailLabel = computed<string>(() => {
    return (
        settlementRailOptions.value.find(
            (option) => option.code === settlementRail.value,
        )?.label ?? 'Automatic'
    );
});

const settlementRailCycleAccessibleLabel = computed<string>(() => {
    return `Transfer network: ${currentSettlementRailLabel.value}. Tap to cycle to the next available network.`;
});

const automaticSettlementRailDescription = computed<string>(() => {
    const capability = automaticSettlementRailCapability.value;

    if (capability === null) {
        return 'x-change chooses the eligible network for this amount.';
    }

    return `x-change chooses the eligible network for this amount — currently ${capability.label}.`;
});

const settlementRailCycleDescription = computed<string>(() => {
    if (settlementRail.value === '') {
        return automaticSettlementRailDescription.value;
    }

    const capability = selectedSettlementRailCapability.value;

    if (capability === null) {
        return 'The selected transfer network is not available from the configured payout provider.';
    }

    return settlementRailHelper(capability);
});

const illustrativeFee = computed<number>(() => {
    const providerFeeMinor =
        selectedSettlementRailCapability.value?.provider_fee_minor;

    return providerFeeMinor === null || providerFeeMinor === undefined
        ? 0
        : providerFeeMinor / 100;
});

const feeStrategyLabel = computed<string>(() => {
    if (feeStrategy.value === 'include') {
        return 'Include fee inside Pay Code amount';
    }

    if (feeStrategy.value === 'add') {
        return 'Add fee on top of amount';
    }

    return 'Issuer Absorbs Fee';
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
            note: 'Configured provider transfer cost is deducted from the visible amount.',
        };
    }

    if (feeStrategy.value === 'add') {
        return {
            recipientAmount: formatMoney(safeAmount),
            issuerCost: formatMoney(safeAmount + fee),
            note: 'Configured provider transfer cost is added to the issuer cost.',
        };
    }

    return {
        recipientAmount: formatMoney(safeAmount),
        issuerCost: formatMoney(safeAmount + fee),
        note: 'Configured provider transfer cost is absorbed by the issuer.',
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
            ?.helper ?? 'Choose the purpose of this Pay Code.'
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
        return 'No Conditions Selected';
    }

    return effectiveMandates.value.join(', ');
});

const selectedInputFields = computed<string[]>(() => {
    const fields = new Set(selectedInputFieldValues.value);

    if (onboardingEnabled.value) {
        fields.add('name');
        fields.add('email');
        fields.add('mobile');
    }

    if (onboardingOtpEnforced.value || payeeRequiresMobile.value) {
        fields.add('otp');
    }

    if (payeeRequiresMobile.value) {
        fields.add('mobile');
    }

    if (payeeRequiresEmail.value) {
        fields.add('email');
    }

    return voucherInputFieldPayloadOrder.filter((field) => fields.has(field));
});

const validationSummary = computed<Record<string, unknown>>(() => {
    const secret = effectiveValidationSecret.value;

    return {
        ...(secret === '' ? {} : { secret }),
        ...(effectiveMobileValidation.value && payeeRequiresMobile.value
            ? { mobile: normalizedPayee.value }
            : {}),
        ...(payeeRequiresVendor.value
            ? { payable: normalizedPayee.value }
            : {}),
        ...(requirePayableValidation.value && payeeType.value === 'anyone'
            ? { payable: 'required' }
            : {}),
        ...(requireCountryValidation.value ? { country: 'PH' } : {}),
        ...(requireLocationValidation.value
            ? { location: 'required', radius: '100' }
            : {}),
        ...(effectiveVerificationOtp.value ? { mobile_verification: {} } : {}),
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
        ...(effectiveVerificationOtp.value
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
        return 'No Recipient Rules Selected';
    }

    return [...new Set(labels)].join(', ');
});

const structuredValidationPreviewDisplay = computed<string>(() => {
    const labels = Object.keys(structuredValidationSummary.value).map(
        (key) => structuredValidationPreviewLabels[key] ?? key,
    );

    if (labels.length === 0) {
        return 'No Additional Checks Selected';
    }

    return labels.join(', ');
});

const verificationSummary = computed<string[]>(() => {
    return [
        verificationKyc.value ? 'kyc' : null,
        effectiveVerificationOtp.value ? 'otp' : null,
        verificationSelfie.value ? 'selfie' : null,
    ].filter((item): item is string => item !== null);
});

const feedbackSummary = computed<Record<string, unknown>>(() => {
    const mobile = normalizedFeedbackMobile.value;
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

const riderSplashContent = computed<string>(() => {
    return buildRiderSplashContent({
        headline: riderSplashHeadline.value,
        body: riderSplash.value,
        cta: riderSplashCtaText.value,
        format: riderSplashFormat.value,
    });
});

const riderSplashInstructionContent = computed<string>(() => {
    const headline = riderSplashHeadline.value.trim();
    const body = riderSplash.value.trim();
    const cta = riderSplashCtaText.value.trim();

    if (riderSplashFormat.value === 'html') {
        return riderSplashContent.value;
    }

    if (riderSplashFormat.value === 'markdown') {
        return [
            headline === '' ? null : `# ${headline}`,
            body === '' ? null : body,
            cta === '' ? null : `**${cta}**`,
        ]
            .filter((item): item is string => item !== null)
            .join('\n\n');
    }

    return [headline, body, cta].filter((item) => item !== '').join('\n\n');
});

const riderSplashPreviewDocument = computed<string>(() => {
    return buildSandboxedPreviewDocument(riderSplashContent.value);
});

const riderUrlPreview = computed<RiderStampPreview>(() => {
    return resolveRiderStampPreview({
        source: 'url',
        url: riderUrl.value,
        urlArtwork: riderUrlArtworkPreview.value,
        artworkSource: 'url',
        artworkTreatment: 'artwork',
        copySource: 'url',
        fit: 'contain',
        position: 'center',
        scrim: 18,
        theme: 'automatic',
        showLogo: false,
        showTagline: false,
        claimMarker: 'none',
    });
});

const riderUrlPreviewDocument = computed<string>(() => {
    return buildRiderStampPreviewDocument(riderUrlPreview.value, '', 'stamp');
});

const riderSplashThumbnailPreview = computed<RiderStampPreview>(() => {
    return resolveRiderStampPreview({
        source: 'splash',
        splashHeadline: riderSplashHeadline.value,
        splashBody: riderSplash.value,
        splashCta: riderSplashCtaText.value,
        artworkSource: 'splash',
        artworkTreatment: 'artwork',
        copySource: 'splash',
        fit: riderStampFit.value,
        position: riderStampPosition.value,
        scrim: riderStampScrim.value,
        theme: riderStampTheme.value,
        showLogo: false,
        showTagline: false,
        claimMarker: 'none',
    });
});

const riderArtworkSourceOptions = computed<RiderArtworkSourceOption[]>(() => {
    const urlArtwork = riderUrlArtworkPreview.value;
    const splashPreview = riderSplashThumbnailPreview.value;

    return [
        {
            value: 'x_change',
            label: 'x-change',
            imageUrl: null,
            title: 'x-change',
            description: 'Money should adapt to people.',
            resolving: false,
        },
        {
            value: 'url',
            label: 'Rider Link',
            imageUrl: urlArtwork?.image_url ?? null,
            title: urlArtwork?.title || riderUrl.value.trim() || 'Rider Link',
            description:
                urlArtwork?.description ||
                (riderUrl.value.trim() === ''
                    ? 'Add a Rider Link to preview it.'
                    : 'Link artwork preview'),
            resolving: riderUrlArtworkResolving.value,
        },
        {
            value: 'splash',
            label: 'Rider Splash',
            imageUrl: firstRiderArtworkImageUrl(riderSplashContent.value),
            title: splashPreview.title || 'Rider Splash',
            description:
                splashPreview.description ||
                (riderSplash.value.trim() === ''
                    ? 'Add Rider Splash content to preview it.'
                    : 'Splash artwork preview'),
            resolving: false,
        },
        {
            value: 'none',
            label: 'None',
            imageUrl: null,
            title: 'No Artwork',
            description: 'Text-only Stamp',
            resolving: false,
        },
    ];
});

const riderStampPreview = computed<RiderStampPreview>(() => {
    return resolveRiderStampPreview({
        source:
            riderStampArtworkSource.value === 'url' ||
            riderStampArtworkSource.value === 'splash'
                ? riderStampArtworkSource.value
                : riderStampCopySource.value,
        message: purpose.value,
        url: riderUrl.value,
        splashHeadline: riderSplashHeadline.value,
        splashBody: riderSplash.value,
        splashCta: riderSplashCtaText.value,
        urlArtwork: riderUrlArtworkPreview.value,
        title: riderStampTitle.value,
        description: riderStampDescription.value,
        fit: riderStampFit.value,
        position: riderStampPosition.value,
        scrim: riderStampScrim.value,
        theme: riderStampTheme.value,
        artworkSource: riderStampArtworkSource.value,
        artworkTreatment: riderStampArtworkTreatment.value,
        copySource: riderStampCopySource.value,
        showLogo: riderStampShowLogo.value,
        showTagline: riderStampShowTagline.value,
        claimMarker: riderStampClaimMarker.value,
        claimMarkerPosition: riderStampClaimMarkerPosition.value,
        experienceTheme: currentTheme.value,
        designId: riderStampDesignId.value,
        designVersion: riderStampDesignVersion.value,
    });
});

const riderCanvasArtworkDocument = computed<string>(() => {
    return buildRiderStampPreviewDocument(
        riderStampPreview.value,
        riderSplashContent.value,
        'canvas',
    );
});

const hasRiderStampCustomization = computed<boolean>(() => {
    return (
        riderStampArtworkSource.value !== 'x_change' ||
        riderStampArtworkTreatment.value !== 'automatic' ||
        riderStampCopySource.value !== 'automatic' ||
        !riderStampShowLogo.value ||
        !riderStampShowTagline.value ||
        riderStampClaimMarker.value !== 'qr' ||
        riderStampClaimMarkerPosition.value !== 'bottom_right' ||
        riderStampTitle.value.trim() !== '' ||
        riderStampDescription.value.trim() !== '' ||
        riderStampFit.value !== 'cover' ||
        riderStampPosition.value !== 'center' ||
        riderStampScrim.value !== '18' ||
        riderStampTheme.value !== 'automatic'
    );
});

const usesRiderArtwork = computed<boolean>(
    () =>
        riderStampArtworkTreatment.value !== 'text' &&
        (riderStampArtworkSource.value === 'url' ||
            riderStampArtworkSource.value === 'splash'),
);

const riderMessageDisclosureSummary = computed<string>(() => {
    const message = purpose.value.trim();

    return message === ''
        ? 'Add an optional message for the recipient.'
        : message.slice(0, 96);
});

const riderUrlDisclosureSummary = computed<string>(() => {
    const value = riderUrl.value.trim();

    if (value === '') {
        return 'Add an optional destination after the claim.';
    }

    try {
        return new URL(value).hostname;
    } catch {
        return value.slice(0, 96);
    }
});

const riderSplashDisclosureSummary = computed<string>(() => {
    const headline = riderSplashHeadline.value.trim();
    const body = riderSplash.value.trim();

    return (
        headline ||
        body.slice(0, 96) ||
        'Design an optional introduction before the claim.'
    );
});

const riderStampDisclosureSummary = computed<string>(() => {
    const artwork =
        riderStampArtworkSource.value === 'x_change'
            ? 'x-change'
            : riderStampArtworkSource.value === 'none'
              ? 'No artwork'
              : `Rider ${riderStampArtworkSource.value}`;

    return `${artwork} · ${riderStampCopySource.value} copy`;
});

const riderSummary = computed<Record<string, unknown>>(() => {
    const message = purpose.value.trim();
    const url = riderUrl.value.trim();
    const redirectTimeout = Number(riderRedirectTimeout.value);
    const splash = riderSplashInstructionContent.value.trim();
    const timeout = Number(riderSplashTimeout.value);
    const htmlProfile = riderSplashMetaProfile.value.trim();
    const stampTitle = riderStampTitle.value.trim();
    const stampDescription = riderStampDescription.value.trim();
    const scrim = Math.min(
        100,
        Math.max(0, Math.round(Number(riderStampScrim.value) || 0)),
    );

    return {
        message: message === '' ? null : message,
        message_format: message === '' ? null : riderMessageFormat.value,
        url: url === '' ? null : url,
        redirect_timeout:
            Number.isFinite(redirectTimeout) && redirectTimeout >= 0
                ? redirectTimeout
                : null,
        splash: splash === '' ? null : splash,
        splash_format: splash === '' ? null : riderSplashFormat.value,
        splash_timeout:
            Number.isFinite(timeout) && timeout > 0 ? timeout : null,
        splash_meta:
            riderSplashFormat.value === 'html' &&
            (riderSplashMetaSanitized.value || htmlProfile !== '')
                ? {
                      sanitized: riderSplashMetaSanitized.value,
                      ...(htmlProfile === ''
                          ? {}
                          : { html_profile: htmlProfile }),
                  }
                : null,
        og_source:
            riderStampArtworkSource.value === 'x_change'
                ? null
                : riderStampArtworkSource.value,
        stamp: {
            source:
                riderStampArtworkSource.value === 'x_change'
                    ? 'automatic'
                    : riderStampArtworkSource.value,
            title: stampTitle === '' ? null : stampTitle,
            description: stampDescription === '' ? null : stampDescription,
            fit: riderStampFit.value,
            position: riderStampPosition.value,
            scrim,
            theme: riderStampTheme.value,
            artwork_source: riderStampArtworkSource.value,
            artwork_treatment: riderStampArtworkTreatment.value,
            copy_source: riderStampCopySource.value,
            show_logo: riderStampShowLogo.value,
            show_tagline: riderStampShowTagline.value,
            claim_marker: riderStampClaimMarker.value,
            claim_marker_position: riderStampClaimMarkerPosition.value,
            design_id: riderStampPreview.value.design.id,
            design_version: riderStampPreview.value.design.version,
            version: 3,
        },
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

const scheduledPortionsUnavailableReason = computed<string | null>(() => {
    if (normalizedPayCodeAmount() <= 0) {
        return 'Enter a Pay Code amount before scheduling portions.';
    }

    if (maxValidClaimCount.value < 2) {
        return `Scheduled portions require enough value for at least two ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)} claims.`;
    }

    return null;
});

const addSliceDisabledReason = computed<string | null>(() => {
    const nextCount = Math.max(2, namedClaimSlices.value.length + 1);
    const nextAmount = normalizedPayCodeAmount() / nextCount;

    if (nextAmount + 0.0001 < minimumWithdrawalFloor.value) {
        return `Adding another portion would create ${currency.value || 'PHP'} ${formatSliceAmount(nextAmount)} claims, below the ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)} effective minimum.`;
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
            : 'Enter a Pay Code amount before configuring portions.';
    }

    if (sliceMode.value === 'fixed') {
        const count = fixedSliceCount();
        const computedAmount = count > 0 ? normalizedAmount / count : 0;

        if (computedAmount + 0.0001 < minimumWithdrawalFloor.value) {
            return `${count} equal portions would create ${currency.value || 'PHP'} ${formatSliceAmount(computedAmount)} claims, below the ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)} effective minimum.`;
        }

        return null;
    }

    if (sliceMode.value === 'open') {
        const minimum = Number(minWithdrawal.value);

        if (
            Number.isFinite(minimum) &&
            minimum + 0.0001 < minimumWithdrawalFloor.value
        ) {
            return `Minimum Claim Amount must be at least ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)}.`;
        }

        return null;
    }

    if (sliceMode.value !== 'named') {
        return null;
    }

    if (normalizedNamedClaimSlices.value.length === 0) {
        return 'Add at least one scheduled portion.';
    }

    if (
        normalizedNamedClaimSlices.value.some(
            (slice) =>
                slice.claim_on !== null &&
                slice.claim_by !== null &&
                slice.claim_by < slice.claim_on,
        )
    ) {
        return 'A scheduled portion cannot expire before it becomes available.';
    }

    if (normalizedNamedClaimSlices.value.some((slice) => slice.amount <= 0)) {
        return 'Each scheduled portion must have an amount greater than zero.';
    }

    if (
        normalizedNamedClaimSlices.value.some(
            (slice) => slice.amount + 0.0001 < minimumWithdrawalFloor.value,
        )
    ) {
        return `Each scheduled portion must be at least ${currency.value || 'PHP'} ${formatSliceAmount(minimumWithdrawalFloor.value)}.`;
    }

    if (Math.abs(namedClaimSliceRemaining.value) >= 0.01) {
        return 'Scheduled portion amounts must equal the Pay Code amount.';
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

const executableSlicePlan = computed<Record<string, unknown> | null>(() => {
    if (reusableBalance.value || sliceMode.value === 'whole') {
        return null;
    }

    const totalMinor = Math.round(normalizedPayCodeAmount() * 100);
    const planBase = {
        schema: 'voucher.slice-plan.v1',
        total_minor: totalMinor,
        currency: currency.value.trim().toUpperCase() || 'PHP',
    };

    if (sliceMode.value === 'fixed') {
        const count = Math.max(
            2,
            Math.round(Number(sliceSummary.value.slices) || 2),
        );
        const baseMinor = Math.floor(totalMinor / count);
        const remainder = totalMinor % count;

        return {
            ...planBase,
            mode: 'equal',
            selection: 'next_only',
            slices: Array.from({ length: count }, (_, index) => ({
                id: `slice_${index + 1}`,
                label: `Slice ${index + 1}`,
                amount_minor: baseMinor + (index < remainder ? 1 : 0),
                sequence: index + 1,
                claim_on: null,
                claim_by: null,
            })),
            max_slices: null,
            min_amount_minor: null,
        };
    }

    if (sliceMode.value === 'named') {
        return {
            ...planBase,
            mode: 'scheduled',
            selection: 'one_or_many',
            slices: normalizedNamedClaimSlices.value.map((slice, index) => ({
                id: slice.id,
                label: slice.description || `Slice ${index + 1}`,
                amount_minor: Math.round(Number(slice.amount) * 100),
                sequence: index + 1,
                claim_on: slice.claim_on,
                claim_by: slice.claim_by,
            })),
            max_slices: null,
            min_amount_minor: null,
        };
    }

    return {
        ...planBase,
        mode: 'flexible',
        selection: 'flexible_amount',
        slices: [],
        max_slices: Math.max(
            1,
            Math.round(Number(sliceSummary.value.max_slices) || 1),
        ),
        min_amount_minor: Math.round(
            (Number(sliceSummary.value.min_withdrawal) ||
                minimumWithdrawalFloor.value) * 100,
        ),
    };
});

const contractSummaryItems = computed<Array<{ label: string; value: string }>>(
    () => {
        return [
            { label: 'Template', value: selectedTemplateName.value },
            {
                label: 'Value',
                value: `${currency.value.trim() || 'PHP'} ${amount.value || '0'} × ${count.value || '1'}`,
            },
            {
                label: 'Recipient',
                value:
                    payeeType.value === 'anyone'
                        ? 'anyone'
                        : `${payeeType.value}: ${payeeDisplayReference.value}`,
            },
            {
                label: 'Expiration',
                value: effectiveTtl.value === '' ? 'none' : effectiveTtl.value,
            },
            {
                label: 'Claim Requirements',
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
                label: 'Status Updates',
                value: Object.values(feedbackSummary.value).some(
                    (value) => value !== null,
                )
                    ? 'configured'
                    : 'none',
            },
            {
                label: 'Claim Schedule',
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

const previewStale = computed<boolean>(() => {
    return (
        previewStatus.value === 'ready' &&
        previewDraftSnapshot.value !== null &&
        previewDraftSnapshot.value !== sanitizedInstructionPayloadJson.value
    );
});

watch(canvasView, (view): void => {
    if (
        view === 'claim' &&
        previewStatus.value === 'idle' &&
        !previewProcessing.value
    ) {
        void generateClaimPreview(false);
    }
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
    if (reusableBalance.value || !includeExecutionInstruction.value) {
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

const livePricingPayload = computed<Record<string, unknown>>(() => {
    return buildPayloadShape(true, amountCalculatorPreview.value);
});
const canEstimateLivePricing = computed<boolean>(() => {
    const normalizedAmount =
        amountCalculatorPreview.value ?? Number(amount.value);
    const normalizedCount = Number(count.value);

    return (
        Number.isFinite(normalizedAmount) &&
        normalizedAmount > 0 &&
        Number.isFinite(normalizedCount) &&
        normalizedCount > 0
    );
});
const {
    estimate: livePricingEstimate,
    estimating: livePricingEstimating,
    estimateError: livePricingEstimateError,
} = usePayCodeCostEstimate(livePricingPayload, canEstimateLivePricing);
const liveAccountDebit = computed<number | null>(() =>
    optionalMoney(livePricingEstimate.value?.account_debit),
);
const liveAccountDebitFormatted = computed<string | null>(() =>
    liveAccountDebit.value === null
        ? null
        : formatAccountMoney(liveAccountDebit.value),
);
const amountCalculatorEstimatedCost = computed<string | null>(() => {
    if (
        amountCalculatorEstimatePending.value ||
        livePricingEstimateError.value !== null
    ) {
        return null;
    }

    return liveAccountDebitFormatted.value;
});
const livePricingCurrency = computed<string>(
    () => livePricingEstimate.value?.currency?.trim() || currency.value,
);
const liveAccountDebitPending = computed<boolean>(
    () =>
        canEstimateLivePricing.value &&
        liveAccountDebit.value === null &&
        livePricingEstimateError.value === null,
);
const liveAccountDebitAffordability = computed<
    'unknown' | 'affordable' | 'insufficient-client-funds'
>(() => {
    if (
        liveAccountDebit.value === null ||
        typeof props.clientFundsMinor !== 'number' ||
        !Number.isFinite(props.clientFundsMinor)
    ) {
        return 'unknown';
    }

    const accountDebitMinor = Math.round(liveAccountDebit.value * 100);

    return accountDebitMinor > Math.round(props.clientFundsMinor)
        ? 'insufficient-client-funds'
        : 'affordable';
});
const liveAccountDebitExceedsClientFunds = computed<boolean>(
    () => liveAccountDebitAffordability.value === 'insufficient-client-funds',
);

function previewAmountInCalculator(value: number | null): void {
    amountCalculatorPreview.value = value;

    if (value === null) {
        amountCalculatorEstimatePending.value = false;

        return;
    }

    amountCalculatorEstimatePending.value =
        value !== Number(amount.value) || liveAccountDebit.value === null;
}

watch([livePricingEstimate, livePricingEstimateError], () => {
    if (amountCalculatorPreview.value !== null) {
        amountCalculatorEstimatePending.value = false;
    }
});

async function submit(): Promise<void> {
    if (!canSubmit.value || processing.value || routeUrl.value === null) {
        return;
    }

    processing.value = true;
    lastStatus.value = 'submitting';
    lastMessage.value =
        'Submitting through the idempotency-protected issuance handoff.';
    submissionErrors.value = [];
    submissionErrorHeading.value = 'Fix these fields before issuing';

    const idempotencyKey = generateIdempotencyKey();
    const payload = buildIssuancePayload();

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
            const normalizedErrors = normalizeSubmissionErrors(body);
            const issuanceIsBusy =
                stringValue(body.code) === 'PAY_CODE_ISSUANCE_BUSY';

            lastStatus.value = 'failed';
            lastMessage.value = issuanceIsBusy
                ? 'No Pay Code was issued or charged. Please try again.'
                : normalizedErrors.length > 0
                  ? 'Your Pay Code needs a few corrections before it can be issued.'
                  : (stringValue(body.message) ??
                    'The Pay Code could not be issued.');
            submissionErrorHeading.value = issuanceIsBusy
                ? 'Issuance is temporarily busy'
                : 'Fix these fields before issuing';
            submissionErrors.value =
                normalizedErrors.length > 0
                    ? normalizedErrors
                    : [
                          {
                              field: 'Submission',
                              message:
                                  stringValue(body.message) ??
                                  'The Pay Code could not be issued.',
                          },
                      ];
            emit('submitError', body);

            return;
        }

        lastStatus.value = body.status === 'replayed' ? 'replayed' : 'issued';
        lastMessage.value =
            body.status === 'replayed'
                ? 'Idempotent replay returned the existing operator-safe result.'
                : 'Pay Code issued through the existing x-change issuance handoff.';
        submissionErrors.value = [];
        submissionErrorHeading.value = 'Fix these fields before issuing';
        lastResponse.value = body;
        issuedPayCodeDialogOpen.value = resultCode.value !== null;
        emit('submitSuccess', body);
    } catch (error) {
        const body = {
            message:
                error instanceof Error
                    ? error.message
                    : 'The Pay Code could not be issued.',
        };

        lastStatus.value = 'failed';
        lastMessage.value = body.message;
        submissionErrorHeading.value = 'Connection issue';
        submissionErrors.value = [
            {
                field: 'Network',
                message: body.message,
            },
        ];
        emit('submitError', body);
    } finally {
        processing.value = false;
    }
}

async function generateClaimPreview(refreshPreview = false): Promise<void> {
    if (!canGenerateClaimPreview.value || claimPreviewRouteUrl.value === null) {
        return;
    }

    previewProcessing.value = true;
    previewStatus.value = 'idle';
    previewMessage.value = refreshPreview
        ? 'Refreshing the claim walkthrough preview.'
        : 'Rendering the claim walkthrough preview.';

    try {
        const response = await fetch(claimPreviewRouteUrl.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify({
                ...buildPayload(),
                refresh_preview: refreshPreview,
                preview_profile: 'issuer',
            }),
        });
        const body = await safeJson(response);

        if (!response.ok) {
            previewStatus.value = 'failed';
            previewMessage.value =
                stringValue(body.message) ??
                'The claim walkthrough preview could not be generated.';
            previewResult.value = null;
            previewDraftSnapshot.value = null;

            return;
        }

        previewStatus.value = 'ready';
        previewMessage.value =
            body.cache_hit === true
                ? 'Cached claim walkthrough preview is ready.'
                : 'Claim walkthrough preview is ready.';
        previewResult.value =
            body as unknown as CockpitClaimExperiencePreviewManifest;
        previewDraftSnapshot.value = sanitizedInstructionPayloadJson.value;
    } catch (error) {
        previewStatus.value = 'failed';
        previewMessage.value =
            error instanceof Error
                ? error.message
                : 'The claim walkthrough preview could not be generated.';
        previewResult.value = null;
        previewDraftSnapshot.value = null;
    } finally {
        previewProcessing.value = false;
    }
}

function normalizeSubmissionErrors(
    body: Record<string, unknown>,
): Array<{ field: string; message: string }> {
    const errors = dataGet(body, ['errors']);

    if (typeof errors !== 'object' || errors === null) {
        const message = stringValue(body.message);

        return message === null
            ? []
            : [
                  {
                      field: 'Submission',
                      message,
                  },
              ];
    }

    return Object.entries(errors as Record<string, unknown>).flatMap(
        ([field, messages]) => {
            if (Array.isArray(messages)) {
                return messages
                    .map((message) => stringValue(message))
                    .filter((message): message is string => message !== null)
                    .map((message) => ({
                        field: humanizeFieldPath(field),
                        message,
                    }));
            }

            const message = stringValue(messages);

            return message === null
                ? []
                : [
                      {
                          field: humanizeFieldPath(field),
                          message,
                      },
                  ];
        },
    );
}

function humanizeFieldPath(field: string): string {
    return field
        .replace(/\.\d+\./g, '.')
        .replace(/[_\.]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
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

function buildIssuancePayload(): Record<string, unknown> {
    const payload = buildPayload();
    const estimate = livePricingEstimate.value;

    if (
        typeof estimate?.commercial_offering_reference === 'string' &&
        typeof estimate.commercial_offering_version === 'number' &&
        typeof estimate.commercial_offering_snapshot_hash === 'string'
    ) {
        payload._pricing = {
            offering_reference: estimate.commercial_offering_reference,
            offering_version: estimate.commercial_offering_version,
            offering_snapshot_hash: estimate.commercial_offering_snapshot_hash,
            ...(typeof estimate.commercial_quote_reference === 'string'
                ? { quote_reference: estimate.commercial_quote_reference }
                : {}),
        };
    }

    return payload;
}

function buildPayloadShape(
    redactSensitive: boolean,
    amountOverride: number | null = null,
): Record<string, unknown> {
    const normalizedAmount =
        amountOverride === null ? Number(amount.value) : amountOverride;
    const normalizedCount = Number(count.value);
    const campaign = campaignMetadata();
    const cash: Record<string, unknown> = {
        amount: Number.isFinite(normalizedAmount)
            ? normalizedAmount
            : amount.value,
        currency: currency.value.trim() || 'PHP',
        validation: validationSummary.value,
    };

    if (!reusableBalance.value && settlementRail.value.trim() !== '') {
        cash.settlement_rail = settlementRail.value.trim();
    }

    cash.fee_strategy = feeStrategy.value;

    if (effectiveCashType.value !== '') {
        cash.type = effectiveCashType.value;
    }

    if (effectiveMandates.value.length > 0) {
        cash.mandates = effectiveMandates.value;
    }

    const validation = { ...validationSummary.value };

    if (redactSensitive && 'secret' in validation) {
        validation.secret = '[redacted secret]';
    }

    cash.validation = validation;

    const payload: Record<string, unknown> = {
        cash,
        ...(executableSlicePlan.value === null
            ? {}
            : { slice_plan: executableSlicePlan.value }),
        ...(reusableBalance.value
            ? {
                  stored_value: {
                      enabled: true,
                      replenishable: storedValueReplenishable.value,
                      maximum_balance:
                          normalizedStoredValueMaximumBalance.value,
                      otp_required_above: normalizedStoredValueOtpAbove.value,
                  },
              }
            : {}),
        ...(onboardingEnabled.value ? { onboarding: true } : {}),
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
        claim: {
            outcomes: [
                claimOutcome.value === 'account_funding'
                    ? {
                          key: 'account_funding',
                          pricing_profile: 'account-funding-v1',
                      }
                    : {
                          key: 'provider_disbursement',
                      },
            ],
            selection: 'server',
            consumption: 'one_of',
            default_outcome: claimOutcome.value,
            onboarding: {
                mode: 'if_required',
            },
            claimant: {
                mode: 'unbound',
            },
            profile: 'voucher.claim.v1',
        },
        metadata: {
            ...(campaign === null ? {} : { campaign }),
            custom: {
                cockpit: {
                    template_key: selectedTemplate.value,
                    ...(activeSavedTemplate.value === null
                        ? {}
                        : {
                              saved_template: {
                                  reference:
                                      activeSavedTemplate.value.reference,
                                  name: activeSavedTemplate.value.name,
                              },
                          }),
                    source: 'cockpit.quick-generate',
                    builder: 'guided-voucher-instruction-builder',
                    payee: {
                        kind: payeePolicy.value.kind,
                        explicit_secret: payeePolicy.value.explicitSecret,
                    },
                    contract_summary: contractSummaryItems.value,
                    ...(!reusableBalance.value
                        ? { slice_plan: canonicalSlicePlan.value }
                        : {}),
                    ...(isAccountFundingClaim.value
                        ? {
                              recipient_reference:
                                  payeePolicy.value.kind === 'open'
                                      ? recipientReference.value.trim()
                                      : (payeePolicy.value.normalizedValue ??
                                        ''),
                          }
                        : {}),
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
    return normalizePhilippineFeedbackMobile(value);
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
    if (!enabled) {
        setFeedbackValue(channel, '');
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

function cloneNamedClaimSlices(source: NamedClaimSlice[]): NamedClaimSlice[] {
    return source.map((slice) => ({ ...slice }));
}

function defaultScheduledClaimSlices(): NamedClaimSlice[] {
    return equalFixedNamedClaimSlices(2);
}

function rememberScheduledClaimSlices(): void {
    if (sliceMode.value !== 'named' || namedClaimSlices.value.length === 0) {
        return;
    }

    rememberedScheduledClaimSlices.value = cloneNamedClaimSlices(
        namedClaimSlices.value,
    );
}

function configureScheduledClaimSlices(): void {
    const remembered = rememberedScheduledClaimSlices.value;
    const current =
        sliceMode.value === 'named' && namedClaimSlices.value.length > 0
            ? namedClaimSlices.value
            : null;

    namedClaimSlices.value =
        remembered !== null && remembered.length > 0
            ? cloneNamedClaimSlices(remembered)
            : current !== null
              ? cloneNamedClaimSlices(current)
              : defaultScheduledClaimSlices();
    sliceMode.value = 'named';
    slices.value = String(namedClaimSlices.value.length);
    maxSlices.value = String(namedClaimSlices.value.length);
    minWithdrawal.value = formatSliceAmount(
        Math.min(
            ...namedClaimSlices.value.map((slice) => Number(slice.amount)),
        ),
    );
}

function nextNamedClaimSliceId(): string {
    const usedIds = new Set(namedClaimSlices.value.map((slice) => slice.id));
    let index = 0;

    while (usedIds.has(namedClaimSliceId(index))) {
        index += 1;
    }

    return namedClaimSliceId(index);
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
    maxSlices.value = String(Math.max(2, Number(maxSlices.value) || 2));
    minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);
    namedClaimSlices.value = defaultOpenNamedClaimSlices();
}

function reconcileOpenAmountSliceConstraints(): void {
    const minimum = Number(minWithdrawal.value);

    if (
        !Number.isFinite(minimum) ||
        minimum + 0.0001 < minimumWithdrawalFloor.value
    ) {
        minWithdrawal.value = formatSliceAmount(minimumWithdrawalFloor.value);
    }

    maxSlices.value = String(Math.max(1, Number(maxSlices.value) || 1));
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
        maxSlices.value = String(Math.max(1, Number(defaults.maxSlices) || 1));
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

    const nextIndex = namedClaimSlices.value.length;
    const nextSlices = [
        ...namedClaimSlices.value,
        {
            id: nextNamedClaimSliceId(),
            amount: '0.00',
            description: defaultFixedSliceDescription(nextIndex),
            tag: '',
            claim_on: '',
            claim_by: '',
        },
    ];

    namedClaimSlices.value =
        redistributeExistingNamedClaimSliceAmounts(nextSlices);
    sliceMode.value = 'named';
    slices.value = String(namedClaimSlices.value.length);
    maxSlices.value = String(namedClaimSlices.value.length);
    rememberScheduledClaimSlices();
}

function removeNamedClaimSlice(index: number): void {
    if (namedClaimSlices.value.length <= 1) {
        return;
    }

    const wasScheduled = sliceMode.value === 'named';
    const wasEqualFixed = namedClaimSlicesAreEqualFixed();

    namedClaimSlices.value = namedClaimSlices.value
        .filter((_, sliceIndex) => sliceIndex !== index)
        .map((slice, sliceIndex) => ({
            ...slice,
            id: slice.id || namedClaimSliceId(sliceIndex),
        }));

    if (wasScheduled) {
        namedClaimSlices.value = redistributeExistingNamedClaimSliceAmounts(
            namedClaimSlices.value,
        );
        sliceMode.value = 'named';
        slices.value = String(namedClaimSlices.value.length);
        maxSlices.value = String(namedClaimSlices.value.length);
        rememberScheduledClaimSlices();

        return;
    }

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
    const wasScheduled = sliceMode.value === 'named';

    namedClaimSlices.value = namedClaimSlices.value.map((slice, sliceIndex) =>
        sliceIndex === index ? { ...slice, [key]: value } : slice,
    );

    if (wasScheduled) {
        sliceMode.value = 'named';
        rememberScheduledClaimSlices();

        return;
    }

    reconcileSliceModeFromNamedClaimSlices();
}

function setSliceMode(mode: CockpitValueUseMode): void {
    if (isAccountFundingClaim.value && mode !== 'whole') {
        return;
    }

    if (sliceMode.value === 'named' && mode !== 'named') {
        rememberScheduledClaimSlices();
    }

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
        configureScheduledClaimSlices();
    }
}

function setFixedSliceCount(count: number): void {
    redistributeFixedNamedClaimSlices(count);
}

function setMaximumClaims(count: number): void {
    maxSlices.value = String(Math.max(1, count));
}

function setMinimumClaimAmount(value: number): void {
    minWithdrawal.value = formatSliceAmount(value);
    reconcileOpenAmountSliceConstraints();
}

function setReusableBalance(enabled: boolean): void {
    if (enabled && !storedValueAvailable.value) {
        return;
    }

    reusableBalance.value = enabled;

    if (
        enabled &&
        Number(storedValueMaximumBalance.value || 0) < normalizedPayCodeAmount()
    ) {
        storedValueMaximumBalance.value = formatSliceAmount(
            normalizedPayCodeAmount(),
        );
    }
}

function setStoredValueReplenishable(enabled: boolean): void {
    storedValueReplenishable.value = enabled;

    if (!enabled) {
        storedValueMaximumBalance.value = formatSliceAmount(
            normalizedPayCodeAmount(),
        );
    }
}

function setStoredValueMaximumBalance(value: number): void {
    storedValueMaximumBalance.value = formatSliceAmount(
        Math.max(normalizedPayCodeAmount(), value),
    );
}

function setStoredValueOtpAbove(value: number | null): void {
    storedValueOtpAbove.value =
        value === null ? '' : formatSliceAmount(Math.max(0, value));
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

function formatAccountMoney(value: number): string {
    const currencyCode =
        livePricingCurrency.value.trim().toUpperCase() || 'PHP';
    const formattedValue = value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return currencyCode === 'PHP'
        ? `₱${formattedValue}`
        : `${currencyCode} ${formattedValue}`;
}

function optionalMoney(value: unknown): number | null {
    if (typeof value !== 'number' && typeof value !== 'string') {
        return null;
    }

    const normalized = Number(value);

    return Number.isFinite(normalized) ? normalized : null;
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

function instructionString(
    source: unknown,
    path: string[],
    fallback = '',
): string {
    return stringValue(dataGet(source, path)) ?? fallback;
}

function instructionContentFormat(
    source: unknown,
    path: string[],
    fallback: RiderContentFormat,
): RiderContentFormat {
    const value = instructionString(source, path);

    return value === 'plain' || value === 'markdown' || value === 'html'
        ? value
        : fallback;
}

function instructionStampFit(source: unknown, path: string[]): RiderStampFit {
    return instructionString(source, path) === 'contain' ? 'contain' : 'cover';
}

function instructionStampPosition(
    source: unknown,
    path: string[],
): RiderStampPosition {
    const value = instructionString(source, path);

    return value === 'top' ||
        value === 'bottom' ||
        value === 'left' ||
        value === 'right'
        ? value
        : 'center';
}

function instructionStampTheme(
    source: unknown,
    path: string[],
): RiderStampTheme {
    const value = instructionString(source, path);

    return value === 'light' || value === 'dark' ? value : 'automatic';
}

function instructionStampArtworkSource(
    source: unknown,
    path: string[],
    legacySource: string,
): RiderStampArtworkSource {
    const value = instructionString(source, path);

    if (
        value === 'x_change' ||
        value === 'url' ||
        value === 'splash' ||
        value === 'none'
    ) {
        return value;
    }

    return legacySource === 'url' || legacySource === 'splash'
        ? legacySource
        : 'x_change';
}

function instructionStampArtworkTreatment(
    source: unknown,
    path: string[],
): RiderStampArtworkTreatment {
    const value = instructionString(source, path);

    return value === 'artwork' || value === 'text' ? value : 'automatic';
}

function instructionStampCopySource(
    source: unknown,
    path: string[],
    legacySource: string,
): RiderStampCopySource {
    const value = instructionString(source, path);

    if (
        value === 'automatic' ||
        value === 'message' ||
        value === 'url' ||
        value === 'splash' ||
        value === 'custom' ||
        value === 'none'
    ) {
        return value;
    }

    return legacySource === 'message' ||
        legacySource === 'url' ||
        legacySource === 'splash'
        ? legacySource
        : 'automatic';
}

function instructionStampClaimMarker(
    source: unknown,
    path: string[],
): RiderStampClaimMarker {
    const value = instructionString(source, path);

    return value === 'none' ||
        value === 'code' ||
        value === 'both' ||
        value === 'qr'
        ? value
        : 'qr';
}

function instructionStampClaimMarkerPosition(
    source: unknown,
    path: string[],
): RiderStampClaimMarkerPosition {
    const value = instructionString(source, path);

    return value === 'top_left' ||
        value === 'top_right' ||
        value === 'bottom_left'
        ? value
        : 'bottom_right';
}

function instructionStringArray(source: unknown, path: string[]): string[] {
    const value = dataGet(source, path);

    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => stringValue(item))
        .filter((item): item is string => item !== null);
}

function instructionRecord(
    source: unknown,
    path: string[],
): Record<string, unknown> {
    const value = dataGet(source, path);

    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? (value as Record<string, unknown>)
        : {};
}
</script>

<template>
    <form
        class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-sky-50 p-4 shadow-sm dark:border-emerald-900/70 dark:from-emerald-950/40 dark:via-slate-950 dark:to-sky-950/30"
        data-testid="cockpit-quick-generate-submit-panel"
        @submit.prevent="submit"
    >
        <div
            v-if="templatePickerOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 p-0 sm:items-center sm:p-6"
            data-testid="cockpit-quick-generate-template-picker"
            @click.self="templatePickerOpen = false"
        >
            <section
                class="max-h-[88vh] w-full overflow-y-auto rounded-t-3xl bg-white p-4 shadow-2xl sm:max-w-2xl sm:rounded-3xl sm:p-6 dark:bg-slate-950"
                role="dialog"
                aria-modal="true"
                aria-labelledby="quick-generate-template-title"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                        >
                            Recommended
                        </p>
                        <h3
                            id="quick-generate-template-title"
                            class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Choose a starting template
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900"
                        aria-label="Close template picker"
                        @click="templatePickerOpen = false"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="template in templates"
                        :key="template.key"
                        type="button"
                        :disabled="template.disabled"
                        class="rounded-2xl border border-slate-200 p-4 text-left transition hover:border-emerald-400 hover:bg-emerald-50/60 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-800 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/30"
                        data-testid="cockpit-quick-generate-template-option"
                        @click="applySystemTemplate(template.key)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p
                                class="font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ template.name }}
                            </p>
                            <Clock3
                                class="size-4 text-slate-400"
                                aria-hidden="true"
                            />
                        </div>
                        <p
                            class="mt-2 text-sm leading-5 text-slate-600 dark:text-slate-300"
                        >
                            {{ template.description }}
                        </p>
                    </button>
                </div>

                <div
                    class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:text-sky-300"
                            >
                                My Templates
                            </p>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Designs you saved for reuse.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                        >
                            {{ savedTemplates?.length ?? 0 }}
                        </span>
                    </div>

                    <div
                        v-if="savedTemplates?.length"
                        class="mt-3 grid gap-3 sm:grid-cols-2"
                    >
                        <button
                            v-for="template in savedTemplates"
                            :key="template.reference"
                            type="button"
                            class="rounded-2xl border border-slate-200 p-4 text-left transition hover:border-sky-400 hover:bg-sky-50/60 dark:border-slate-800 dark:hover:border-sky-700 dark:hover:bg-sky-950/30"
                            data-testid="cockpit-quick-generate-saved-template-option"
                            @click="applySavedTemplate(template)"
                        >
                            <p
                                class="font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ template.name }}
                            </p>
                            <p
                                v-if="template.description"
                                class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-300"
                            >
                                {{ template.description }}
                            </p>
                            <p
                                class="mt-3 text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400"
                            >
                                {{ template.base_template_key }}
                            </p>
                        </button>
                    </div>
                    <div
                        v-else
                        class="mt-3 rounded-2xl border border-dashed border-slate-300 px-4 py-5 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
                    >
                        Save a template to find it here next time.
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="saveTemplateOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 p-0 sm:items-center sm:p-6"
            data-testid="cockpit-quick-generate-save-template-dialog"
            @click.self="saveTemplateOpen = false"
        >
            <section
                class="w-full rounded-t-3xl bg-white p-4 shadow-2xl sm:max-w-lg sm:rounded-3xl sm:p-6 dark:bg-slate-950"
                role="dialog"
                aria-modal="true"
                aria-labelledby="quick-generate-save-template-title"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                        >
                            My Templates
                        </p>
                        <h3
                            id="quick-generate-save-template-title"
                            class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                activeSavedTemplate
                                    ? 'Save Template Changes'
                                    : 'Create A Template'
                            }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900"
                        aria-label="Close save template dialog"
                        @click="saveTemplateOpen = false"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>

                <div class="mt-5 grid gap-4">
                    <div
                        v-if="activeSavedTemplate"
                        class="grid grid-cols-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-900"
                        data-testid="cockpit-quick-generate-template-save-mode"
                    >
                        <button
                            type="button"
                            :class="[
                                'min-h-10 rounded-lg px-3 text-sm font-semibold transition',
                                saveTemplateMode === 'update'
                                    ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-800 dark:text-emerald-200'
                                    : 'text-slate-600 dark:text-slate-300',
                            ]"
                            data-testid="cockpit-quick-generate-template-update-mode"
                            @click="chooseSaveTemplateMode('update')"
                        >
                            Update “{{ activeSavedTemplate.name }}”
                        </button>
                        <button
                            type="button"
                            :class="[
                                'min-h-10 rounded-lg px-3 text-sm font-semibold transition',
                                saveTemplateMode === 'create'
                                    ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-800 dark:text-emerald-200'
                                    : 'text-slate-600 dark:text-slate-300',
                            ]"
                            data-testid="cockpit-quick-generate-template-create-mode"
                            @click="chooseSaveTemplateMode('create')"
                        >
                            Save As New
                        </button>
                    </div>
                    <label
                        class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                    >
                        Template Name
                        <input
                            v-model="saveTemplateName"
                            type="text"
                            maxlength="80"
                            placeholder="e.g. Weekly Allowance"
                            class="min-h-11 rounded-xl border border-slate-200 bg-white px-3 text-slate-950 outline-none ring-emerald-500 focus:ring-2 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            data-testid="cockpit-quick-generate-template-name"
                        />
                    </label>
                    <label
                        class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                    >
                        Description
                        <textarea
                            v-model="saveTemplateDescription"
                            rows="2"
                            maxlength="240"
                            placeholder="Optional note for future you"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-950 outline-none ring-emerald-500 focus:ring-2 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            data-testid="cockpit-quick-generate-template-description"
                        />
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-200"
                        >
                            <input
                                v-model="saveTemplateIncludeAmount"
                                type="checkbox"
                                class="size-4 rounded border-slate-300 text-emerald-600"
                                data-testid="cockpit-quick-generate-template-include-amount"
                            />
                            Include Amount
                        </label>
                        <label
                            class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-200"
                        >
                            <input
                                v-model="saveTemplateIncludePurpose"
                                type="checkbox"
                                class="size-4 rounded border-slate-300 text-emerald-600"
                                data-testid="cockpit-quick-generate-template-include-purpose"
                            />
                            Include Purpose
                        </label>
                    </div>
                    <p
                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Recipient details, contact destinations, secrets, and
                        one-time dates are never saved.
                    </p>
                    <p
                        v-if="saveTemplateMode === 'update'"
                        class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Updating affects future Pay Codes only. Already issued
                        Pay Codes will not change.
                    </p>
                    <p
                        v-if="templateSaveError"
                        class="text-sm font-medium text-rose-600 dark:text-rose-300"
                        data-testid="cockpit-quick-generate-template-save-error"
                    >
                        {{ templateSaveError }}
                    </p>
                    <button
                        type="button"
                        :disabled="templateSaving"
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60"
                        data-testid="cockpit-quick-generate-template-save-submit"
                        @click="saveTemplate"
                    >
                        <Save class="size-4" aria-hidden="true" />
                        {{
                            templateSaving
                                ? 'Saving…'
                                : saveTemplateMode === 'update'
                                  ? 'Update Template'
                                  : 'Create Template'
                        }}
                    </button>
                </div>
            </section>
        </div>

        <div
            class="grid min-w-0 gap-5 xl:grid-cols-[minmax(19rem,0.74fr)_minmax(28rem,1.26fr)]"
            data-testid="cockpit-quick-generate-essentials-canvas"
        >
            <div
                class="min-w-0 rounded-2xl border border-emerald-200 bg-white/80 p-4 dark:border-emerald-900/70 dark:bg-slate-950/70"
                data-testid="cockpit-quick-generate-order-card"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h4
                            class="text-lg font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Order
                        </h4>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 dark:disabled:bg-slate-800 dark:disabled:text-slate-500"
                        data-testid="cockpit-quick-generate-submit-button"
                        :disabled="!canSubmit || processing"
                    >
                        <LoaderCircle
                            v-if="processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                            data-testid="cockpit-quick-generate-issue-spinner"
                        />
                        <TicketCheck
                            v-else
                            class="size-4"
                            aria-hidden="true"
                            data-testid="cockpit-quick-generate-issue-icon"
                        />
                        {{
                            processing
                                ? 'Issuing…'
                                : onboardingEnabled
                                  ? 'Issue Invitation'
                                  : 'Issue Pay Code'
                        }}
                    </button>
                </div>
                <p
                    class="mt-1 min-w-0 text-sm text-slate-600 dark:text-slate-300"
                >
                    Set the value, payee, and purpose.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[0.68rem] font-semibold normal-case"
                        :class="voucherKindTone"
                        data-testid="cockpit-quick-generate-voucher-kind"
                    >
                        {{ voucherKindLabel }}
                    </span>
                    <div
                        class="flex flex-wrap items-center gap-2"
                        data-testid="cockpit-quick-generate-mode-control"
                    >
                        <span
                            class="flex shrink-0 items-center gap-1 text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Mode
                            <CockpitFieldHelp
                                label="About Mode"
                                tooltip="Pay Code issues a claimable value. Invitation also collects the identity details needed to open or link the recipient’s Account."
                            />
                        </span>
                        <div
                            class="inline-grid shrink-0 grid-cols-2 rounded-full bg-slate-100 p-1 dark:bg-slate-900"
                            role="group"
                            aria-label="Issuance mode"
                        >
                            <button
                                type="button"
                                :aria-pressed="!onboardingEnabled"
                                :class="[
                                    'min-h-8 rounded-full px-3 text-xs font-semibold transition',
                                    !onboardingEnabled
                                        ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-800 dark:text-emerald-200'
                                        : 'text-slate-600 dark:text-slate-300',
                                ]"
                                :disabled="processing"
                                data-testid="cockpit-quick-generate-mode-paycode"
                                @click="setOnboardingMode(false)"
                            >
                                Pay Code
                            </button>
                            <button
                                type="button"
                                :aria-pressed="onboardingEnabled"
                                :class="[
                                    'min-h-8 rounded-full px-3 text-xs font-semibold transition',
                                    onboardingEnabled
                                        ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-800 dark:text-emerald-200'
                                        : 'text-slate-600 dark:text-slate-300',
                                ]"
                                :disabled="processing"
                                data-testid="cockpit-quick-generate-mode-invitation"
                                @click="setOnboardingMode(true)"
                            >
                                Invitation
                            </button>
                        </div>
                    </div>
                </div>
                <div
                    class="mt-4 grid items-start gap-3 sm:grid-cols-2"
                    data-testid="cockpit-quick-generate-order-fields"
                >
                    <div
                        class="grid gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300"
                        data-testid="cockpit-quick-generate-amount-field"
                    >
                        <label
                            class="flex items-center gap-1"
                            for="cockpit-quick-generate-primary-amount"
                        >
                            Amount
                            <CockpitFieldHelp
                                label="About Amount"
                                tooltip="Value the recipient can claim. Select the field to open the calculator."
                            />
                        </label>
                        <CockpitAmountPicker
                            ref="amountInputElement"
                            v-model="amount"
                            :disabled="processing"
                            :estimated-cost="amountCalculatorEstimatedCost"
                            :estimate-pending="amountCalculatorEstimatePending"
                            :estimate-affordability="
                                liveAccountDebitAffordability
                            "
                            @preview="previewAmountInCalculator"
                        />
                        <div
                            class="mt-1 flex min-h-5 items-baseline justify-between gap-3 px-0.5 text-[0.7rem] leading-5"
                            data-testid="cockpit-quick-generate-account-debit"
                            :data-affordability="liveAccountDebitAffordability"
                            :title="
                                liveAccountDebitExceedsClientFunds
                                    ? 'Estimated Cost exceeds Client Funds.'
                                    : undefined
                            "
                            aria-live="polite"
                        >
                            <button
                                type="button"
                                class="underline-offset-2 hover:underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2"
                                :class="
                                    liveAccountDebitExceedsClientFunds
                                        ? 'font-semibold text-rose-600 hover:text-rose-700 focus-visible:outline-rose-600 dark:text-rose-300 dark:hover:text-rose-200'
                                        : 'font-medium text-slate-500 hover:text-emerald-700 focus-visible:outline-emerald-600 dark:text-slate-400 dark:hover:text-emerald-300'
                                "
                                data-testid="cockpit-quick-generate-account-debit-view-cost"
                                @click="canvasView = 'cost'"
                            >
                                Estimated Cost
                            </button>
                            <span
                                v-if="liveAccountDebit !== null"
                                class="shrink-0 font-semibold tabular-nums"
                                :class="
                                    liveAccountDebitExceedsClientFunds
                                        ? 'text-rose-600 dark:text-rose-300'
                                        : 'text-slate-700 dark:text-slate-200'
                                "
                                data-testid="cockpit-quick-generate-account-debit-amount"
                            >
                                {{ formatAccountMoney(liveAccountDebit) }}
                            </span>
                            <span
                                v-else-if="liveAccountDebitPending"
                                class="shrink-0 text-slate-400 dark:text-slate-500"
                                data-testid="cockpit-quick-generate-account-debit-loading"
                            >
                                Calculating…
                            </span>
                            <span
                                v-else
                                class="shrink-0 text-slate-400 dark:text-slate-500"
                                data-testid="cockpit-quick-generate-account-debit-unavailable"
                            >
                                —
                            </span>
                        </div>
                    </div>
                    <label
                        class="grid gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300"
                        data-testid="cockpit-quick-generate-recipient-field"
                    >
                        <span class="flex items-center gap-1">
                            Pay To
                            <CockpitFieldHelp
                                label="About Pay To"
                                tooltip="Leave blank or use CASH for an open claim. A mobile, email, @vendor, or quoted secret adds the matching safeguards."
                            />
                        </span>
                        <input
                            v-model="recipientReference"
                            :type="
                                payeeRequiresSecret && !payeeInputFocused
                                    ? 'password'
                                    : 'text'
                            "
                            class="h-12 w-full rounded-xl border bg-white px-3 text-sm text-slate-950 shadow-sm dark:bg-slate-900 dark:text-slate-50"
                            :class="
                                payeePolicy.kind === 'invalid' ||
                                payeePolicy.kind === 'email'
                                    ? 'border-rose-300 ring-2 ring-rose-100 dark:border-rose-800 dark:ring-rose-950'
                                    : 'border-slate-200 dark:border-slate-800'
                            "
                            data-testid="cockpit-quick-generate-primary-recipient"
                            :disabled="processing"
                            @focus="payeeInputFocused = true"
                            @blur="payeeInputFocused = false"
                        />
                        <span
                            v-if="payeeType !== 'anyone'"
                            class="min-h-5 px-0.5 text-[0.7rem] font-normal leading-5"
                            :class="
                                payeePolicy.kind === 'invalid' ||
                                payeePolicy.kind === 'email'
                                    ? 'text-rose-600 dark:text-rose-300'
                                    : 'text-slate-500 dark:text-slate-400'
                            "
                            data-testid="cockpit-quick-generate-primary-recipient-help"
                        >
                            {{ payeeHelpText }}
                        </span>
                    </label>
                    <label
                        class="grid gap-1 text-xs font-medium text-slate-700 sm:col-span-2 dark:text-slate-300"
                    >
                        <span class="flex items-center gap-1">
                            Purpose
                            <CockpitFieldHelp
                                label="About Purpose"
                                tooltip="Shown to the recipient as the Rider Message."
                            />
                        </span>
                        <input
                            v-model="purpose"
                            type="text"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                            data-testid="cockpit-quick-generate-primary-purpose"
                            :disabled="processing"
                        />
                    </label>
                    <div
                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 sm:col-span-2 dark:text-slate-300"
                        data-testid="cockpit-quick-generate-primary-feedback"
                    >
                        <span class="flex items-center gap-1">
                            Status Updates
                            <CockpitFieldHelp
                                label="About Status Updates"
                                tooltip="Optional email, mobile, or webhook destinations notified after the claim."
                            />
                        </span>
                        <CockpitFeedbackDestinationInput
                            v-model="feedbackDestinations"
                            :defaults="feedbackDestinationDefaults"
                            :unavailable="feedbackUnavailableReasons"
                            :disabled="processing"
                            @validation="feedbackTokenErrors = $event"
                        />
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <CockpitClaimRequirementsControl
                            :options="claimRequirementOptions"
                            :presets="claimRequirementPresets"
                            :disabled="processing"
                            help-tooltip="Information or evidence the recipient must provide before claiming."
                            @toggle="toggleInputField"
                            @preset="applyClaimRequirementPreset"
                        />
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <CockpitValueUseControl
                            :mode="sliceMode"
                            :amount="normalizedPayCodeAmount()"
                            :currency="currency"
                            :fixed-count="fixedSliceCount()"
                            :max-claims="Math.max(1, Number(maxSlices) || 1)"
                            :minimum-claim="Number(minWithdrawal) || 0"
                            :scheduled-count="namedClaimSlices.length"
                            :scheduled-portions="namedClaimSlices"
                            :scheduled-total="namedClaimSliceTotal"
                            :scheduled-remaining="namedClaimSliceRemaining"
                            :scheduled-minimum-amount="minimumWithdrawalFloor"
                            :scheduled-available="
                                scheduledPortionsUnavailableReason === null
                            "
                            :scheduled-unavailable-reason="
                                scheduledPortionsUnavailableReason
                            "
                            :scheduled-add-disabled-reason="
                                addSliceDisabledReason
                            "
                            :scheduled-validation-message="
                                namedClaimSliceValidationMessage
                            "
                            :reusable-balance="reusableBalance"
                            :stored-value-available="storedValueAvailable"
                            :stored-value-unavailable-reason="
                                storedValueUnavailableReason
                            "
                            :stored-value-replenishable="
                                storedValueReplenishable
                            "
                            :stored-value-maximum-balance="
                                normalizedStoredValueMaximumBalance
                            "
                            :stored-value-otp-above="
                                normalizedStoredValueOtpAbove
                            "
                            :disabled="processing"
                            @mode="setSliceMode"
                            @fixed-count="setFixedSliceCount"
                            @max-claims="setMaximumClaims"
                            @minimum-claim="setMinimumClaimAmount"
                            @reusable-balance="setReusableBalance"
                            @stored-value-replenishable="
                                setStoredValueReplenishable
                            "
                            @stored-value-maximum-balance="
                                setStoredValueMaximumBalance
                            "
                            @stored-value-otp-above="setStoredValueOtpAbove"
                            @scheduled-add="addNamedClaimSlice"
                            @scheduled-remove="removeNamedClaimSlice"
                            @scheduled-update="updateNamedClaimSlice"
                        />
                        <p
                            v-if="storedValuePolicyError"
                            class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-300"
                            data-testid="cockpit-value-use-policy-error"
                        >
                            {{ storedValuePolicyError }}
                        </p>
                    </div>
                </div>

                <fieldset
                    v-if="!isAccountFundingClaim && !reusableBalance"
                    class="mt-4 grid min-w-0 gap-1.5 border-t border-emerald-100 pt-4 dark:border-emerald-900/70"
                    data-testid="cockpit-quick-generate-primary-settlement-rail"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <legend
                                class="flex items-center gap-1 text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Transfer Network
                                <CockpitFieldHelp
                                    label="About Transfer Network"
                                    :tooltip="settlementRailCycleDescription"
                                />
                            </legend>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-55 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                                :disabled="processing"
                                :aria-label="settlementRailCycleAccessibleLabel"
                                data-testid="cockpit-quick-generate-settlement-rail-cycle"
                                @click="cycleSettlementRail"
                            >
                                <RotateCcw
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ currentSettlementRailLabel }}
                            </button>
                        </div>
                        <p
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                            data-testid="cockpit-quick-generate-payout-provider"
                        >
                            via
                            <span class="font-semibold">{{
                                payoutProviderLabel
                            }}</span>
                        </p>
                    </div>
                    <p
                        v-if="settlementRailSelectionError"
                        class="text-xs font-medium text-rose-600 dark:text-rose-300"
                        data-testid="cockpit-quick-generate-settlement-rail-error"
                    >
                        {{ settlementRailSelectionError }}
                    </p>
                </fieldset>

                <section
                    class="mt-4 border-t border-emerald-100 pt-4 dark:border-emerald-900/70"
                    data-testid="cockpit-quick-generate-starting-point"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300"
                        >
                            Templates
                        </p>
                        <span
                            class="min-w-0 truncate rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[0.68rem] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                            data-testid="cockpit-quick-generate-current-template"
                        >
                            Current · {{ currentTemplateName }}
                        </span>
                    </div>

                    <div
                        class="mt-2 flex flex-nowrap items-center gap-1.5 overflow-x-auto pb-1"
                        data-testid="cockpit-quick-generate-template-toolbar"
                    >
                        <button
                            type="button"
                            :aria-pressed="startingPoint === 'blank'"
                            :class="[
                                'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border px-2.5 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600',
                                startingPoint === 'blank'
                                    ? 'border-emerald-400 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-900'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-900',
                            ]"
                            data-testid="cockpit-quick-generate-start-blank"
                            @click="startBlank"
                        >
                            <Check
                                v-if="startingPoint === 'blank'"
                                class="size-3.5"
                                aria-hidden="true"
                                data-testid="cockpit-quick-generate-start-blank-check"
                            />
                            <FilePlus2
                                v-else
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            Blank
                        </button>
                        <button
                            type="button"
                            :disabled="!lastInstructions"
                            :aria-pressed="startingPoint === 'last'"
                            :class="[
                                'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border px-2.5 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-45',
                                startingPoint === 'last'
                                    ? 'border-emerald-400 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-200 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-900'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-900',
                            ]"
                            data-testid="cockpit-quick-generate-repeat-last"
                            @click="repeatLastDesign"
                        >
                            <Check
                                v-if="startingPoint === 'last'"
                                class="size-3.5"
                                aria-hidden="true"
                                data-testid="cockpit-quick-generate-repeat-last-check"
                            />
                            <RotateCcw
                                v-else
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            Repeat Last
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                            data-testid="cockpit-quick-generate-choose-template"
                            @click="templatePickerOpen = true"
                        >
                            <LayoutTemplate
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            Choose Template
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                            data-testid="cockpit-quick-generate-save-template"
                            @click="openSaveTemplateDialog"
                        >
                            <Save class="size-3.5" aria-hidden="true" />
                            Save Template
                        </button>
                    </div>
                </section>
            </div>

            <div
                ref="canvasSectionElement"
                class="min-w-0 xl:sticky xl:top-4 xl:self-start"
            >
                <CockpitPayCodeCanvas
                    v-model:view="canvasView"
                    :amount="amount"
                    :currency="currency"
                    :recipient="payeeDisplayReference"
                    :purpose="purpose"
                    :claim-outcome="claimOutcome"
                    :voucher-type="voucherType"
                    :expiry="canvasExpiryLabel"
                    :instruction-keys="canvasInstructionKeys"
                    :issued-code="resultCode"
                    :has-rider-design="usesRiderArtwork"
                    :rider-design-source="riderStampPreview.source"
                    :rider-design-document="riderCanvasArtworkDocument"
                    :rider-stamp="riderStampPreview"
                    :cost-estimate="livePricingEstimate"
                    :cost-loading="livePricingEstimating"
                    :cost-error="livePricingEstimateError"
                    :quantity="count"
                >
                    <template #design>
                        <div
                            ref="riderDesignTeleportTarget"
                            id="quick-generate-rider-design-editor"
                            class="h-full overflow-y-auto overscroll-contain pr-1"
                            data-testid="cockpit-quick-generate-rider-design-editor"
                        />
                    </template>
                    <template #claim>
                        <CockpitClaimExperiencePreview
                            :status="previewStatus"
                            :processing="previewProcessing"
                            :message="previewMessage"
                            :manifest="previewResult"
                            :stale="previewStale"
                            :can-generate="canGenerateClaimPreview"
                            @generate="generateClaimPreview(false)"
                            @refresh="generateClaimPreview(true)"
                        />
                    </template>
                </CockpitPayCodeCanvas>
            </div>
        </div>

        <CockpitIssuedPayCodeDialog
            :open="issuedPayCodeDialogOpen"
            :code="resultCode"
            :amount="amount"
            :currency="currency"
            :recipient="payeeDisplayReference"
            :purpose="purpose"
            :claim-outcome="
                isAccountFundingResult
                    ? 'account_funding'
                    : 'provider_disbursement'
            "
            :voucher-type="voucherType"
            :expiry="canvasExpiryLabel"
            :instruction-keys="canvasInstructionKeys"
            :has-rider-design="usesRiderArtwork"
            :rider-design-source="riderStampPreview.source"
            :rider-design-document="riderCanvasArtworkDocument"
            :rider-stamp="riderStampPreview"
            :cost-estimate="issuedCostEstimate"
            :quantity="count"
            :claim-url="beneficiaryClaimUrl"
            :claim-qr="beneficiaryClaimQr"
            :share-card-url="beneficiaryShareCardUrl"
            :detail-url="cockpitDetailUrl"
            @close="issuedPayCodeDialogOpen = false"
        />

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

        <details
            ref="instructionBuilderElement"
            class="mt-5 rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
            data-testid="cockpit-voucher-instruction-builder"
        >
            <summary
                class="cursor-pointer list-none text-sm font-semibold text-slate-950 dark:text-slate-50"
            >
                <span class="flex items-center justify-between gap-3">
                    <span>
                        Claim Experience
                        <span
                            class="ml-2 text-xs font-normal text-slate-500 dark:text-slate-400"
                        >
                            Optional requirements, checks, rider content, and
                            advanced rules
                        </span>
                    </span>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                    >
                        Customize
                    </span>
                </span>
            </summary>
            <div class="mt-4 grid gap-4">
                <details
                    id="quick-generate-contract-money"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-200"
                            >1</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Issuance Details
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Choose the template, value, recipient,
                                availability, and quantity.
                            </p>
                        </div>
                    </summary>
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
                                    class="w-24 min-w-0 rounded-r-xl border border-l-0 border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold uppercase text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                    data-testid="cockpit-quick-generate-submit-currency"
                                    :disabled="processing"
                                />
                            </div>
                        </label>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Recipient
                            <input
                                v-model="recipientReference"
                                :type="
                                    payeeRequiresSecret && !payeeInputFocused
                                        ? 'password'
                                        : 'text'
                                "
                                :placeholder="
                                    isAccountFundingClaim
                                        ? 'CASH or verified 0917...'
                                        : 'CASH, 0917..., or vendor alias'
                                "
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-submit-recipient"
                                :disabled="processing"
                                @focus="payeeInputFocused = true"
                                @blur="payeeInputFocused = false"
                            />
                            <span
                                class="text-[11px] font-normal text-slate-500 dark:text-slate-400"
                                data-testid="cockpit-quick-generate-payee-help"
                            >
                                {{ payeeHelpText }}
                            </span>
                        </label>

                        <fieldset
                            id="quick-generate-claim-outcome"
                            class="grid min-w-0 gap-2 lg:col-span-2"
                            data-testid="cockpit-quick-generate-claim-outcome"
                        >
                            <legend
                                class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Recipient Receives
                            </legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                    :class="
                                        claimOutcome === 'provider_disbursement'
                                            ? 'border-emerald-400 bg-emerald-50 text-emerald-950 ring-2 ring-emerald-100 dark:border-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-100 dark:ring-emerald-950'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-200 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                                    "
                                >
                                    <input
                                        v-model="claimOutcome"
                                        type="radio"
                                        value="provider_disbursement"
                                        class="mt-0.5 rounded-full border-slate-300 text-emerald-600"
                                        data-testid="cockpit-quick-generate-claim-outcome-provider"
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'feedback.email',
                                                feedbackEmailEnabled,
                                            )
                                        "
                                    />
                                    <span>
                                        <span
                                            class="block text-sm font-semibold"
                                        >
                                            Cash Payout
                                        </span>
                                        <span
                                            class="mt-0.5 block text-[11px] leading-4"
                                        >
                                            Send the value through the selected
                                            payout provider.
                                        </span>
                                    </span>
                                </label>

                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                    :class="
                                        claimOutcome === 'account_funding'
                                            ? 'border-cyan-400 bg-cyan-50 text-cyan-950 ring-2 ring-cyan-100 dark:border-cyan-500 dark:bg-cyan-950/40 dark:text-cyan-100 dark:ring-cyan-950'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-cyan-200 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                                    "
                                >
                                    <input
                                        v-model="claimOutcome"
                                        type="radio"
                                        value="account_funding"
                                        class="mt-0.5 rounded-full border-slate-300 text-cyan-600"
                                        data-testid="cockpit-quick-generate-claim-outcome-account"
                                        :disabled="processing"
                                    />
                                    <span>
                                        <span
                                            class="block text-sm font-semibold"
                                        >
                                            Account Funds
                                        </span>
                                        <span
                                            class="mt-0.5 block text-[11px] leading-4"
                                        >
                                            Add the whole amount to an Account.
                                            No bank payout occurs.
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <p
                                v-if="claimRecipientError"
                                class="text-xs font-medium text-rose-600 dark:text-rose-300"
                                data-testid="cockpit-quick-generate-claim-recipient-error"
                            >
                                {{ claimRecipientError }}
                            </p>
                        </fieldset>

                        <label
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Expiration
                            <select
                                v-model="expiryPreset"
                                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                data-testid="cockpit-quick-generate-expiry-preset"
                                :disabled="processing"
                            >
                                <option value="none">No Expiration</option>
                                <option value="P12H">12 Hours</option>
                                <option value="P1D">1 Day</option>
                                <option value="P3D">3 Days</option>
                                <option value="P7D">7 Days</option>
                                <option value="custom">Custom</option>
                            </select>
                            <span
                                class="text-[11px] font-normal text-slate-500 dark:text-slate-400"
                            >
                                Advanced timing settings take precedence.
                            </span>
                        </label>

                        <label
                            v-if="expiryPreset === 'custom'"
                            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                        >
                            Custom Expiration (Days)
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
                            Advanced Issuance Settings
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
                                        Effective Expiration
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
                                An exact expiration takes priority over a custom
                                duration, which takes priority over the preset.
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
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Optional characters shown before the
                                    generated code.
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
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Optional pattern for generated codes.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none"
                                    >Custom Duration</span
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
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Use an ISO-8601 duration, such as P1D or
                                    PT12H.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Starts At</span>
                                <input
                                    v-model="startsAt"
                                    type="datetime-local"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-starts-at"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Optional date and time when claims begin.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Expires At</span>
                                <input
                                    v-model="expiresAt"
                                    type="datetime-local"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-expires-at"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Exact date and time when claims end.
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none">Fee Handling</span>
                                <select
                                    v-model="feeStrategy"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-fee-strategy"
                                    :disabled="
                                        processing || isAccountFundingClaim
                                    "
                                >
                                    <option value="absorb">
                                        Absorb — Issuer Pays
                                    </option>
                                    <option value="include">
                                        Include — Deduct From Value
                                    </option>
                                    <option value="add">
                                        Add — Charge On Top
                                    </option>
                                </select>
                                <span
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Preview how the configured provider transfer
                                    cost affects the recipient and issuer.
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
                                            class="text-[11px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300"
                                        >
                                            Provider Transfer Cost
                                        </p>
                                        <p class="font-semibold">
                                            {{ formatMoney(illustrativeFee) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-[11px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300"
                                        >
                                            Recipient Amount
                                        </p>
                                        <p class="font-semibold">
                                            {{
                                                feeStrategyPreview.recipientAmount
                                            }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-[11px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300"
                                        >
                                            Issuer Cost
                                        </p>
                                        <p class="font-semibold">
                                            {{ feeStrategyPreview.issuerCost }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    class="text-[11px] text-emerald-700 dark:text-emerald-300"
                                >
                                    {{ feeStrategyPreview.note }} x-change
                                    issuance pricing remains separately itemized
                                    under Cost.
                                </p>
                            </div>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                <span class="leading-none"
                                    >Pay Code Purpose</span
                                >
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
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
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
                                    >Custom Purpose Key</span
                                >
                                <input
                                    v-model="customCashType"
                                    type="text"
                                    placeholder="Approved purpose key"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-custom-cash-type"
                                    :disabled="processing"
                                />
                                <span
                                    class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Use the key supplied by the approved
                                    integration.
                                </span>
                            </label>
                            <div
                                class="grid min-w-0 gap-2 rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-700 lg:col-span-3 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                data-testid="cockpit-quick-generate-mandate-options"
                            >
                                <div>
                                    <p
                                        class="font-semibold leading-none text-slate-800 dark:text-slate-100"
                                    >
                                        Required Conditions
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        Choose any conditions that must be met
                                        before the Pay Code can be completed.
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
                                                class="text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
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
                                        >Additional Condition Keys</span
                                    >
                                    <input
                                        v-model="customMandates"
                                        type="text"
                                        placeholder="Comma-separated custom keys"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-mandates"
                                        :disabled="processing"
                                    />
                                    <span
                                        class="min-h-8 text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        Add approved condition keys that are not
                                        listed above.
                                    </span>
                                </label>
                                <details
                                    class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950"
                                    data-testid="cockpit-quick-generate-mandates-preview"
                                >
                                    <summary
                                        class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        Selected Conditions
                                    </summary>
                                    <div class="mt-2 grid gap-2">
                                        <p
                                            class="text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                                        >
                                            Review the condition keys that will
                                            be included with this Pay Code.
                                        </p>
                                        <div
                                            class="break-words rounded-lg border border-slate-200 bg-white p-3 font-mono text-xs text-slate-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                                            data-testid="cockpit-quick-generate-mandates-preview-value"
                                        >
                                            {{ effectiveMandatesDisplay }}
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </details>
                </details>

                <details
                    id="quick-generate-contract-inputs"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-700 dark:bg-sky-900/60 dark:text-sky-200"
                            >2</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Claim Requirements
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Choose what the recipient must provide before
                                claiming.
                            </p>
                        </div>
                    </summary>
                    <div class="mt-4 grid gap-3">
                        <div
                            class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3"
                            data-testid="cockpit-quick-generate-input-fields"
                        >
                            <label
                                v-for="field in voucherInputFieldOptions"
                                :key="field.value"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                :class="{
                                    'border-amber-200 bg-amber-50/70 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-200':
                                        inputFieldCapability(field.value) !==
                                            null &&
                                        capabilityUnavailable(field.value),
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :value="field.value"
                                    :checked="isInputFieldSelected(field.value)"
                                    class="rounded border-slate-300"
                                    :disabled="
                                        isAutomaticInputField(field.value) ||
                                        (inputFieldCapability(field.value) !==
                                            null &&
                                            capabilitySelectionDisabled(
                                                field.value,
                                                isInputFieldSelected(
                                                    field.value,
                                                ),
                                            ))
                                    "
                                    :data-onboarding-locked="
                                        isAutomaticInputField(field.value)
                                            ? 'true'
                                            : undefined
                                    "
                                    @change="
                                        toggleInputField(
                                            field.value,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span>
                                    <span class="block">{{ field.label }}</span>
                                    <span
                                        class="mt-0.5 block text-[11px] font-normal text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            inputFieldCapability(
                                                field.value,
                                            ) !== null &&
                                            capabilityUnavailable(field.value)
                                                ? capabilityReason(
                                                      field.value,
                                                      field.helper,
                                                  )
                                                : field.helper
                                        }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>
                </details>

                <details
                    id="quick-generate-contract-validation"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    data-testid="cockpit-quick-generate-validation-section"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-900/60 dark:text-violet-200"
                            >3</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Validation And Verification
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Choose how the recipient and submitted proof
                                will be checked.
                            </p>
                        </div>
                    </summary>
                    <div class="mt-4 grid gap-3">
                        <div
                            class="grid gap-3 rounded-xl border border-violet-100 bg-violet-50 p-3 text-xs text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                            data-testid="cockpit-quick-generate-recipient-match-group"
                        >
                            <div>
                                <p class="font-semibold">Recipient Matching</p>
                                <p class="mt-1">
                                    Match claim data against the intended
                                    recipient when the Pay Code should be
                                    restricted to a mobile number or payable
                                    alias.
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-violet-200 bg-white/70 p-3 dark:border-violet-900/60 dark:bg-violet-950/40"
                                data-testid="cockpit-quick-generate-payee-interpretation"
                            >
                                <p class="font-semibold">
                                    Recipient Matching Guide
                                </p>
                                <p class="mt-1">
                                    {{ payeeHelpText }}
                                </p>
                                <p
                                    class="mt-1 text-violet-700 dark:text-violet-300"
                                >
                                    Mobile numbers and vendor aliases restrict
                                    who may claim. CASH or a blank recipient
                                    allows any eligible claimant.
                                </p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-violet-200 bg-white/70 p-3 text-xs font-medium text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                                >
                                    <input
                                        v-model="mobileValidationSelection"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-violet-300"
                                        :disabled="
                                            processing || payeeRequiresMobile
                                        "
                                        data-testid="cockpit-quick-generate-mobile-validation"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Match Mobile Number</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-violet-700 dark:text-violet-300"
                                        >
                                            Require the claimant’s mobile number
                                            to match the recipient.
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
                                        <span>Require Vendor Alias</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-violet-700 dark:text-violet-300"
                                        >
                                            Require a payable vendor alias when
                                            one is expected.
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
                                        <span>Restrict To The Philippines</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-violet-700 dark:text-violet-300"
                                        >
                                            Accept claims only from the
                                            Philippines.
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
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'location',
                                                requireLocationValidation,
                                            )
                                        "
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Require Location Radius</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-violet-700 dark:text-violet-300"
                                        >
                                            {{
                                                capabilityUnavailable(
                                                    'location',
                                                )
                                                    ? capabilityReason(
                                                          'location',
                                                          'Location evidence is unavailable.',
                                                      )
                                                    : 'Require the claim to occur within the allowed area.'
                                            }}
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
                                <span>Claim PIN Or Release Code</span>
                                <input
                                    v-model="validationSecret"
                                    type="text"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-validation-secret"
                                    :disabled="processing"
                                />
                                <span
                                    class="text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                >
                                    Use a private code when staff must verify
                                    the recipient before release.
                                </span>
                            </label>
                        </div>
                        <div
                            class="grid gap-2 rounded-xl border border-sky-100 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                            data-testid="cockpit-quick-generate-evidence-required-group"
                        >
                            <div>
                                <p class="font-semibold">Required Proof</p>
                                <p class="mt-1">
                                    Choose the proof the recipient must complete
                                    during the claim.
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
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'kyc',
                                                verificationKyc,
                                            )
                                        "
                                    />
                                    <span class="grid gap-0.5">
                                        <span>KYC</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-sky-700 dark:text-sky-300"
                                        >
                                            {{
                                                capabilityUnavailable('kyc')
                                                    ? capabilityReason(
                                                          'kyc',
                                                          'KYC is unavailable.',
                                                      )
                                                    : 'Verify the recipient’s identity.'
                                            }}
                                        </span>
                                    </span>
                                </label>
                                <label
                                    class="flex items-start gap-2 rounded-xl border border-sky-200 bg-white/70 p-3 text-xs font-medium text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-100"
                                >
                                    <input
                                        v-model="otpSelection"
                                        type="checkbox"
                                        class="mt-0.5 rounded border-sky-300"
                                        :disabled="
                                            onboardingOtpEnforced ||
                                            payeeRequiresMobile ||
                                            capabilitySelectionDisabled(
                                                'otp',
                                                effectiveVerificationOtp,
                                            )
                                        "
                                        data-testid="cockpit-quick-generate-otp-verification"
                                    />
                                    <span class="grid gap-0.5">
                                        <span>OTP</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-sky-700 dark:text-sky-300"
                                        >
                                            {{
                                                capabilityUnavailable('otp')
                                                    ? capabilityReason(
                                                          'otp',
                                                          'OTP is unavailable.',
                                                      )
                                                    : 'Confirm a one-time passcode.'
                                            }}
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
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'selfie',
                                                verificationSelfie,
                                            )
                                        "
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Selfie</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-sky-700 dark:text-sky-300"
                                        >
                                            Capture a recipient selfie.
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
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'signature',
                                                signatureRequired,
                                            )
                                        "
                                    />
                                    <span class="grid gap-0.5">
                                        <span>Require Signature</span>
                                        <span
                                            class="text-[11px] font-normal leading-snug text-sky-700 dark:text-sky-300"
                                        >
                                            Capture the recipient’s signature.
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
                                Advanced Verification Rules
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Signature Failure
                                    <select
                                        v-model="signatureFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !signatureRequired
                                        "
                                    >
                                        <option value="block">
                                            Block Claim
                                        </option>
                                        <option value="warn">
                                            Allow With Warning
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    OTP Failure
                                    <select
                                        v-model="otpFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing ||
                                            !effectiveVerificationOtp
                                        "
                                    >
                                        <option value="block">
                                            Block Claim
                                        </option>
                                        <option value="warn">
                                            Allow With Warning
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Selfie Failure
                                    <select
                                        v-model="selfieFailure"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        :disabled="
                                            processing || !verificationSelfie
                                        "
                                    >
                                        <option value="block">
                                            Block Claim
                                        </option>
                                        <option value="warn">
                                            Allow With Warning
                                        </option>
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
                                    Require Face Match
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
                                        <option value="block">
                                            Block Claim
                                        </option>
                                        <option value="warn">
                                            Allow With Warning
                                        </option>
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
                                    Start Time
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
                                    End Time
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
                                    Time Zone
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
                                    Time Limit (Minutes)
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
                                    Track Claim Duration
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
                                Validation Summary
                            </summary>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                    >
                                        Recipient Rules
                                    </p>
                                    <p
                                        class="mt-1 break-words font-mono text-xs text-slate-800 dark:text-slate-100"
                                        data-testid="cockpit-quick-generate-cash-validation-preview-value"
                                    >
                                        {{ validationPreviewDisplay }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                                    >
                                        Additional Checks
                                    </p>
                                    <p
                                        class="mt-1 break-words font-mono text-xs text-slate-800 dark:text-slate-100"
                                        data-testid="cockpit-quick-generate-structured-validation-preview-value"
                                    >
                                        {{ structuredValidationPreviewDisplay }}
                                    </p>
                                </div>
                            </div>
                            <p
                                class="mt-2 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                Only rule names are shown. Private codes and
                                submitted proof remain hidden.
                            </p>
                        </details>
                    </div>
                </details>

                <section
                    id="quick-generate-contract-rider"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-bold text-amber-700 dark:bg-amber-900/60 dark:text-amber-200"
                                >4</span
                            >
                            <div class="min-w-0">
                                <h4
                                    class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    Rider Design
                                </h4>
                                <p
                                    class="truncate text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        purpose.trim() !== '' ||
                                        riderUrl.trim() !== '' ||
                                        riderSplash.trim() !== '' ||
                                        hasRiderStampCustomization
                                            ? 'Configured in the Pay Code canvas.'
                                            : 'Optional message, link, artwork, and Stamp presentation.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="inline-flex min-h-9 shrink-0 items-center justify-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-800 transition hover:border-amber-300 hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                            data-testid="cockpit-quick-generate-open-design-button"
                            :disabled="processing"
                            @click="openDesignEditor"
                        >
                            <Palette class="size-3.5" aria-hidden="true" />
                            Open Design
                        </button>
                    </div>
                    <Teleport
                        v-if="
                            riderDesignTeleportReady &&
                            riderDesignTeleportTarget !== null
                        "
                        :to="riderDesignTeleportTarget"
                    >
                        <div class="grid gap-3">
                            <div
                                role="tablist"
                                aria-label="Rider Design editor"
                                class="sticky top-0 z-30 grid grid-cols-4 gap-1 rounded-xl border border-slate-200 bg-white/95 p-1 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-950/95"
                                data-testid="cockpit-quick-generate-rider-design-tabs"
                            >
                                <button
                                    type="button"
                                    role="tab"
                                    class="inline-flex min-w-0 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-[0.68rem] font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-xs"
                                    :class="
                                        riderDesignEditor === 'appearance'
                                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100'
                                    "
                                    :aria-selected="
                                        riderDesignEditor === 'appearance'
                                    "
                                    data-testid="cockpit-quick-generate-rider-design-appearance-tab"
                                    @click="
                                        selectRiderDesignEditor('appearance')
                                    "
                                >
                                    <Palette
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">Appearance</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    class="inline-flex min-w-0 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-[0.68rem] font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-xs"
                                    :class="
                                        riderDesignEditor === 'message'
                                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100'
                                    "
                                    :aria-selected="
                                        riderDesignEditor === 'message'
                                    "
                                    data-testid="cockpit-quick-generate-rider-design-message-tab"
                                    @click="selectRiderDesignEditor('message')"
                                >
                                    <MessageSquareText
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">Message</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    class="inline-flex min-w-0 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-[0.68rem] font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-xs"
                                    :class="
                                        riderDesignEditor === 'link'
                                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100'
                                    "
                                    :aria-selected="
                                        riderDesignEditor === 'link'
                                    "
                                    data-testid="cockpit-quick-generate-rider-design-link-tab"
                                    @click="selectRiderDesignEditor('link')"
                                >
                                    <Link2
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">Link</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    class="inline-flex min-w-0 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-[0.68rem] font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:text-xs"
                                    :class="
                                        riderDesignEditor === 'splash'
                                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100'
                                    "
                                    :aria-selected="
                                        riderDesignEditor === 'splash'
                                    "
                                    data-testid="cockpit-quick-generate-rider-design-splash-tab"
                                    @click="selectRiderDesignEditor('splash')"
                                >
                                    <Sparkles
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">Splash</span>
                                </button>
                            </div>
                            <CockpitRiderEditorDisclosure
                                v-show="riderDesignEditor === 'message'"
                                title="Rider Message"
                                description="Add an optional message for the recipient."
                                :default-open="true"
                                :status="
                                    purpose.trim() === ''
                                        ? 'Empty'
                                        : 'Configured'
                                "
                                :summary="riderMessageDisclosureSummary"
                                data-testid="cockpit-quick-generate-rider-message-editor"
                            >
                                <CockpitRiderMessageEditor
                                    v-model:message="purpose"
                                    v-model:format="riderMessageFormat"
                                    :disabled="processing"
                                />
                            </CockpitRiderEditorDisclosure>
                            <CockpitRiderEditorDisclosure
                                v-show="riderDesignEditor === 'link'"
                                title="Rider URL"
                                description="Add an optional destination after the claim."
                                :default-open="true"
                                :status="
                                    riderUrl.trim() === ''
                                        ? 'Empty'
                                        : 'Configured'
                                "
                                :summary="riderUrlDisclosureSummary"
                                data-testid="cockpit-quick-generate-rider-cta-section"
                            >
                                <div class="grid gap-3">
                                    <label
                                        class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                    >
                                        Link
                                        <input
                                            v-model="riderUrl"
                                            type="url"
                                            class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                            data-testid="cockpit-quick-generate-rider-url"
                                            :disabled="processing"
                                        />
                                    </label>
                                    <CockpitRiderLibrary
                                        kind="url"
                                        :entries="riderLibrary"
                                        :current-payload="{
                                            url: riderUrl.trim(),
                                        }"
                                        :disabled="processing"
                                        @apply="applyRiderUrlLibraryPayload"
                                    />
                                </div>
                                <div
                                    v-if="riderUrl.trim() !== ''"
                                    class="mt-3 rounded-xl border border-sky-200 bg-white p-3 dark:border-sky-900/60 dark:bg-slate-950"
                                    data-testid="cockpit-quick-generate-rider-url-preview"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p
                                            class="text-[11px] font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300"
                                        >
                                            Rider URL Preview
                                        </p>
                                        <p
                                            class="text-[11px] text-sky-700 dark:text-sky-300"
                                            data-testid="cockpit-quick-generate-rider-url-preview-status"
                                        >
                                            {{
                                                riderUrlArtworkResolving
                                                    ? 'Loading Artwork…'
                                                    : riderUrlArtworkMessage
                                            }}
                                        </p>
                                    </div>
                                    <div class="mt-2">
                                        <CockpitRiderPreviewFrame
                                            title="Rider URL Preview"
                                            surface="stamp"
                                            class="border-sky-200 dark:border-sky-900/60"
                                            data-testid="cockpit-quick-generate-rider-url-artwork-preview"
                                            :document="riderUrlPreviewDocument"
                                        />
                                    </div>
                                </div>
                            </CockpitRiderEditorDisclosure>
                            <CockpitRiderEditorDisclosure
                                v-show="riderDesignEditor === 'splash'"
                                title="Rider Splash"
                                description="Design an optional introduction before the claim."
                                :default-open="true"
                                :status="
                                    riderSplash.trim() === ''
                                        ? 'Empty'
                                        : 'Configured'
                                "
                                :summary="riderSplashDisclosureSummary"
                                data-testid="cockpit-quick-generate-rider-splash-builder"
                            >
                                <div class="grid gap-3">
                                    <fieldset
                                        class="flex flex-wrap items-center gap-1"
                                        data-testid="cockpit-quick-generate-rider-splash-format"
                                    >
                                        <legend class="sr-only">
                                            Splash format
                                        </legend>
                                        <label
                                            v-for="option in [
                                                {
                                                    value: 'plain',
                                                    label: 'Text',
                                                },
                                                {
                                                    value: 'markdown',
                                                    label: 'Markdown',
                                                },
                                                {
                                                    value: 'html',
                                                    label: 'HTML',
                                                },
                                            ]"
                                            :key="option.value"
                                            class="cursor-pointer rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-orange-600"
                                            :class="
                                                riderSplashFormat ===
                                                option.value
                                                    ? 'border-orange-600 bg-orange-600 text-white'
                                                    : 'border-orange-200 bg-white text-orange-800 hover:bg-orange-50 dark:border-orange-900/60 dark:bg-slate-900 dark:text-orange-200 dark:hover:bg-orange-950/40'
                                            "
                                        >
                                            <input
                                                v-model="riderSplashFormat"
                                                type="radio"
                                                class="sr-only"
                                                name="rider-splash-format"
                                                :value="option.value"
                                                :disabled="processing"
                                            />
                                            {{ option.label }}
                                        </label>
                                    </fieldset>
                                    <label
                                        class="grid min-w-0 gap-1 text-xs font-medium text-orange-950 dark:text-orange-100"
                                    >
                                        Splash
                                        <textarea
                                            v-model="riderSplash"
                                            rows="5"
                                            class="w-full min-w-0 rounded-xl border border-orange-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-orange-900/60 dark:bg-slate-900 dark:text-slate-50"
                                            data-testid="cockpit-quick-generate-rider-splash-body"
                                            :disabled="processing"
                                        />
                                    </label>
                                    <CockpitRiderLibrary
                                        kind="splash"
                                        :entries="riderLibrary"
                                        :current-payload="{
                                            splash: riderSplashInstructionContent,
                                            format: riderSplashFormat,
                                        }"
                                        :disabled="processing"
                                        @apply="applyRiderSplashLibraryPayload"
                                    />
                                    <div
                                        class="rounded-xl border border-orange-200 bg-white p-3 dark:border-orange-900/60 dark:bg-slate-950"
                                        data-testid="cockpit-quick-generate-rider-splash-preview"
                                    >
                                        <p
                                            class="text-[11px] font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-300"
                                        >
                                            Claim Splash Preview
                                        </p>
                                        <p
                                            class="mt-1 text-[11px] leading-snug text-orange-800 dark:text-orange-200"
                                        >
                                            {{
                                                riderSplashFormat === 'html'
                                                    ? 'Custom HTML is isolated inside this preview.'
                                                    : 'Formatting is rendered inside an isolated preview.'
                                            }}
                                        </p>
                                        <div class="mt-2">
                                            <CockpitRiderPreviewFrame
                                                title="Claim Splash Preview"
                                                surface="splash"
                                                class="border-orange-200 dark:border-orange-900/60"
                                                data-testid="cockpit-quick-generate-rider-splash-html-preview"
                                                :document="
                                                    riderSplashPreviewDocument
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CockpitRiderEditorDisclosure>
                            <CockpitRiderEditorDisclosure
                                v-show="riderDesignEditor === 'appearance'"
                                id="quick-generate-front-design"
                                title="Stamp Appearance"
                                description="Compose the Stamp from Rider content."
                                :default-open="true"
                                :status="
                                    hasRiderStampCustomization
                                        ? 'Configured'
                                        : 'x-change'
                                "
                                :summary="riderStampDisclosureSummary"
                                data-testid="cockpit-quick-generate-rider-stamp-editor"
                            >
                                <div
                                    class="grid gap-5"
                                    data-testid="cockpit-quick-generate-rider-appearance-layout"
                                >
                                    <div class="grid min-w-0 gap-5">
                                        <div
                                            class="grid gap-2"
                                            data-testid="cockpit-quick-generate-rider-artwork-picker"
                                        >
                                            <fieldset
                                                data-testid="cockpit-quick-generate-rider-stamp-source"
                                            >
                                                <legend class="sr-only">
                                                    Artwork
                                                </legend>
                                                <div
                                                    class="grid grid-cols-4 gap-1 rounded-xl border border-slate-200 bg-slate-100/80 p-1 dark:border-slate-800 dark:bg-slate-900/80"
                                                >
                                                    <label
                                                        v-for="option in riderArtworkSourceOptions"
                                                        :key="option.value"
                                                        class="group relative grid min-w-0 cursor-pointer place-items-center rounded-lg border p-1 transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                        :class="[
                                                            riderStampArtworkSource ===
                                                            option.value
                                                                ? 'border-sky-600 bg-sky-100 shadow-sm ring-2 ring-sky-500/25 dark:border-sky-400 dark:bg-sky-950/70'
                                                                : 'border-transparent bg-white hover:border-sky-300 dark:bg-slate-950 dark:hover:border-sky-800',
                                                            (option.value ===
                                                                'url' &&
                                                                riderUrl.trim() ===
                                                                    '' &&
                                                                riderStampArtworkSource !==
                                                                    'url') ||
                                                            (option.value ===
                                                                'splash' &&
                                                                riderSplashContent.trim() ===
                                                                    '' &&
                                                                riderStampArtworkSource !==
                                                                    'splash')
                                                                ? 'cursor-not-allowed opacity-40'
                                                                : '',
                                                        ]"
                                                        :title="option.label"
                                                    >
                                                        <input
                                                            v-model="
                                                                riderStampArtworkSource
                                                            "
                                                            type="radio"
                                                            class="sr-only"
                                                            name="rider-stamp-artwork-source"
                                                            :value="
                                                                option.value
                                                            "
                                                            :aria-label="
                                                                option.label
                                                            "
                                                            :aria-describedby="`rider-stamp-artwork-tooltip-${option.value}`"
                                                            :data-testid="`cockpit-quick-generate-rider-stamp-source-${option.value}`"
                                                            :disabled="
                                                                processing ||
                                                                (option.value ===
                                                                    'url' &&
                                                                    riderUrl.trim() ===
                                                                        '' &&
                                                                    riderStampArtworkSource !==
                                                                        'url') ||
                                                                (option.value ===
                                                                    'splash' &&
                                                                    riderSplashContent.trim() ===
                                                                        '' &&
                                                                    riderStampArtworkSource !==
                                                                        'splash')
                                                            "
                                                            @change="
                                                                markRiderStampArtworkSourceSelection
                                                            "
                                                        />
                                                        <CockpitRiderArtworkThumbnail
                                                            :source="
                                                                option.value
                                                            "
                                                            :image-url="
                                                                option.imageUrl
                                                            "
                                                            :title="
                                                                option.title
                                                            "
                                                            :description="
                                                                option.description
                                                            "
                                                            :resolving="
                                                                option.resolving
                                                            "
                                                        />
                                                        <span
                                                            v-if="
                                                                riderStampArtworkSource ===
                                                                option.value
                                                            "
                                                            class="absolute right-1.5 top-1.5 grid size-4 place-items-center rounded-full bg-sky-600 text-[9px] font-black text-white shadow-sm dark:bg-sky-400 dark:text-slate-950"
                                                            aria-hidden="true"
                                                            data-testid="cockpit-quick-generate-rider-artwork-selected"
                                                        >
                                                            ✓
                                                        </span>
                                                        <span
                                                            :id="`rider-stamp-artwork-tooltip-${option.value}`"
                                                            role="tooltip"
                                                            class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-950 px-2 py-1 text-[10px] font-semibold text-white opacity-0 shadow-lg transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 dark:bg-white dark:text-slate-950"
                                                        >
                                                            {{ option.label }}
                                                        </span>
                                                    </label>
                                                </div>
                                            </fieldset>
                                            <CockpitRiderArtworkInspector
                                                class="static w-full"
                                                data-testid="cockpit-quick-generate-rider-artwork-inspector"
                                                :artwork-source="
                                                    riderStampArtworkSource
                                                "
                                                :fit="riderStampFit"
                                                :position="riderStampPosition"
                                                :preview-document="
                                                    riderCanvasArtworkDocument
                                                "
                                                :url-artwork-resolving="
                                                    riderUrlArtworkResolving
                                                "
                                                :url-artwork-message="
                                                    riderUrlArtworkMessage
                                                "
                                            />
                                        </div>
                                        <fieldset
                                            v-if="
                                                riderStampArtworkSource ===
                                                    'url' ||
                                                riderStampArtworkSource ===
                                                    'splash'
                                            "
                                            class="grid gap-2"
                                            data-testid="cockpit-quick-generate-rider-stamp-artwork-treatment"
                                        >
                                            <legend
                                                class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                            >
                                                Treatment
                                            </legend>
                                            <div class="flex flex-wrap gap-1">
                                                <label
                                                    v-for="option in [
                                                        {
                                                            value: 'automatic',
                                                            label: 'Automatic',
                                                        },
                                                        {
                                                            value: 'artwork',
                                                            label: 'Artwork',
                                                        },
                                                        {
                                                            value: 'text',
                                                            label: 'Text',
                                                        },
                                                    ]"
                                                    :key="option.value"
                                                    class="cursor-pointer rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                    :class="
                                                        riderStampArtworkTreatment ===
                                                        option.value
                                                            ? 'border-sky-600 bg-sky-600 text-white'
                                                            : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                                                    "
                                                >
                                                    <input
                                                        v-model="
                                                            riderStampArtworkTreatment
                                                        "
                                                        type="radio"
                                                        class="sr-only"
                                                        name="rider-stamp-artwork-treatment"
                                                        :value="option.value"
                                                        :disabled="processing"
                                                    />
                                                    {{ option.label }}
                                                </label>
                                            </div>
                                        </fieldset>
                                        <fieldset
                                            class="grid gap-2"
                                            data-testid="cockpit-quick-generate-rider-stamp-copy-source"
                                        >
                                            <legend
                                                class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                            >
                                                Stamp Copy
                                            </legend>
                                            <div
                                                class="grid grid-cols-3 gap-1.5 sm:grid-cols-6 lg:grid-cols-3"
                                            >
                                                <label
                                                    v-for="option in [
                                                        {
                                                            value: 'automatic',
                                                            label: 'Automatic',
                                                        },
                                                        {
                                                            value: 'message',
                                                            label: 'Message',
                                                        },
                                                        {
                                                            value: 'url',
                                                            label: 'Link',
                                                        },
                                                        {
                                                            value: 'splash',
                                                            label: 'Splash',
                                                        },
                                                        {
                                                            value: 'custom',
                                                            label: 'Custom',
                                                        },
                                                        {
                                                            value: 'none',
                                                            label: 'None',
                                                        },
                                                    ]"
                                                    :key="option.value"
                                                    class="cursor-pointer rounded-lg border px-2 py-1.5 text-center text-[11px] font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                    :class="[
                                                        riderStampCopySource ===
                                                        option.value
                                                            ? 'border-sky-600 bg-sky-600 text-white'
                                                            : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
                                                        (option.value ===
                                                            'message' &&
                                                            purpose.trim() ===
                                                                '') ||
                                                        (option.value ===
                                                            'url' &&
                                                            riderUrl.trim() ===
                                                                '') ||
                                                        (option.value ===
                                                            'splash' &&
                                                            riderSplash.trim() ===
                                                                '')
                                                            ? 'cursor-not-allowed opacity-45'
                                                            : '',
                                                    ]"
                                                >
                                                    <input
                                                        v-model="
                                                            riderStampCopySource
                                                        "
                                                        type="radio"
                                                        class="sr-only"
                                                        name="rider-stamp-copy-source"
                                                        :value="option.value"
                                                        :disabled="
                                                            processing ||
                                                            (option.value ===
                                                                'message' &&
                                                                purpose.trim() ===
                                                                    '') ||
                                                            (option.value ===
                                                                'url' &&
                                                                riderUrl.trim() ===
                                                                    '') ||
                                                            (option.value ===
                                                                'splash' &&
                                                                riderSplash.trim() ===
                                                                    '')
                                                        "
                                                    />
                                                    {{ option.label }}
                                                </label>
                                            </div>
                                        </fieldset>
                                        <div
                                            class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900/50"
                                        >
                                            <fieldset
                                                class="grid gap-2"
                                                data-testid="cockpit-quick-generate-rider-stamp-fit"
                                            >
                                                <legend
                                                    class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                                >
                                                    Fit
                                                </legend>
                                                <div
                                                    class="grid grid-cols-2 gap-1"
                                                >
                                                    <label
                                                        v-for="option in [
                                                            {
                                                                value: 'cover',
                                                                label: 'Cover',
                                                            },
                                                            {
                                                                value: 'contain',
                                                                label: 'Contain',
                                                            },
                                                        ]"
                                                        :key="option.value"
                                                        class="cursor-pointer rounded-lg border px-2.5 py-1.5 text-center text-xs font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                        :class="
                                                            riderStampFit ===
                                                            option.value
                                                                ? 'border-sky-600 bg-sky-600 text-white'
                                                                : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300'
                                                        "
                                                    >
                                                        <input
                                                            v-model="
                                                                riderStampFit
                                                            "
                                                            type="radio"
                                                            class="sr-only"
                                                            name="rider-stamp-fit"
                                                            :value="
                                                                option.value
                                                            "
                                                            :data-testid="`cockpit-quick-generate-rider-stamp-fit-${option.value}`"
                                                            :disabled="
                                                                processing
                                                            "
                                                        />
                                                        {{ option.label }}
                                                    </label>
                                                </div>
                                            </fieldset>
                                            <fieldset
                                                class="grid gap-2"
                                                data-testid="cockpit-quick-generate-rider-stamp-position"
                                            >
                                                <legend
                                                    class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                                >
                                                    Position
                                                </legend>
                                                <div
                                                    class="grid grid-cols-5 gap-1"
                                                >
                                                    <label
                                                        v-for="option in [
                                                            {
                                                                value: 'top',
                                                                label: 'Top',
                                                            },
                                                            {
                                                                value: 'left',
                                                                label: 'Left',
                                                            },
                                                            {
                                                                value: 'center',
                                                                label: 'Center',
                                                            },
                                                            {
                                                                value: 'right',
                                                                label: 'Right',
                                                            },
                                                            {
                                                                value: 'bottom',
                                                                label: 'Bottom',
                                                            },
                                                        ]"
                                                        :key="option.value"
                                                        class="cursor-pointer rounded-lg border px-1 py-1.5 text-center text-[10px] font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                        :class="
                                                            riderStampPosition ===
                                                            option.value
                                                                ? 'border-sky-600 bg-sky-600 text-white'
                                                                : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300'
                                                        "
                                                    >
                                                        <input
                                                            v-model="
                                                                riderStampPosition
                                                            "
                                                            type="radio"
                                                            class="sr-only"
                                                            name="rider-stamp-position"
                                                            :value="
                                                                option.value
                                                            "
                                                            :disabled="
                                                                processing
                                                            "
                                                        />
                                                        {{ option.label }}
                                                    </label>
                                                </div>
                                            </fieldset>
                                            <fieldset
                                                class="grid gap-2"
                                                data-testid="cockpit-quick-generate-rider-stamp-theme"
                                            >
                                                <legend
                                                    class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                                >
                                                    Theme
                                                </legend>
                                                <div
                                                    class="grid grid-cols-3 gap-1"
                                                >
                                                    <label
                                                        v-for="option in [
                                                            {
                                                                value: 'automatic',
                                                                label: 'Automatic',
                                                            },
                                                            {
                                                                value: 'light',
                                                                label: 'Light',
                                                            },
                                                            {
                                                                value: 'dark',
                                                                label: 'Dark',
                                                            },
                                                        ]"
                                                        :key="option.value"
                                                        class="cursor-pointer rounded-lg border px-1.5 py-1.5 text-center text-[11px] font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                        :class="
                                                            riderStampTheme ===
                                                            option.value
                                                                ? 'border-sky-600 bg-sky-600 text-white'
                                                                : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300'
                                                        "
                                                    >
                                                        <input
                                                            v-model="
                                                                riderStampTheme
                                                            "
                                                            type="radio"
                                                            class="sr-only"
                                                            name="rider-stamp-theme"
                                                            :value="
                                                                option.value
                                                            "
                                                            :disabled="
                                                                processing
                                                            "
                                                        />
                                                        {{ option.label }}
                                                    </label>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <details
                                            class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                                            data-testid="cockpit-quick-generate-rider-stamp-more-options"
                                        >
                                            <summary
                                                class="cursor-pointer text-xs font-semibold text-sky-950 dark:text-sky-100"
                                            >
                                                More Stamp Options
                                            </summary>
                                            <div class="mt-4 grid gap-3">
                                                <label
                                                    class="grid min-w-0 gap-1 text-xs font-medium text-sky-950 sm:col-span-2 dark:text-sky-100"
                                                >
                                                    Front Title
                                                    <input
                                                        v-model="
                                                            riderStampTitle
                                                        "
                                                        type="text"
                                                        maxlength="120"
                                                        class="w-full min-w-0 rounded-xl border border-sky-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-sky-900/60 dark:bg-slate-900 dark:text-slate-50"
                                                        data-testid="cockpit-quick-generate-rider-stamp-title"
                                                        :disabled="processing"
                                                        placeholder="Use the selected Rider title"
                                                    />
                                                </label>
                                                <label
                                                    class="grid min-w-0 gap-1 text-xs font-medium text-sky-950 sm:col-span-2 dark:text-sky-100"
                                                >
                                                    Front Subtitle
                                                    <input
                                                        v-model="
                                                            riderStampDescription
                                                        "
                                                        type="text"
                                                        maxlength="240"
                                                        class="w-full min-w-0 rounded-xl border border-sky-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-sky-900/60 dark:bg-slate-900 dark:text-slate-50"
                                                        data-testid="cockpit-quick-generate-rider-stamp-description"
                                                        :disabled="processing"
                                                        placeholder="Use the selected Rider description"
                                                    />
                                                </label>
                                                <label
                                                    class="flex items-center gap-2 rounded-xl border border-sky-200 bg-white px-3 py-2 text-xs font-medium text-sky-950 dark:border-sky-900/60 dark:bg-slate-950 dark:text-sky-100"
                                                >
                                                    <input
                                                        v-model="
                                                            riderStampShowLogo
                                                        "
                                                        type="checkbox"
                                                        class="rounded border-sky-300"
                                                        :disabled="processing"
                                                    />
                                                    Show x-change Logo
                                                </label>
                                                <label
                                                    class="flex items-center gap-2 rounded-xl border border-sky-200 bg-white px-3 py-2 text-xs font-medium text-sky-950 dark:border-sky-900/60 dark:bg-slate-950 dark:text-sky-100"
                                                >
                                                    <input
                                                        v-model="
                                                            riderStampShowTagline
                                                        "
                                                        type="checkbox"
                                                        class="rounded border-sky-300"
                                                        :disabled="processing"
                                                    />
                                                    Show Tagline
                                                </label>
                                                <fieldset
                                                    class="grid gap-2"
                                                    data-testid="cockpit-quick-generate-rider-stamp-claim-marker"
                                                >
                                                    <legend
                                                        class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                                    >
                                                        Claim Marker
                                                    </legend>
                                                    <div
                                                        class="grid grid-cols-4 gap-1"
                                                    >
                                                        <label
                                                            v-for="option in [
                                                                {
                                                                    value: 'qr',
                                                                    label: 'QR',
                                                                },
                                                                {
                                                                    value: 'code',
                                                                    label: 'Code',
                                                                },
                                                                {
                                                                    value: 'both',
                                                                    label: 'Both',
                                                                },
                                                                {
                                                                    value: 'none',
                                                                    label: 'None',
                                                                },
                                                            ]"
                                                            :key="option.value"
                                                            class="cursor-pointer rounded-lg border px-1.5 py-1.5 text-center text-[11px] font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                            :class="
                                                                riderStampClaimMarker ===
                                                                option.value
                                                                    ? 'border-sky-600 bg-sky-600 text-white'
                                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                                                            "
                                                        >
                                                            <input
                                                                v-model="
                                                                    riderStampClaimMarker
                                                                "
                                                                type="radio"
                                                                class="sr-only"
                                                                name="rider-stamp-claim-marker"
                                                                :value="
                                                                    option.value
                                                                "
                                                                :disabled="
                                                                    processing
                                                                "
                                                            />
                                                            {{ option.label }}
                                                        </label>
                                                    </div>
                                                </fieldset>
                                                <fieldset
                                                    v-if="
                                                        riderStampClaimMarker !==
                                                        'none'
                                                    "
                                                    class="grid gap-2"
                                                    data-testid="cockpit-quick-generate-rider-stamp-claim-marker-position"
                                                >
                                                    <legend
                                                        class="text-xs font-semibold text-sky-950 dark:text-sky-100"
                                                    >
                                                        Marker Position
                                                    </legend>
                                                    <div
                                                        class="grid grid-cols-2 gap-1"
                                                    >
                                                        <label
                                                            v-for="option in [
                                                                {
                                                                    value: 'top_left',
                                                                    label: 'Top Left',
                                                                },
                                                                {
                                                                    value: 'top_right',
                                                                    label: 'Top Right',
                                                                },
                                                                {
                                                                    value: 'bottom_left',
                                                                    label: 'Bottom Left',
                                                                },
                                                                {
                                                                    value: 'bottom_right',
                                                                    label: 'Bottom Right',
                                                                },
                                                            ]"
                                                            :key="option.value"
                                                            class="cursor-pointer rounded-lg border px-2 py-1.5 text-center text-[11px] font-semibold transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-sky-600"
                                                            :class="
                                                                riderStampClaimMarkerPosition ===
                                                                option.value
                                                                    ? 'border-sky-600 bg-sky-600 text-white'
                                                                    : 'border-slate-200 bg-white text-slate-600 hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'
                                                            "
                                                        >
                                                            <input
                                                                v-model="
                                                                    riderStampClaimMarkerPosition
                                                                "
                                                                type="radio"
                                                                class="sr-only"
                                                                name="rider-stamp-claim-marker-position"
                                                                :value="
                                                                    option.value
                                                                "
                                                                :disabled="
                                                                    processing
                                                                "
                                                            />
                                                            {{ option.label }}
                                                        </label>
                                                    </div>
                                                </fieldset>
                                                <label
                                                    class="grid min-w-0 gap-1 text-xs font-medium text-sky-950 dark:text-sky-100"
                                                >
                                                    <span
                                                        class="flex items-center justify-between gap-3"
                                                    >
                                                        <span>Contrast</span>
                                                        <span
                                                            >{{
                                                                riderStampScrim
                                                            }}%</span
                                                        >
                                                    </span>
                                                    <input
                                                        v-model="
                                                            riderStampScrim
                                                        "
                                                        type="range"
                                                        min="0"
                                                        max="100"
                                                        step="1"
                                                        data-testid="cockpit-quick-generate-rider-stamp-scrim"
                                                        :disabled="processing"
                                                    />
                                                </label>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </CockpitRiderEditorDisclosure>
                        </div>
                    </Teleport>
                    <details
                        class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900/50"
                        data-testid="cockpit-quick-generate-rider-behavior"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    Rider Behavior
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400"
                                >
                                    Timing, claim navigation, and advanced
                                    Splash handling.
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-slate-200/80 px-2 py-1 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                Details
                            </span>
                        </summary>
                        <div
                            class="mt-4 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2 dark:border-slate-800"
                        >
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Link Preset
                                <select
                                    v-model="riderUrlPreset"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
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
                                    class="text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                    data-testid="cockpit-quick-generate-rider-url-preset-helper"
                                >
                                    {{ selectedRiderUrlPreset.helper }}
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Redirect Delay (Seconds)
                                <input
                                    v-model="riderRedirectTimeout"
                                    type="number"
                                    min="0"
                                    max="300"
                                    step="1"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-rider-redirect-timeout"
                                    :disabled="processing"
                                />
                            </label>
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Splash Headline
                                <input
                                    v-model="riderSplashHeadline"
                                    type="text"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-rider-splash-headline"
                                    :disabled="processing"
                                />
                            </label>
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Splash Button Label
                                <input
                                    v-model="riderSplashCtaText"
                                    type="text"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-rider-splash-cta-text"
                                    :disabled="processing"
                                />
                            </label>
                            <label
                                class="grid min-w-0 content-start gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                Splash Duration (Seconds)
                                <input
                                    v-model="riderSplashTimeout"
                                    type="number"
                                    min="0"
                                    max="60"
                                    step="1"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-rider-splash-timeout"
                                    :disabled="processing"
                                />
                            </label>
                            <label
                                v-if="riderSplashFormat === 'html'"
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                HTML Profile
                                <input
                                    v-model="riderSplashMetaProfile"
                                    type="text"
                                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:text-slate-50"
                                    data-testid="cockpit-quick-generate-rider-splash-profile"
                                    :disabled="processing"
                                />
                            </label>
                            <label
                                v-if="riderSplashFormat === 'html'"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 text-xs font-medium text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                            >
                                <input
                                    v-model="riderSplashMetaSanitized"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                    :disabled="processing"
                                />
                                Sanitize Custom HTML
                            </label>
                        </div>
                    </details>
                </section>

                <details
                    id="quick-generate-contract-feedback"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-sm font-bold text-rose-700 dark:bg-rose-900/60 dark:text-rose-200"
                            >5</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Status Updates
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Choose optional destinations for claim updates.
                                Issuing the Pay Code does not send a message.
                            </p>
                        </div>
                    </summary>
                    <div
                        class="mt-4 grid gap-3"
                        data-testid="cockpit-quick-generate-feedback-channels"
                    >
                        <div
                            class="grid gap-2 rounded-xl border border-violet-100 bg-violet-50 p-3 text-xs text-violet-950 dark:border-violet-900/60 dark:bg-violet-950/40 dark:text-violet-100"
                        >
                            <p class="font-semibold">
                                Suggested Update Destinations
                            </p>
                            <p class="text-violet-800 dark:text-violet-200">
                                Use your saved contact details or enter
                                different destinations below.
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
                                            >Use My Email</span
                                        >
                                        <span
                                            class="block text-[11px] text-violet-700 dark:text-violet-200"
                                        >
                                            {{
                                                capabilityUnavailable(
                                                    'feedback.email',
                                                )
                                                    ? capabilityReason(
                                                          'feedback.email',
                                                          'Email delivery is unavailable.',
                                                      )
                                                    : defaultFeedbackEmail ||
                                                      'No Saved Email'
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
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'feedback.sms',
                                                feedbackMobileEnabled,
                                            )
                                        "
                                        @change="
                                            toggleFeedbackChannel(
                                                'mobile',
                                                feedbackMobileEnabled,
                                            )
                                        "
                                    />
                                    <span>
                                        <span class="font-semibold"
                                            >Use My Mobile</span
                                        >
                                        <span
                                            class="block text-[11px] text-violet-700 dark:text-violet-200"
                                        >
                                            {{
                                                capabilityUnavailable(
                                                    'feedback.sms',
                                                )
                                                    ? capabilityReason(
                                                          'feedback.sms',
                                                          'SMS delivery is unavailable.',
                                                      )
                                                    : defaultFeedbackMobile ||
                                                      'No Saved Mobile Number'
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
                                        :disabled="
                                            capabilitySelectionDisabled(
                                                'feedback.webhook',
                                                feedbackWebhookEnabled,
                                            )
                                        "
                                        @change="
                                            toggleFeedbackChannel(
                                                'webhook',
                                                feedbackWebhookEnabled,
                                            )
                                        "
                                    />
                                    <span class="min-w-0">
                                        <span class="font-semibold"
                                            >Use My Webhook</span
                                        >
                                        <span
                                            class="block break-all text-[11px] text-violet-700 dark:text-violet-200"
                                        >
                                            {{
                                                defaultFeedbackWebhook ||
                                                'No Saved Webhook'
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
                                Update Email
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
                                Update Mobile
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
                                    Saved As:
                                    {{ normalizedFeedbackMobile }}
                                </span>
                            </label>
                            <label
                                class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 lg:col-span-2 dark:text-slate-300"
                            >
                                Update Webhook
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
                                    Webhook delivery must be configured
                                    separately.
                                </span>
                            </label>
                        </div>
                    </div>
                </details>

                <details
                    v-if="!reusableBalance"
                    id="quick-generate-contract-slices"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-100 text-sm font-bold text-cyan-700 dark:bg-cyan-900/60 dark:text-cyan-200"
                            >6</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Claim Schedule And Availability
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Choose whether the value is claimed once, in
                                portions, or on a schedule.
                            </p>
                        </div>
                    </summary>
                    <div class="mt-4 grid gap-4">
                        <div
                            class="grid grid-cols-1 gap-2 lg:grid-cols-4"
                            data-testid="cockpit-quick-generate-slice-mode-cards"
                        >
                            <button
                                v-for="option in [
                                    {
                                        value: 'whole',
                                        label: 'Whole Amount',
                                        helper: 'One claim uses the entire Pay Code.',
                                    },
                                    {
                                        value: 'fixed',
                                        label: 'Equal Portions',
                                        helper: 'Split the value into equal claims.',
                                    },
                                    {
                                        value: 'open',
                                        label: 'Flexible Amounts',
                                        helper: 'Let the recipient choose each partial amount.',
                                    },
                                    {
                                        value: 'named',
                                        label: 'Scheduled Portions',
                                        helper: 'Set each portion’s value, label, and dates.',
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
                                :disabled="
                                    processing ||
                                    (isAccountFundingClaim &&
                                        option.value !== 'whole')
                                "
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
                                    Equal Portions
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
                                        class="text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        Number of equal claims.
                                    </span>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Maximum Claims
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
                                        class="text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        Available only for Flexible Amounts.
                                    </span>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Minimum Claim Amount
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
                                            class="inline-flex w-24 min-w-0 items-center justify-center rounded-r-xl border border-l-0 border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold uppercase text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                            data-testid="cockpit-quick-generate-min-withdrawal-currency"
                                        >
                                            {{ currency || 'PHP' }}
                                        </span>
                                    </div>
                                    <span
                                        class="text-[11px] font-normal leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        Smallest permitted claim amount, shown
                                        in
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
                                        >Maximum Claims</span
                                    >
                                    <span class="block">
                                        {{ maxValidClaimCount }}
                                    </span>
                                </p>
                            </div>
                            <p
                                class="text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                            >
                                The selected plan controls how much may be
                                claimed and how many successful claims are
                                allowed.
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
                                        Claim Plan
                                    </p>
                                    <p
                                        class="mt-1 text-[11px] text-cyan-800 dark:text-cyan-200"
                                    >
                                        Review or customize the portions
                                        available to the recipient.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-full border border-cyan-200 bg-white px-3 py-1.5 text-xs font-semibold text-cyan-800 shadow-sm hover:bg-cyan-50 disabled:opacity-50 dark:border-cyan-800 dark:bg-slate-950 dark:text-cyan-100"
                                    data-testid="cockpit-quick-generate-add-named-slice"
                                    :disabled="addSliceDisabled"
                                    @click="addNamedClaimSlice"
                                >
                                    Add Portion
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
                                            Label
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
                                            Category
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
                                            Available On
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
                                            Expires On
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
                                        Scheduled Total
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
                                        Unassigned Value
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
                                        Claim Mode
                                    </p>
                                    <p
                                        class="mt-1 font-semibold text-cyan-800 dark:text-cyan-200"
                                    >
                                        {{
                                            sliceMode === 'whole'
                                                ? 'Whole Amount'
                                                : sliceMode === 'fixed'
                                                  ? 'Equal Portions'
                                                  : sliceMode === 'open'
                                                    ? 'Flexible Amounts'
                                                    : 'Scheduled Portions'
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
                                <p class="font-semibold">Availability Window</p>
                                <p class="mt-1 text-[11px]">
                                    Starts:
                                    {{ startsAt || 'Immediately' }}
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">
                                    Effective Expiration
                                </p>
                                <p class="mt-1 text-[11px]">
                                    {{ effectiveExpiry.label }}
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Timing Priority</p>
                                <p class="mt-1 text-[11px]">
                                    Exact expiration, then custom duration, then
                                    preset.
                                </p>
                            </div>
                        </div>
                    </div>
                </details>

                <details
                    v-if="!reusableBalance"
                    id="quick-generate-contract-execution"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    data-testid="cockpit-quick-generate-advanced-contract-section"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center gap-3"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700 dark:bg-violet-900/60 dark:text-violet-200"
                            >7</span
                        >
                        <div>
                            <h4
                                class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Advanced Settlement Settings
                            </h4>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Use only for specialized collection, settlement,
                                or automated claim flows.
                            </p>
                        </div>
                    </summary>

                    <div class="mt-4 grid gap-3">
                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Payment Rules
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Pay Code Type
                                    <select
                                        v-model="voucherType"
                                        class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                                        data-testid="cockpit-quick-generate-voucher-type"
                                        :disabled="processing"
                                    >
                                        <option value="redeemable">
                                            Redeemable
                                        </option>
                                        <option value="payable">Payable</option>
                                        <option value="settlement">
                                            Settlement
                                        </option>
                                    </select>
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Target Value
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
                                    Minimum Payment
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
                                    Maximum Payment
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
                                    Allow Overpayment
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
                                    Close When Fully Paid
                                </label>
                            </div>
                        </details>

                        <details
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60"
                        >
                            <summary
                                class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                Automated Claim Flow
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
                                    Use Automated Claim Flow
                                </label>
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Flow Version
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
                                    Handler
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
                                    Processing Mode
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
                                    Processing Steps
                                    <input
                                        v-model="executionPipeline"
                                        type="text"
                                        placeholder="Step keys, separated by commas"
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
                                    Fallback Action
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
                                    Visible To
                                    <input
                                        v-model="executionVisibility"
                                        type="text"
                                        placeholder="Audience keys, separated by commas"
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
                                    Operator Note
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
                                Issuance References
                            </summary>
                            <div
                                class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-3"
                            >
                                <label
                                    class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    Flow Name
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
                                    Issuer Reference
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
                                    Collection Account Reference
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
                </details>
            </div>
        </details>

        <section
            v-if="selectedUnavailableCapabilities.length > 0"
            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
            data-testid="cockpit-quick-generate-capability-warning"
        >
            <p class="font-semibold">This design needs unavailable services</p>
            <ul class="mt-2 space-y-1">
                <li
                    v-for="capability in selectedUnavailableCapabilities"
                    :key="capability.key"
                >
                    {{ capability.label }} — {{ capability.reason }} Remove it
                    before issuing.
                </li>
            </ul>
        </section>

        <details
            class="mt-5 rounded-2xl border border-slate-800 bg-slate-950 p-4 text-xs text-slate-300 shadow-sm"
            data-testid="cockpit-quick-generate-engineering-preview"
        >
            <summary
                class="cursor-pointer text-sm font-semibold text-slate-100"
            >
                Engineering Preview
            </summary>
            <p class="mt-2 text-xs leading-5 text-slate-400">
                Sanitized Pay Code instructions. Secrets and execution internals
                are excluded.
            </p>
            <pre
                class="mt-3 max-h-96 overflow-auto rounded-xl border border-slate-800 bg-slate-950 p-3 text-[11px] leading-5 text-slate-200"
                data-testid="cockpit-quick-generate-engineering-preview-json"
                >{{ sanitizedInstructionPayloadJson }}</pre>
        </details>

        <p class="mt-3 text-xs leading-5 text-slate-600 dark:text-slate-300">
            {{ lastMessage }}
        </p>

        <section
            v-if="submissionErrors.length > 0"
            class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900 dark:border-rose-900/70 dark:bg-rose-950/30 dark:text-rose-100"
            data-testid="cockpit-quick-generate-submission-errors"
        >
            <p class="font-semibold">{{ submissionErrorHeading }}</p>
            <ul class="mt-2 space-y-1">
                <li
                    v-for="error in submissionErrors"
                    :key="`${error.field}:${error.message}`"
                    class="leading-5"
                    data-testid="cockpit-quick-generate-submission-error"
                >
                    <span class="font-semibold">{{ error.field }}:</span>
                    {{ error.message }}
                </li>
            </ul>
        </section>

        <div
            v-if="lastResponse"
            class="mt-4 rounded-2xl border border-emerald-200 bg-white p-4 text-xs text-slate-700 shadow-sm dark:border-emerald-900 dark:bg-slate-950 dark:text-slate-300"
            data-testid="cockpit-quick-generate-result-panel"
        >
            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/30"
                data-testid="cockpit-quick-generate-productized-result-card"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700 dark:text-emerald-300"
                        >
                            {{ generationStatusLabel }}
                        </p>
                        <h3
                            class="mt-2 text-2xl font-semibold tracking-tight text-emerald-950 dark:text-emerald-50"
                        >
                            Pay Code issued
                        </h3>
                        <p
                            class="mt-2 max-w-2xl text-sm leading-6 text-emerald-800 dark:text-emerald-200"
                        >
                            <template v-if="isAccountFundingResult">
                                This Pay Code is reserved for Account Funding.
                                Claiming it adds the whole amount to one
                                authenticated Account without a provider payout.
                            </template>
                            <template v-else>
                                Generated through the existing x-change issuance
                                handoff. Cockpit presents the result, claim URL,
                                preflight summaries, and activity status without
                                sending feedback or executing the claim from
                                this UI.
                            </template>
                        </p>
                    </div>
                    <span
                        class="w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
                    >
                        {{ resultCode ?? 'issued' }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <div
                        v-for="item in generationSummary"
                        :key="item.label"
                        class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/70 dark:bg-slate-950/60"
                    >
                        <dt
                            class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                        >
                            {{ item.label }}
                        </dt>
                        <dd
                            class="mt-1 break-words font-semibold text-emerald-950 dark:text-emerald-50"
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
                    class="mt-4 flex flex-col gap-3 rounded-xl border border-emerald-200 bg-white/80 p-3 lg:flex-row lg:items-center lg:justify-between dark:border-emerald-900/70 dark:bg-slate-950/60"
                    data-testid="cockpit-quick-generate-primary-next-actions"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                        >
                            Primary next step
                        </p>
                        <p
                            class="mt-1 text-sm font-semibold text-emerald-950 dark:text-emerald-50"
                        >
                            {{
                                isAccountFundingResult
                                    ? 'Copy the Pay Code or open Account Funding'
                                    : 'Copy or inspect the beneficiary claim URL'
                            }}
                        </p>
                        <p
                            v-if="
                                !isAccountFundingResult && beneficiaryClaimUrl
                            "
                            class="mt-1 break-all font-mono text-[11px] text-emerald-800 dark:text-emerald-200"
                        >
                            {{ beneficiaryClaimUrl }}
                        </p>
                        <p
                            v-if="
                                !isAccountFundingResult &&
                                (beneficiaryClaimUrl || beneficiaryRedeemPath)
                            "
                            class="mt-2 inline-flex rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200"
                            data-testid="cockpit-quick-generate-primary-claim-route-source"
                        >
                            {{ beneficiaryClaimRouteLabel }}
                        </p>
                        <p
                            v-else-if="!isAccountFundingResult"
                            class="mt-1 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                        >
                            No beneficiary URL was returned. Use the generated
                            Pay Code detail for inspection.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <CockpitManualCopyButton
                            v-if="isAccountFundingResult && resultCode !== null"
                            :value="resultCode"
                            label="Copy Pay Code"
                            data-testid="cockpit-quick-generate-copy-funding-pay-code"
                        />
                        <a
                            v-if="
                                isAccountFundingResult &&
                                fundingWorkspaceUrl !== null
                            "
                            :href="fundingWorkspaceUrl"
                            class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-cyan-800 dark:bg-cyan-500 dark:text-cyan-950 dark:hover:bg-cyan-400"
                            data-testid="cockpit-quick-generate-open-account-funding"
                        >
                            Open Account Funding
                        </a>
                        <CockpitManualCopyButton
                            v-if="
                                !isAccountFundingResult && beneficiaryClaimUrl
                            "
                            :value="beneficiaryClaimUrl"
                            label="Copy claim URL"
                            data-testid="cockpit-quick-generate-primary-copy-control"
                        />
                        <a
                            v-if="
                                !isAccountFundingResult && beneficiaryClaimUrl
                            "
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

                <div
                    v-if="pricingPreflight || fundingPreflight"
                    class="mt-4 grid gap-3 lg:grid-cols-2"
                    data-testid="cockpit-quick-generate-primary-financial-readiness"
                >
                    <section
                        v-if="pricingPreflight"
                        class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/70 dark:bg-slate-950/60"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                                >
                                    Pricing summary
                                </p>
                                <p
                                    class="mt-1 text-xl font-semibold text-emerald-950 dark:text-emerald-50"
                                >
                                    {{
                                        displayValue(
                                            pricingPreflight.currency,
                                            'PHP',
                                        )
                                    }}
                                    {{
                                        displayValue(
                                            pricingPreflight.total,
                                            '0',
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200"
                            >
                                {{ displayValue(pricingPreflight.status) }}
                            </span>
                        </div>
                        <p
                            class="mt-2 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                        >
                            Base fee:
                            {{ displayValue(pricingPreflight.base_fee, '0') }} ·
                            Blocking:
                            {{
                                pricingPreflight.blocking === true
                                    ? 'yes'
                                    : 'no'
                            }}
                        </p>
                    </section>

                    <section
                        v-if="fundingPreflight"
                        class="rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/70 dark:bg-slate-950/60"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                                >
                                    Funding summary
                                </p>
                                <p
                                    class="mt-1 text-xl font-semibold text-emerald-950 dark:text-emerald-50"
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
                                            fundingPreflight.authoritative
                                                ?.balance,
                                            'not available',
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200"
                            >
                                {{ displayValue(fundingPreflight.status) }}
                            </span>
                        </div>
                        <p
                            class="mt-2 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                        >
                            Authority:
                            {{ displayValue(fundingPreflight.authority) }} ·
                            Sync:
                            {{ displayValue(fundingPreflight.sync_status) }}
                        </p>
                    </section>
                </div>

                <div
                    v-if="activityRuntime"
                    class="mt-4 rounded-xl border border-emerald-200 bg-white/80 p-3 dark:border-emerald-900/70 dark:bg-slate-950/60"
                    data-testid="cockpit-quick-generate-primary-handoff-status"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300"
                            >
                                Downstream handoff status
                            </p>
                            <p
                                class="mt-1 text-sm font-semibold text-emerald-950 dark:text-emerald-50"
                            >
                                Activity:
                                {{ displayValue(activityRuntime.status) }}
                            </p>
                        </div>
                        <span
                            class="w-fit rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200"
                        >
                            {{
                                activityRuntime.presentation_only === true
                                    ? 'presentation-only'
                                    : 'runtime'
                            }}
                        </span>
                    </div>

                    <dl class="mt-3 grid gap-2 md:grid-cols-3">
                        <div
                            v-for="handoff in downstreamHandoffSummary"
                            :key="handoff.label"
                            class="rounded-lg border border-emerald-100 bg-emerald-50/70 p-2 dark:border-emerald-900/70 dark:bg-emerald-950/30"
                        >
                            <dt
                                class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                {{ handoff.label }}
                            </dt>
                            <dd
                                class="mt-1 font-semibold text-emerald-950 dark:text-emerald-50"
                            >
                                {{ handoff.status }}
                            </dd>
                            <p
                                class="mt-1 text-[11px] leading-4 text-emerald-800 dark:text-emerald-200"
                            >
                                {{ handoff.detail }}
                            </p>
                        </div>
                    </dl>
                </div>
            </section>

            <details
                class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-quick-generate-supporting-result-details"
            >
                <summary
                    class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    Supporting result details
                </summary>
                <p
                    class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                >
                    Additional generated Pay Code links, campaign return
                    navigation, runtime metadata, and preflight details remain
                    available for inspection. The primary card above is the
                    operator workflow surface.
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
                    No automatic redirect is performed. The operator chooses
                    whether to refresh Cockpit data, open the generated Pay Code
                    detail, or copy the beneficiary URL for an approved external
                    distribution workflow.
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
                                Operator-safe full URL from the existing
                                issuance result. Showing this link does not send
                                SMS, email, webhook, or campaign delivery.
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
                                class="text-[11px] font-medium uppercase tracking-[0.18em] text-emerald-700/80 dark:text-emerald-200/80"
                            >
                                Full URL
                            </dt>
                            <dd
                                class="mt-1 break-all font-mono text-[12px] font-semibold text-emerald-950 dark:text-emerald-50"
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
                                class="text-[11px] font-medium uppercase tracking-[0.18em] text-emerald-700/80 dark:text-emerald-200/80"
                            >
                                Path
                            </dt>
                            <dd
                                class="mt-1 break-all font-mono text-[12px] font-semibold text-emerald-950 dark:text-emerald-50"
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
                                navigation only. Campaign state is not mutated
                                here.
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
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
                                Planning key
                            </dt>
                            <dd
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                {{
                                    displayValue(
                                        campaignAttribution?.planning_key,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
                                Execution
                            </dt>
                            <dd
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                {{
                                    displayValue(
                                        campaignAttribution?.execution_id,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
                                Campaign
                            </dt>
                            <dd
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                {{
                                    displayValue(
                                        campaignAttribution?.campaign_id,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
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
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
                                Recipient
                            </dt>
                            <dd
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                {{
                                    displayValue(
                                        campaignAttribution?.recipient_id,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
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
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
                                Template
                            </dt>
                            <dd
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                {{
                                    displayValue(
                                        campaignAttribution?.template_key,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-amber-700/80 dark:text-amber-200/80"
                            >
                                Amount
                            </dt>
                            <dd
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                {{
                                    displayValue(campaignAttribution?.currency)
                                }}
                                {{ displayValue(campaignAttribution?.amount) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="campaignReturnNavigationItems.length > 0"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/30"
                    data-testid="cockpit-quick-generate-campaign-return-navigation-panel"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="font-semibold text-amber-950 dark:text-amber-50"
                            >
                                Campaign return navigation
                            </p>
                            <p
                                class="mt-1 text-[11px] leading-4 text-amber-800 dark:text-amber-200"
                            >
                                Return to campaign-filtered Cockpit views after
                                generation. These links are read-only and do not
                                update campaign state.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800"
                        >
                            Read-only
                        </span>
                    </div>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <a
                            v-for="item in campaignReturnNavigationItems"
                            :key="
                                String(
                                    item.key ?? item.label ?? 'campaign-return',
                                )
                            "
                            :href="
                                item.enabled === true && item.href
                                    ? item.href
                                    : undefined
                            "
                            class="rounded-lg border border-amber-200 bg-white px-3 py-3 font-semibold text-amber-900 transition hover:border-amber-300 hover:bg-amber-100 aria-disabled:cursor-not-allowed aria-disabled:opacity-60 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100 dark:hover:bg-amber-900/40"
                            :aria-disabled="
                                item.enabled === true && item.href
                                    ? undefined
                                    : 'true'
                            "
                            :data-testid="`cockpit-quick-generate-campaign-return-link-${item.key}`"
                        >
                            <span class="block">{{ item.label }}</span>
                            <span
                                class="mt-1 block text-[11px] font-medium text-amber-700 dark:text-amber-200"
                            >
                                Campaign context preserved · {{ item.status }}
                            </span>
                        </a>
                    </div>
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
                                Read-only destinations for the generated Pay
                                Code. Automatic redirect:
                                {{
                                    postIssuanceNavigation?.auto_redirect ===
                                    true
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
                            :key="
                                String(
                                    item.key ?? item.label ?? 'post-issuance',
                                )
                            "
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
                                        activityRuntime.presentation_only ===
                                        true
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
                                    {{
                                        displayValue(
                                            pricingPreflight.total,
                                            '0',
                                        )
                                    }}
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
                                        displayValue(
                                            pricingPreflight.base_fee,
                                            '0',
                                        )
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
                                    {{
                                        displayValue(fundingPreflight.authority)
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">
                                    Available Funds
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
                                            fundingPreflight.authoritative
                                                ?.balance,
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
                                    {{
                                        displayValue(
                                            fundingPreflight.sync_status,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </details>
        </div>
    </form>
</template>
if (instructionBuilder !== null) { instructionBuilder.open = true; }
