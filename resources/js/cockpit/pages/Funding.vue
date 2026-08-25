<script setup lang="ts">
import { router, useForm, usePoll } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    CirclePlus,
    Eye,
    EyeOff,
    FileText,
    Landmark,
    QrCode,
    RefreshCw,
    Search,
    TicketCheck,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { store as storeVerificationCheck } from '@/routes/x-change/cockpit/funding/intents/verification-checks';
import { store as refreshFundingLiquidityRoute } from '@/routes/x-change/cockpit/funding/liquidity-refreshes';
import { store as claimPayCodeFundingRoute } from '@/routes/x-change/cockpit/funding/pay-code-claims';
import { store as inspectPayCodeFundingRoute } from '@/routes/x-change/cockpit/funding/pay-code-inspections';
import { approve as approveReconciliation } from '@/routes/x-change/cockpit/funding/reconciliations';
import { store as storeFundingRequest } from '@/routes/x-change/cockpit/funding/requests';
import { store as checkFundingRequestTransfer } from '@/routes/x-change/cockpit/funding/requests/transfer-checks';
import { store as runQrPhFundingSimulationRoute } from '@/routes/x-change/cockpit/funding/scenarios/qrph';
import { store as openStandingFundingAddressRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank';
import { store as checkStandingFundingHistoryRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank/history-checks';
import { approve as approveStandingFundingReceiptRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank/receipts';
import { store as storeReconciliationRequest } from '@/routes/x-change/cockpit/funding/suspense/reconciliation-requests';
import CockpitFundingActivity from '../components/CockpitFundingActivity.vue';
import CockpitFundingMethodPanel from '../components/CockpitFundingMethodPanel.vue';
import CockpitFundingQrAddress from '../components/CockpitFundingQrAddress.vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitFundingActivityItem,
    CockpitFundingPageProps,
    CockpitQrPhFundingSimulationResult,
    CockpitStandingFundingAddress,
    CockpitStandingFundingReceipt,
} from '../types';

const props = defineProps<CockpitFundingPageProps>();
const fundingBalanceValuesVisible = ref(false);
const clientFundsMetric = computed(() =>
    props.cockpit_header_read_model?.balances?.find(
        (balance) => balance.key === 'internal',
    ),
);
const clientFundsMinor = computed<number | null>(() => {
    const amount = clientFundsMetric.value?.amount_minor;

    return typeof amount === 'number' && Number.isFinite(amount)
        ? Math.round(amount)
        : null;
});
const clientFundsValue = computed<string>(() => {
    if (clientFundsMinor.value === null) {
        return 'Not available';
    }

    const formattedValue = clientFundsMetric.value?.value.trim();

    return formattedValue !== undefined && formattedValue !== ''
        ? formattedValue
        : 'Available';
});

function toggleFundingBalanceVisibility(): void {
    fundingBalanceValuesVisible.value = !fundingBalanceValuesVisible.value;
}

const fundingRequests = computed(
    () =>
        props.funding_requests ?? {
            schema: 'x-change.cockpit.account-funding-requests.v1',
            requests: [],
            notices: [],
            review_queue: [],
            bank_transfer: {
                enabled: false,
                provider: 'netbank',
                institution: 'NetBank',
                account_name: '',
                account_number: '',
                currency: 'PHP',
                minimum_requested_amount_minor: 10_000,
                minimum_requested_amount: '₱100.00',
                reserved_exact_amounts_enabled: false,
                minimum_adjustment: '₱3.17',
                maximum_adjustment: '₱5.37',
                instruction_valid_for_minutes: 10,
                full_expected_amount_is_credited: true as const,
                automatic_credit_window_minutes: 10,
                windows: [
                    {
                        value: 'recent' as const,
                        label: 'Last 10 minutes',
                        automatic: true,
                    },
                    {
                        value: 'last_hour' as const,
                        label: 'Last hour',
                        automatic: false,
                    },
                    {
                        value: 'today' as const,
                        label: 'Today',
                        automatic: false,
                    },
                ],
                sender_reference_authority: false as const,
            },
            controls: {
                attachments_enabled: false,
                evidence_authorizes_credit: false,
                maker_checker_required: true,
                reviewer: false,
                provider_payout_enabled: false,
            },
            redactions: {},
        },
);
const fundingActivity = computed(
    () =>
        props.funding_activity ?? {
            schema: 'x-change.cockpit.funding-activity.v1' as const,
            items: [],
            filters: [
                { key: 'all' as const, label: 'All' },
                { key: 'qr_ph' as const, label: 'QR Ph' },
                {
                    key: 'bank_transfer' as const,
                    label: 'Bank Transfer',
                },
                { key: 'pay_code' as const, label: 'Pay Code' },
                {
                    key: 'reviewed_value' as const,
                    label: 'Reviewed Value',
                },
            ],
            redactions: {
                payer_identity_exposed: false as const,
                provider_transaction_id_exposed: false as const,
                raw_evidence_exposed: false as const,
            },
        },
);
const activeReconciliationCase = ref<string | null>(null);
const activeApproval = ref<string | null>(null);
const activeVerificationCheck = ref<string | null>(null);
const simulationRunning = ref(false);
const simulationError = ref<string | null>(null);
const simulationResult = ref<CockpitQrPhFundingSimulationResult | null>(null);
const standingAddress = ref<CockpitStandingFundingAddress | null>(null);
const standingReceipts = ref<CockpitStandingFundingReceipt[]>([]);
const standingAddressLoading = ref(false);
const standingHistoryLoading = ref(false);
const standingHistoryCooldownSeconds = ref(0);
const standingAddressError = ref<string | null>(null);
const standingAddressErrorCode = ref<string | null>(null);
const standingAddressOperatorReference = ref<string | null>(null);
const activeStandingReceiptApproval = ref<string | null>(null);
const standingActionNotice = ref<string | null>(null);
const liquidityRefreshRunning = ref(false);
const liquidityRefreshError = ref<string | null>(null);
const fundingRequestAmount = ref('');
const fundingRequestAmountError = ref<string | null>(null);
const bankTransferAmountInput = ref<HTMLInputElement | null>(null);
const bankTransferQuickAmountsMinor = [
    10_000, 50_000, 100_000, 500_000, 1_000_000,
] as const;
const bankTransferQuickAmountFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});
const copiedFundingRequest = ref<string | null>(null);
const activeTransferCheck = ref<string | null>(null);
const bankTransferInstructionsOpen = ref(false);
const bankTransferInstructionReference = ref<string | null>(null);
const bankTransferInstructionDialog = ref<HTMLElement | null>(null);
const dismissedFundingRequestResult = ref(false);
let bankTransferDialogReturnFocus: HTMLElement | null = null;
type FundingWorkspaceMode =
    | 'self_top_up'
    | 'bank_transfer'
    | 'pay_code'
    | 'reviewed_value'
    | 'simulation';
const activeFundingMode = ref<FundingWorkspaceMode>(
    props.funding_workspace_mode ?? 'self_top_up',
);
const fundingQrMerchantProfile = computed(
    () =>
        props.funding_qr_merchant_profile ?? {
            name: 'Account Holder',
            city: 'Manila',
            merchant_category_code: '0000',
            merchant_name_template: '{name} - {city}',
            rendered_label: 'Account Holder - Manila',
            maximum_label_length: 25,
            uppercase: false,
            application_name: 'X-Change',
            template_options: [
                { value: '{name}' as const, label: 'Name' },
                {
                    value: '{name} - {city}' as const,
                    label: 'Name + City',
                },
                {
                    value: '{app_name} - {name}' as const,
                    label: 'x-change + Name',
                },
            ],
            category_options: [],
            presentation_only: true as const,
            controls_routing: false as const,
            controls_settlement: false as const,
        },
);
const primaryFundingWorkspaceModes = computed(() => [
    {
        key: 'self_top_up' as const,
        label: 'QR Ph',
    },
    {
        key: 'bank_transfer' as const,
        label: 'Bank Transfer',
    },
    {
        key: 'pay_code' as const,
        label: 'Pay Code',
    },
]);
const fundingActivityFilter = computed(() => {
    if (activeFundingMode.value === 'self_top_up') {
        return 'qr_ph' as const;
    }

    if (activeFundingMode.value === 'simulation') {
        return 'all' as const;
    }

    return activeFundingMode.value;
});
const processedFundingEvents = new Set<string>();
let realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let standingHistoryCooldownTimer: ReturnType<typeof setInterval> | null = null;
let lastProjectionRefreshAt = 0;
const activeSimulationStepIndex = ref(0);
const activeSimulationStep = computed(
    () =>
        simulationResult.value?.steps[activeSimulationStepIndex.value] ?? null,
);
const availableFundingProviders = computed(() =>
    props.funding_read_model.providers.filter(
        (provider) => provider.status === 'available',
    ),
);
const operationalFundingIntents = computed(() =>
    props.funding_read_model.intents.filter(
        (intent) => intent.provider !== 'qrph_simulator',
    ),
);
const canViewTreasuryControls = computed(
    () => props.funding_read_model.controls.can_view_treasury_controls === true,
);
const canRefreshProviderLiquidity = computed(
    () =>
        props.funding_read_model.controls.can_refresh_provider_liquidity ===
        true,
);
const canManageTreasuryReconciliation = computed(
    () =>
        props.funding_read_model.controls.can_manage_treasury_reconciliation ===
        true,
);
const treasuryPortfolio = computed(() => {
    const portfolio = props.funding_read_model.treasury_portfolio;

    return canViewTreasuryControls.value &&
        portfolio &&
        Array.isArray(portfolio.connections)
        ? portfolio
        : null;
});
const staleProviderLiquidity = computed(() =>
    treasuryPortfolio.value?.connections.find(
        (connection) =>
            connection.mode !== 'disabled' &&
            connection.provider_liquidity_is_stale,
    ),
);
const refreshableProviderLiquidity = computed(() =>
    treasuryPortfolio.value?.connections.find(
        (connection) => connection.mode !== 'disabled',
    ),
);
const hasOpenFundingIntents = computed(() =>
    operationalFundingIntents.value.some((intent) =>
        ['awaiting_funds', 'evidence_received', 'verifying'].includes(
            intent.status,
        ),
    ),
);
const hasOpenFundingWork = computed(
    () =>
        hasOpenFundingIntents.value ||
        fundingRequests.value.requests.some((request) =>
            [
                'submitted',
                'under_review',
                'needs_information',
                'awaiting_approval',
                'pay_code_issued',
            ].includes(request.status),
        ),
);
const fundingExceptionCount = computed(
    () =>
        props.funding_read_model.approval_queue.length +
        props.funding_read_model.suspense_cases.length +
        props.funding_read_model.recovery_holds.length,
);
const hasFundingExceptions = computed(() => fundingExceptionCount.value > 0);
const { start: startFundingPoll, stop: stopFundingPoll } = usePoll(
    Math.max(1000, props.funding_poll_interval ?? 5000),
    {
        only: [
            'cockpit_header_read_model',
            'funding_read_model',
            'funding_requests',
            'funding_activity',
            'funding_notice',
        ],
    },
    {
        autoStart: hasOpenFundingWork.value,
        mode: 'rest',
    },
);
const reconciliationForm = useForm({
    action: '',
});
const approvalForm = useForm({});
const verificationForm = useForm({});
const fundingRequestForm = useForm({
    funding_type: 'unspecified',
    requested_value_minor: 0,
    external_reference: '',
    transfer_window: 'recent' as 'recent' | 'last_hour' | 'today',
    occurred_on: '',
    requester_notes: '',
    evidence_document_type: 'supporting_document',
    evidence_document: null as File | null,
    idempotency_key: newIdempotencyKey(),
});
const bankTransferInstructions = computed(
    () => fundingRequests.value.bank_transfer,
);
const bankTransferRequests = computed(() =>
    fundingRequests.value.requests.filter(
        (request) => request.type === 'bank_transfer',
    ),
);
const selectedBankTransferRequest = computed(
    () =>
        bankTransferRequests.value.find(
            (request) =>
                request.reference === bankTransferInstructionReference.value,
        ) ?? null,
);
const payCodeInspectionForm = useForm({
    code: '',
});
const payCodeFundingClaimForm = useForm({
    inspection_token: props.pay_code_funding_preview?.inspection_token ?? '',
});
const payCodeFundingClaimError = computed(
    () =>
        (payCodeFundingClaimForm.errors as Record<string, string | undefined>)
            .pay_code_funding ?? null,
);
const payCodeFundingActionLabel = computed(() => {
    const amount = props.pay_code_funding_preview?.amount;

    return amount ? `Add ${amount} to my Account` : 'Add to my Account';
});
const submittedFundingRequest = computed(() => {
    if (dismissedFundingRequestResult.value) {
        return null;
    }

    const reference = props.funding_request_submitted_reference;

    return reference
        ? (fundingRequests.value.requests.find(
              (request) => request.reference === reference,
          ) ?? null)
        : null;
});
const fundingRequestButtonLabel = computed(() => {
    const amountMinor = amountToMinor(fundingRequestAmount.value);

    if (amountMinor === null) {
        return 'Request value review';
    }

    return `Request ${new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amountMinor / 100)}`;
});
type FundingProjectionChangedPayload = {
    schema: string;
    event_id: string;
    reason: string;
    occurred_at: string;
};
type FundingRequestChangedPayload = {
    schema: string;
    event_id: string;
    reason: string;
    request_reference: string;
    status: string;
    occurred_at: string;
};

if (props.funding_realtime?.enabled === true) {
    useEcho<FundingProjectionChangedPayload>(
        props.funding_realtime.channel,
        props.funding_realtime.event,
        (event) => {
            if (
                event.schema !== 'x-change.funding-projection-changed.v1' ||
                event.reason !== 'account_funding_settled' ||
                processedFundingEvents.has(event.event_id)
            ) {
                return;
            }

            processedFundingEvents.add(event.event_id);

            if (realtimeRefreshTimer !== null) {
                clearTimeout(realtimeRefreshTimer);
            }

            realtimeRefreshTimer = setTimeout(() => {
                refreshFundingProjections();
                realtimeRefreshTimer = null;
            }, 150);
        },
    );
    useEcho<FundingRequestChangedPayload>(
        props.funding_realtime.channel,
        props.funding_realtime.workflow_event,
        (event) => {
            if (
                event.schema !== 'x-change.funding-request-changed.v1' ||
                event.reason !== 'funding_request_changed' ||
                processedFundingEvents.has(event.event_id)
            ) {
                return;
            }

            processedFundingEvents.add(event.event_id);
            refreshFundingProjections();
        },
    );
}

onUnmounted(() => {
    if (realtimeRefreshTimer !== null) {
        clearTimeout(realtimeRefreshTimer);
    }

    if (standingHistoryCooldownTimer !== null) {
        clearInterval(standingHistoryCooldownTimer);
    }
});

onMounted(() => {
    if (props.standing_funding_address?.available === true) {
        void openStandingFundingAddress();
    }
});

watch(hasOpenFundingWork, (hasOpenWork) => {
    if (hasOpenWork) {
        startFundingPoll();

        return;
    }

    stopFundingPoll();
});

watch(
    submittedFundingRequest,
    (request) => {
        if (request?.type !== 'bank_transfer' || request.transfer === null) {
            return;
        }

        bankTransferInstructionReference.value = request.reference;
        bankTransferInstructionsOpen.value = true;
    },
    { immediate: true },
);

watch(bankTransferInstructionsOpen, async (isOpen) => {
    if (typeof document === 'undefined') {
        return;
    }

    if (isOpen) {
        bankTransferDialogReturnFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        await nextTick();
        bankTransferInstructionDialog.value?.focus();

        return;
    }

    bankTransferDialogReturnFocus?.focus();
    bankTransferDialogReturnFocus = null;
});

const summaryCards = computed(() => [
    {
        key: 'awaiting',
        label: 'Awaiting Funds',
        value: String(props.funding_read_model.summary.awaiting_funds),
        helper: 'Open Funding Intents waiting for authoritative settlement.',
        tone: 'text-sky-700 dark:text-sky-300',
    },
    {
        key: 'settled',
        label: 'Settled Funding',
        value: props.funding_read_model.summary.settled_funding,
        helper: 'Verified net funding posted to this Account.',
        tone: 'text-emerald-700 dark:text-emerald-300',
    },
    {
        key: 'suspense',
        label: 'Open Suspense',
        value: String(props.funding_read_model.summary.open_suspense),
        helper: 'Mismatched or ambiguous evidence requiring review.',
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        key: 'recovery',
        label: 'Recovery',
        value: props.funding_read_model.summary.recovery_outstanding,
        helper: 'Reversed funding still held against future Issuance Capacity.',
        tone: 'text-rose-700 dark:text-rose-300',
    },
]);

const treasuryPositionControl = computed(() => {
    const activeConnections = (
        treasuryPortfolio.value?.connections ?? []
    ).filter((connection) => connection.mode !== 'disabled');

    if (activeConnections.length === 0) {
        return 'No active provider connection';
    }

    const controlsNeedingReview = activeConnections.filter(
        (connection) => connection.control_status !== 'reconciled',
    );

    if (controlsNeedingReview.length === 0) {
        return 'Internal positions reconciled';
    }

    if (controlsNeedingReview.length === 1) {
        return controlStatusLabel(controlsNeedingReview[0].control_status);
    }

    return `${controlsNeedingReview.length} provider controls need review`;
});

const safeguards = [
    'Every credit is bound to an exact Funding Intent or an immutable Account Funding Address.',
    'A webhook stores evidence and wakes verification; it never credits an Account.',
    'Settlement is re-queried from the provider before exact net funding is posted.',
    'Mismatches enter suspense; operators cannot type an arbitrary credit amount.',
    'Reconciliation and compensation require separate maker and checker identities.',
];

function displayLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function controlStatusLabel(value: string): string {
    return value === 'reconciled'
        ? 'Internal positions reconciled'
        : displayLabel(value);
}

function displayTime(value?: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date);
}

function newIdempotencyKey(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `cockpit-funding-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function amountToMinor(value: string): number | null {
    const normalized = value.trim();

    if (!/^\d{1,10}(\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, decimal = ''] = normalized.split('.');
    const amountMinor = Number(whole) * 100 + Number(decimal.padEnd(2, '0'));

    return Number.isSafeInteger(amountMinor) && amountMinor > 0
        ? amountMinor
        : null;
}

function focusBankTransferAmount(): void {
    if (fundingRequestForm.funding_type !== 'bank_transfer') {
        return;
    }

    bankTransferAmountInput.value?.focus();
    bankTransferAmountInput.value?.select();
}

async function selectBankTransferQuickAmount(
    amountMinor: number,
): Promise<void> {
    fundingRequestAmount.value = (amountMinor / 100).toFixed(2);
    fundingRequestAmountError.value = null;
    fundingRequestForm.clearErrors('requested_value_minor');

    await nextTick();
    bankTransferAmountInput.value?.focus();
    bankTransferAmountInput.value?.select();
}

function submitFundingRequest(): void {
    fundingRequestForm.clearErrors();
    fundingRequestAmountError.value = null;
    const amountMinor = amountToMinor(fundingRequestAmount.value);
    const minimumAmountMinor =
        fundingRequestForm.funding_type === 'bank_transfer'
            ? bankTransferInstructions.value.minimum_requested_amount_minor
            : 1;

    if (amountMinor === null) {
        fundingRequestAmountError.value =
            'Enter the amount you are requesting.';
        focusBankTransferAmount();

        return;
    }

    if (amountMinor < minimumAmountMinor) {
        fundingRequestAmountError.value = `Minimum bank transfer amount is ${bankTransferInstructions.value.minimum_requested_amount}.`;
        focusBankTransferAmount();

        return;
    }

    fundingRequestForm.requested_value_minor = amountMinor;
    fundingRequestForm.post(storeFundingRequest(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            fundingRequestAmount.value = '';
            fundingRequestForm.reset(
                'external_reference',
                'occurred_on',
                'requester_notes',
                'evidence_document',
            );
            fundingRequestForm.requested_value_minor = 0;
            fundingRequestForm.idempotency_key = newIdempotencyKey();
        },
        onError: (errors) => {
            if (errors.requested_value_minor) {
                focusBankTransferAmount();
            }
        },
    });
}

function submitBankTransferRequest(): void {
    fundingRequestForm.funding_type = 'bank_transfer';
    submitFundingRequest();
}

function openBankTransferInstructions(reference: string): void {
    bankTransferInstructionReference.value = reference;
    bankTransferInstructionsOpen.value = true;
}

function closeBankTransferInstructions(): void {
    bankTransferInstructionsOpen.value = false;
}

function checkTransfer(reference: string): void {
    if (activeTransferCheck.value !== null) {
        return;
    }

    activeTransferCheck.value = reference;
    router.post(
        checkFundingRequestTransfer(reference),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                activeTransferCheck.value = null;
            },
        },
    );
}

async function copyFundingRequest(
    reference: string,
    value: string,
): Promise<void> {
    if (!navigator.clipboard?.writeText) {
        return;
    }

    await navigator.clipboard.writeText(value);
    copiedFundingRequest.value = reference;
}

function fundingRequestMessage(amount: string, code: string): string {
    return `Please process my ${amount} Account Funding request. Pay Code: ${code}.`;
}

function fundingRequestStatusTone(status: string): string {
    return (
        {
            funded: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
            funding:
                'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200',
            action_needed:
                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
            not_funded:
                'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
            cancelled:
                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            expired:
                'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            pending:
                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
        }[status] ??
        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
    );
}

function openFundingActivityInstructions(
    item: CockpitFundingActivityItem,
): void {
    if (item.request_reference) {
        openBankTransferInstructions(item.request_reference);
    }
}

function checkFundingActivityProvider(item: CockpitFundingActivityItem): void {
    if (item.request_reference) {
        checkTransfer(item.request_reference);
    }
}

function copyFundingActivityPayCode(item: CockpitFundingActivityItem): void {
    if (item.pay_code?.code) {
        void copyFundingRequest(item.key, item.pay_code.code);
    }
}

function approveFundingActivityReceipt(item: CockpitFundingActivityItem): void {
    const receipt = standingReceipts.value.find(
        (candidate) => candidate.approval_reference === item.approval_reference,
    );

    if (receipt) {
        void approveStandingFundingReceipt(receipt);
    }
}

const fundingActivityProcessingKey = computed(() => {
    if (activeTransferCheck.value !== null) {
        return `request:${activeTransferCheck.value}`;
    }

    if (activeStandingReceiptApproval.value !== null) {
        return (
            fundingActivity.value.items.find(
                (item) =>
                    item.source === 'standing_funding_receipt' &&
                    item.display_reference ===
                        activeStandingReceiptApproval.value,
            )?.key ?? null
        );
    }

    return null;
});

function selectFundingRequestEvidence(event: Event): void {
    const input = event.target as HTMLInputElement;

    fundingRequestForm.evidence_document = input.files?.[0] ?? null;
}

function inspectPayCodeFunding(): void {
    payCodeInspectionForm.post(inspectPayCodeFundingRoute.url(), {
        preserveScroll: true,
        onSuccess: () => {
            payCodeInspectionForm.reset('code');
        },
    });
}

function claimPayCodeFunding(): void {
    const inspectionToken =
        props.pay_code_funding_preview?.inspection_token ?? '';

    if (inspectionToken === '') {
        return;
    }

    payCodeFundingClaimForm.inspection_token = inspectionToken;
    payCodeFundingClaimForm.post(claimPayCodeFundingRoute.url(), {
        preserveScroll: true,
    });
}

function requestReconciliation(caseReference: string, action: string): void {
    if (!canManageTreasuryReconciliation.value) {
        return;
    }

    activeReconciliationCase.value = caseReference;
    reconciliationForm.action = action;
    reconciliationForm.post(storeReconciliationRequest(caseReference), {
        preserveScroll: true,
        onFinish: () => {
            activeReconciliationCase.value = null;
        },
    });
}

function approveFundingReconciliation(reference: string): void {
    if (!canManageTreasuryReconciliation.value) {
        return;
    }

    activeApproval.value = reference;
    approvalForm.post(approveReconciliation(reference), {
        preserveScroll: true,
        onFinish: () => {
            activeApproval.value = null;
        },
    });
}

function checkNetBank(reference: string): void {
    if (activeVerificationCheck.value !== null) {
        return;
    }

    activeVerificationCheck.value = reference;

    verificationForm.post(storeVerificationCheck(reference), {
        preserveScroll: true,
        onFinish: () => {
            activeVerificationCheck.value = null;
        },
    });
}

function refreshLiquidity(): void {
    if (
        !canRefreshProviderLiquidity.value ||
        liquidityRefreshRunning.value ||
        refreshableProviderLiquidity.value === undefined
    ) {
        return;
    }

    liquidityRefreshError.value = null;
    router.post(
        refreshFundingLiquidityRoute(),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                liquidityRefreshRunning.value = true;
            },
            onError: (errors) => {
                liquidityRefreshError.value =
                    typeof errors.liquidity_refresh === 'string'
                        ? errors.liquidity_refresh
                        : 'Provider liquidity could not be refreshed.';
            },
            onFinish: () => {
                liquidityRefreshRunning.value = false;
            },
        },
    );
}

function reconciliationActionLabel(action: string): string {
    return (
        {
            retry_verification: 'Request verification retry',
            match_verified_observation: 'Request exact evidence match',
            compensate_verified_posting: 'Request verified posting',
        }[action] ?? displayLabel(action)
    );
}

async function runQrPhFundingSimulation(): Promise<void> {
    if (
        simulationRunning.value ||
        props.funding_simulation?.enabled !== true ||
        props.funding_simulation.mobile_ready !== true
    ) {
        return;
    }

    simulationRunning.value = true;
    simulationError.value = null;
    const route = runQrPhFundingSimulationRoute();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = await safeJson(response);

        if (!response.ok) {
            simulationError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'The QR Ph simulation could not complete safely.';

            return;
        }

        if (
            body.schema !== 'x-change.lifecycle.qrph-funding-simulation.v1' ||
            !Array.isArray(body.steps)
        ) {
            simulationError.value =
                'The QR Ph simulation returned an unexpected response.';

            return;
        }

        simulationResult.value = body as CockpitQrPhFundingSimulationResult;
        activeSimulationStepIndex.value = 0;
    } catch {
        simulationError.value =
            'The QR Ph simulation could not reach the Cockpit service.';
    } finally {
        simulationRunning.value = false;
    }
}

async function openStandingFundingAddress(): Promise<void> {
    if (
        standingAddressLoading.value ||
        props.standing_funding_address?.available !== true
    ) {
        return;
    }

    standingAddressLoading.value = true;
    standingAddressError.value = null;
    standingAddressErrorCode.value = null;
    standingAddressOperatorReference.value = null;
    const route = openStandingFundingAddressRoute();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify({
                confirm_account_funding_address: true,
            }),
        });
        const body = await safeJson(response);

        if (
            !response.ok ||
            body.schema !== 'x-change.cockpit.standing-funding-address.v1' ||
            typeof body.address !== 'object' ||
            body.address === null
        ) {
            standingAddressError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'NetBank could not open the Account Funding Address.';
            standingAddressErrorCode.value =
                typeof body.code === 'string' ? body.code : null;
            standingAddressOperatorReference.value =
                typeof body.operator_reference === 'string'
                    ? body.operator_reference
                    : null;

            return;
        }

        standingAddress.value = body.address as CockpitStandingFundingAddress;
        const persistedHistory =
            typeof body.persisted_history === 'object' &&
            body.persisted_history !== null
                ? (body.persisted_history as Record<string, unknown>)
                : {};
        standingReceipts.value = Array.isArray(persistedHistory.observations)
            ? (persistedHistory.observations as CockpitStandingFundingReceipt[])
            : [];
    } catch {
        standingAddressError.value =
            'The Account Funding Address could not reach NetBank.';
    } finally {
        standingAddressLoading.value = false;
    }
}

async function checkStandingFundingHistory(): Promise<void> {
    if (
        standingHistoryLoading.value ||
        standingHistoryCooldownSeconds.value > 0 ||
        standingAddress.value === null
    ) {
        return;
    }

    standingHistoryLoading.value = true;
    standingAddressError.value = null;
    const route = checkStandingFundingHistoryRoute();

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify({
                confirm_account_funding_address: true,
            }),
        });
        const body = await safeJson(response);

        if (response.status === 429) {
            const retryAfter = Number.parseInt(
                response.headers.get('Retry-After') ?? '',
                10,
            );
            startStandingHistoryCooldown(
                Number.isFinite(retryAfter) ? retryAfter : 60,
            );
            standingAddressError.value =
                'NetBank was checked recently. Wait for the cooldown before checking again.';

            return;
        }

        if (
            !response.ok ||
            body.schema !== 'x-change.cockpit.standing-funding-history.v1' ||
            !Array.isArray(body.observations)
        ) {
            standingAddressError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'NetBank history could not be checked.';

            return;
        }

        standingReceipts.value =
            body.observations as CockpitStandingFundingReceipt[];
        standingActionNotice.value =
            body.balance_changed === true
                ? 'New NetBank funding was applied to Client Funds exactly once.'
                : body.observations.length > 0
                  ? 'NetBank history refreshed. Previously applied receipts were not applied again.'
                  : null;

        refreshFundingProjections();
    } catch {
        standingAddressError.value =
            'The NetBank history check could not reach the provider.';
    } finally {
        standingHistoryLoading.value = false;
    }
}

function startStandingHistoryCooldown(seconds: number): void {
    standingHistoryCooldownSeconds.value = Math.min(60, Math.max(1, seconds));

    if (standingHistoryCooldownTimer !== null) {
        clearInterval(standingHistoryCooldownTimer);
    }

    standingHistoryCooldownTimer = setInterval(() => {
        standingHistoryCooldownSeconds.value = Math.max(
            0,
            standingHistoryCooldownSeconds.value - 1,
        );

        if (
            standingHistoryCooldownSeconds.value === 0 &&
            standingHistoryCooldownTimer !== null
        ) {
            clearInterval(standingHistoryCooldownTimer);
            standingHistoryCooldownTimer = null;
        }
    }, 1000);
}

async function approveStandingFundingReceipt(
    receipt: CockpitStandingFundingReceipt,
): Promise<void> {
    if (
        activeStandingReceiptApproval.value !== null ||
        !receipt.approval_reference
    ) {
        return;
    }

    const displayReference = receipt.reference;

    activeStandingReceiptApproval.value = displayReference;
    standingAddressError.value = null;
    standingActionNotice.value = null;
    const route = approveStandingFundingReceiptRoute(
        receipt.approval_reference,
    );

    try {
        const response = await fetch(route.url, {
            method: route.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
        });
        const body = await safeJson(response);

        if (
            !response.ok ||
            body.schema !==
                'x-change.cockpit.account-funding-receipt-approval.v1'
        ) {
            standingAddressError.value =
                typeof body.message === 'string'
                    ? body.message
                    : 'The verified funding receipt could not be approved.';

            return;
        }

        standingReceipts.value = standingReceipts.value.map((receipt) =>
            receipt.reference === displayReference
                ? {
                      ...receipt,
                      status: 'settled',
                      applied: true,
                      applied_amount_minor: receipt.net_amount_minor,
                      applied_amount: receipt.net_amount,
                      applied_at:
                          typeof body.receipt === 'object' &&
                          body.receipt !== null &&
                          typeof (body.receipt as Record<string, unknown>)
                              .settled_at === 'string'
                              ? (body.receipt as Record<string, string>)
                                    .settled_at
                              : receipt.applied_at,
                      provisional: false,
                      can_approve: false,
                      approval_reference: null,
                  }
                : receipt,
        );
        standingActionNotice.value =
            typeof body.message === 'string'
                ? body.message
                : 'Verified funding was credited to the Account.';
        refreshFundingProjections();
    } catch {
        standingAddressError.value =
            'The funding approval could not reach the Cockpit service.';
    } finally {
        activeStandingReceiptApproval.value = null;
    }
}

function refreshFundingProjections(): void {
    const refreshedAt = Date.now();

    if (refreshedAt - lastProjectionRefreshAt < 750) {
        return;
    }

    lastProjectionRefreshAt = refreshedAt;
    router.reload({
        only: [
            'cockpit_header_read_model',
            'funding_read_model',
            'funding_requests',
            'funding_activity',
        ],
        preserveScroll: true,
        preserveState: true,
    });
}

function handleFundingQrMerchantProfileUpdated(): void {
    resetStandingFundingAddress();
    standingActionNotice.value = 'Merchant label saved. Updating QR…';
    void openStandingFundingAddress();
}

function resetStandingFundingAddress(): void {
    standingAddress.value = null;
    standingReceipts.value = [];
    standingAddressError.value = null;
    standingActionNotice.value = null;
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
</script>

<template>
    <CockpitLayout
        active-navigation="funding"
        :cockpit-header-read-model="cockpit_header_read_model"
        :cockpit-entry-notice="cockpit_entry_notice"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-5 md:block md:space-y-5"
            data-testid="cockpit-funding-page"
        >
            <div
                v-if="funding_notice"
                class="-order-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 md:order-none dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                role="status"
            >
                {{ funding_notice }}
            </div>
            <div
                class="contents md:block md:rounded-2xl md:border md:border-slate-200 md:bg-white md:px-4 md:py-3 md:shadow-sm md:dark:border-slate-800 md:dark:bg-slate-900"
                data-testid="cockpit-funding-header-shell"
            >
                <section
                    class="-order-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm md:order-none md:rounded-none md:border-0 md:bg-transparent md:p-0 md:shadow-none dark:border-slate-800 dark:bg-slate-900 md:dark:bg-transparent"
                    data-testid="cockpit-funding-header"
                >
                    <section
                        class="rounded-2xl bg-slate-950 px-4 py-3 text-white shadow-sm md:hidden dark:bg-slate-800"
                        aria-labelledby="cockpit-funding-balance-hero-label"
                        data-testid="cockpit-funding-balance-hero"
                    >
                        <div
                            class="flex min-w-0 items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    id="cockpit-funding-balance-hero-label"
                                    class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-300"
                                >
                                    Client Funds
                                </p>
                                <p
                                    class="mt-1 truncate text-3xl font-bold tabular-nums tracking-tight"
                                    data-testid="cockpit-funding-balance-hero-value"
                                >
                                    {{
                                        fundingBalanceValuesVisible
                                            ? clientFundsValue
                                            : '••••••'
                                    }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex size-10 shrink-0 items-center justify-center rounded-full border border-white/20 text-slate-200 transition hover:border-white/35 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                :aria-label="
                                    fundingBalanceValuesVisible
                                        ? 'Hide Client Funds balance'
                                        : 'Show Client Funds balance'
                                "
                                :aria-pressed="fundingBalanceValuesVisible"
                                :title="
                                    fundingBalanceValuesVisible
                                        ? 'Hide Client Funds balance'
                                        : 'Show Client Funds balance'
                                "
                                data-testid="cockpit-funding-balance-hero-toggle"
                                @click="toggleFundingBalanceVisibility"
                            >
                                <EyeOff
                                    v-if="fundingBalanceValuesVisible"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                <Eye v-else class="size-5" aria-hidden="true" />
                            </button>
                        </div>
                    </section>

                    <div
                        class="hidden md:block"
                        data-testid="cockpit-funding-orientation"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <h1
                                class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                            >
                                Account Funding
                            </h1>
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                            >
                                Bank verified
                            </span>
                        </div>
                        <p
                            class="mt-1 text-sm leading-5 text-slate-600 dark:text-slate-400"
                        >
                            Add funds using QR Ph, bank transfer, or Pay Code.
                        </p>
                    </div>

                    <section
                        class="hidden grid-cols-4 gap-2 md:mt-3 md:grid"
                        aria-label="Funding summary"
                        data-testid="cockpit-funding-summary-strip"
                    >
                        <article
                            v-for="card in summaryCards"
                            :key="card.key"
                            class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-center dark:border-slate-700 dark:bg-slate-950/50"
                        >
                            <p
                                class="truncate text-[0.62rem] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                :class="[
                                    'mt-0.5 text-base font-semibold tracking-tight',
                                    card.tone,
                                ]"
                            >
                                {{ card.value }}
                            </p>
                            <p class="sr-only">
                                {{ card.helper }}
                            </p>
                        </article>
                    </section>

                    <div
                        class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800"
                        data-testid="cockpit-funding-mode-switcher"
                    >
                        <div
                            class="grid grid-cols-3 gap-2"
                            role="tablist"
                            aria-label="Funding workspace mode"
                        >
                            <button
                                v-for="mode in primaryFundingWorkspaceModes"
                                :id="`funding-mode-${mode.key}`"
                                :key="mode.key"
                                type="button"
                                role="tab"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl px-2 py-2 text-center text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                                :class="
                                    activeFundingMode === mode.key
                                        ? 'bg-slate-950 text-white shadow-sm dark:bg-sky-300 dark:text-slate-950'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                                "
                                :aria-selected="activeFundingMode === mode.key"
                                :aria-controls="`funding-panel-${mode.key}`"
                                :data-testid="`funding-mode-${mode.key}`"
                                @click="activeFundingMode = mode.key"
                            >
                                <QrCode
                                    v-if="mode.key === 'self_top_up'"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                    data-testid="funding-mode-icon-self_top_up"
                                />
                                <Landmark
                                    v-else-if="mode.key === 'bank_transfer'"
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                    data-testid="funding-mode-icon-bank_transfer"
                                />
                                <TicketCheck
                                    v-else
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                    data-testid="funding-mode-icon-pay_code"
                                />
                                <span>{{ mode.label }}</span>
                            </button>
                        </div>
                    </div>
                </section>

                <section
                    class="-order-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm md:order-none md:rounded-none md:border-0 md:bg-transparent md:p-0 md:shadow-none dark:border-slate-800 dark:bg-slate-900 md:dark:bg-transparent"
                    data-testid="cockpit-funding-more-options"
                >
                    <details
                        class="md:mt-2 md:border-t md:border-slate-200 md:pt-2 md:dark:border-slate-800"
                        data-testid="funding-advanced-paths"
                        :open="
                            activeFundingMode === 'reviewed_value' ||
                            activeFundingMode === 'simulation'
                        "
                    >
                        <summary
                            class="cursor-pointer text-xs font-semibold text-slate-500 marker:text-slate-400 dark:text-slate-400"
                        >
                            Other funding options
                        </summary>
                        <div class="flex flex-wrap gap-2 pt-2">
                            <button
                                id="funding-mode-reviewed_value"
                                type="button"
                                class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
                                :class="
                                    activeFundingMode === 'reviewed_value'
                                        ? 'border-slate-950 bg-slate-950 text-white dark:border-sky-300 dark:bg-sky-300 dark:text-slate-950'
                                        : 'border-slate-300 text-slate-600 hover:border-slate-400 hover:text-slate-950 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white'
                                "
                                :aria-pressed="
                                    activeFundingMode === 'reviewed_value'
                                "
                                aria-controls="funding-panel-reviewed_value"
                                data-testid="funding-mode-reviewed_value"
                                @click="activeFundingMode = 'reviewed_value'"
                            >
                                Reviewed Value
                            </button>
                            <button
                                v-if="funding_simulation"
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white"
                                data-testid="funding-mode-simulation"
                                @click="activeFundingMode = 'simulation'"
                            >
                                Lifecycle simulation
                            </button>
                        </div>

                        <section
                            class="mt-3 border-t border-slate-200 pt-3 md:hidden dark:border-slate-800"
                            data-testid="cockpit-funding-mobile-summary"
                        >
                            <p
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                            >
                                Funding status
                            </p>
                            <div
                                class="grid grid-cols-2 gap-2 pt-2"
                                aria-label="Mobile funding summary"
                                data-testid="cockpit-funding-mobile-summary-strip"
                            >
                                <article
                                    v-for="card in summaryCards"
                                    :key="card.key"
                                    class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-center dark:border-slate-700 dark:bg-slate-950/50"
                                >
                                    <p
                                        class="truncate text-[0.62rem] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400"
                                    >
                                        {{ card.label }}
                                    </p>
                                    <p
                                        :class="[
                                            'mt-0.5 text-base font-semibold tracking-tight',
                                            card.tone,
                                        ]"
                                    >
                                        {{ card.value }}
                                    </p>
                                    <p class="sr-only">
                                        {{ card.helper }}
                                    </p>
                                </article>
                            </div>
                        </section>
                    </details>

                    <p
                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                        data-testid="funding-confirmation-notice"
                    >
                        Funds appear in your Account only after confirmation
                        from the bank or payment provider.
                    </p>
                </section>
            </div>

            <details
                v-if="canViewTreasuryControls && treasuryPortfolio"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="funding-treasury-portfolio"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 marker:hidden"
                    data-testid="funding-treasury-oversight-summary"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700 dark:text-sky-300"
                            >
                                Treasury oversight
                            </p>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                cached projection
                            </span>
                        </div>
                        <h2
                            class="mt-0.5 text-base font-semibold text-slate-950 dark:text-white"
                        >
                            Liquidity &amp; reconciliation
                        </h2>
                    </div>
                    <span
                        class="text-right text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >
                        {{ treasuryPositionControl }}
                    </span>
                </summary>
                <div
                    class="flex flex-col items-start gap-2 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                    data-testid="funding-liquidity-control"
                >
                    <div>
                        <p
                            v-if="staleProviderLiquidity"
                            class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                            data-testid="funding-liquidity-freshness"
                        >
                            {{ staleProviderLiquidity.provider_label }}
                            liquidity stale · checked
                            {{
                                displayTime(
                                    staleProviderLiquidity.provider_liquidity_checked_at,
                                )
                            }}
                        </p>
                        <p
                            v-else-if="
                                refreshableProviderLiquidity?.provider_liquidity !=
                                null
                            "
                            class="text-xs text-slate-500 dark:text-slate-400"
                            data-testid="funding-liquidity-freshness"
                        >
                            {{ refreshableProviderLiquidity.provider_label }}
                            liquidity fresh · checked
                            {{
                                displayTime(
                                    refreshableProviderLiquidity.provider_liquidity_checked_at,
                                )
                            }}
                        </p>
                        <p
                            v-else
                            class="text-xs text-slate-500 dark:text-slate-400"
                            data-testid="funding-liquidity-freshness"
                        >
                            Cached projections only · no provider call on page
                            load
                        </p>
                    </div>
                    <div class="flex flex-col items-start gap-2 sm:items-end">
                        <button
                            v-if="
                                canRefreshProviderLiquidity &&
                                refreshableProviderLiquidity
                            "
                            type="button"
                            class="inline-flex min-h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-600 dark:hover:text-sky-300 dark:focus-visible:ring-offset-slate-900"
                            data-testid="funding-liquidity-refresh"
                            :disabled="liquidityRefreshRunning"
                            @click="refreshLiquidity"
                        >
                            {{
                                liquidityRefreshRunning
                                    ? 'Refreshing…'
                                    : 'Refresh liquidity'
                            }}
                        </button>
                        <p
                            v-if="liquidityRefreshError"
                            class="max-w-sm text-xs font-medium text-rose-600 sm:text-right dark:text-rose-300"
                            role="alert"
                        >
                            {{ liquidityRefreshError }}
                        </p>
                    </div>
                </div>

                <dl class="grid sm:grid-cols-2">
                    <div class="min-w-0 px-4 py-3">
                        <dt
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400"
                        >
                            Provider Inventory
                        </dt>
                        <dd
                            class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{
                                treasuryPortfolio.totals.provider_inventory ??
                                'Not available'
                            }}
                        </dd>
                    </div>
                    <div
                        class="min-w-0 border-t border-slate-200 px-4 py-3 sm:border-l sm:border-t-0 dark:border-slate-800"
                    >
                        <dt
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400"
                        >
                            Position control
                        </dt>
                        <dd
                            class="mt-1 text-sm font-semibold text-slate-950 dark:text-white"
                        >
                            {{ treasuryPositionControl }}
                        </dd>
                    </div>
                </dl>

                <details
                    class="border-t border-slate-200 px-4 py-2.5 dark:border-slate-800"
                    data-testid="funding-settlement-safeguards"
                >
                    <summary
                        class="cursor-pointer text-xs font-semibold text-slate-700 marker:text-slate-400 dark:text-slate-300"
                    >
                        Settlement safeguards
                    </summary>
                    <dl
                        class="mt-3 grid grid-cols-3 divide-x divide-slate-200 rounded-xl border border-slate-200 bg-slate-50 dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-950/50"
                        aria-label="Funding control posture"
                    >
                        <div class="min-w-0 px-3 py-2 text-center">
                            <dt
                                class="text-[0.62rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            >
                                Authority
                            </dt>
                            <dd
                                class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                Bank or EMI
                            </dd>
                        </div>
                        <div class="min-w-0 px-3 py-2 text-center">
                            <dt
                                class="text-[0.62rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            >
                                Posting
                            </dt>
                            <dd
                                class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                Verified
                            </dd>
                        </div>
                        <div class="min-w-0 px-3 py-2 text-center">
                            <dt
                                class="text-[0.62rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            >
                                Manual credit
                            </dt>
                            <dd
                                class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                Disabled
                            </dd>
                        </div>
                    </dl>
                    <p
                        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Provider evidence is independently verified before
                        Inventory recognition and Account posting.
                    </p>
                </details>

                <details
                    class="border-t border-slate-200 px-4 py-2.5 dark:border-slate-800"
                    data-testid="funding-treasury-provider-breakdown"
                >
                    <summary
                        class="cursor-pointer text-xs font-semibold text-slate-700 marker:text-slate-400 dark:text-slate-300"
                    >
                        Provider controls
                        <span class="font-normal text-slate-500">
                            ({{ treasuryPortfolio.connections.length }})
                        </span>
                    </summary>
                    <div
                        v-if="treasuryPortfolio.connections.length"
                        class="mt-3 grid gap-3 lg:grid-cols-2"
                    >
                        <article
                            v-for="connection in treasuryPortfolio.connections"
                            :key="`${connection.provider}-${connection.currency}`"
                            class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-950/40"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p
                                    class="text-sm font-semibold text-slate-950 dark:text-white"
                                >
                                    {{ connection.provider_label }}
                                </p>
                                <span
                                    class="rounded-full bg-white px-2 py-0.5 text-[0.65rem] font-semibold uppercase text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700"
                                >
                                    {{ displayLabel(connection.status) }}
                                </span>
                            </div>
                            <dl
                                class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-3"
                            >
                                <div>
                                    <dt class="text-slate-500">Client Funds</dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ connection.client_funds }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">
                                        Pay Code Reserve
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ connection.pay_code_reserve }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">
                                        Provider Liquidity
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{
                                            connection.provider_liquidity ??
                                            'Not available'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">
                                        Provider Inventory
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{
                                            connection.provider_inventory ??
                                            'Not available'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">
                                        Issuance Capacity
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{
                                            connection.issuance_capacity ??
                                            'Not available'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Control</dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{
                                            controlStatusLabel(
                                                connection.control_status,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <p
                                class="mt-3 border-t border-slate-200 pt-2 text-[0.7rem] text-slate-500 dark:border-slate-800 dark:text-slate-400"
                            >
                                Liquidity:
                                {{
                                    displayLabel(
                                        connection.provider_liquidity_status,
                                    )
                                }}
                                · checked
                                {{
                                    displayTime(
                                        connection.provider_liquidity_checked_at,
                                    )
                                }}
                            </p>
                        </article>
                    </div>
                    <p
                        v-else
                        class="mt-3 text-xs text-slate-500 dark:text-slate-400"
                    >
                        No provider Treasury connection is configured.
                    </p>
                    <p
                        class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        Provider outflow reflects principal only. Sender-side
                        system charges remain in the deferred Accounting Wave
                        until explicit Treasury debit and revenue allocation are
                        implemented.
                    </p>
                </details>
            </details>

            <section
                v-if="standing_funding_address"
                v-show="activeFundingMode === 'self_top_up'"
                id="funding-panel-self_top_up"
                role="tabpanel"
                aria-labelledby="funding-mode-self_top_up"
                class="-order-2 md:order-none"
                data-testid="cockpit-standing-funding-address"
            >
                <CockpitFundingMethodPanel
                    title="QR Ph"
                    description="Scan your reusable QR Ph code, then check NetBank for confirmed funds."
                    test-id="funding-method-panel-qr-ph"
                >
                    <template #action>
                        <span
                            v-if="standingAddressLoading"
                            class="inline-flex h-9 items-center rounded-lg bg-sky-100 px-3 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200"
                            data-testid="standing-funding-address-loading"
                        >
                            Preparing QR…
                        </span>
                        <button
                            v-else-if="
                                standingAddress === null &&
                                standing_funding_address.available === true &&
                                standingAddressErrorCode !==
                                    'standing_funding_address_binding_migration_required'
                            "
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-semibold text-sky-800 transition hover:bg-sky-50 dark:border-sky-800 dark:bg-slate-950 dark:text-sky-200 dark:hover:bg-sky-950"
                            data-testid="open-standing-funding-address"
                            @click="openStandingFundingAddress"
                        >
                            <RefreshCw
                                class="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            Try again
                        </button>
                        <button
                            v-if="standingAddress"
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-sky-400 dark:text-slate-950 dark:hover:bg-sky-300"
                            :disabled="
                                standingHistoryLoading ||
                                standingHistoryCooldownSeconds > 0
                            "
                            data-testid="check-standing-funding-history"
                            @click="checkStandingFundingHistory"
                        >
                            <RefreshCw
                                class="size-3.5 shrink-0"
                                :class="{
                                    'animate-spin': standingHistoryLoading,
                                }"
                                aria-hidden="true"
                                data-testid="check-standing-funding-history-icon"
                            />
                            {{
                                standingHistoryLoading
                                    ? 'Checking NetBank…'
                                    : standingHistoryCooldownSeconds > 0
                                      ? `Try again in ${standingHistoryCooldownSeconds}s`
                                      : 'Check NetBank'
                            }}
                        </button>
                        <span
                            v-if="
                                !standingAddressLoading &&
                                standingAddress === null &&
                                standing_funding_address.available !== true
                            "
                            class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            QR Ph unavailable
                        </span>
                    </template>

                    <CockpitFundingQrAddress
                        v-if="standingAddress"
                        :address="standingAddress"
                        :profile="fundingQrMerchantProfile"
                        :notice="standingActionNotice"
                        @updated="handleFundingQrMerchantProfileUpdated"
                    />

                    <div
                        v-if="standingAddressError"
                        class="flex flex-wrap items-center justify-between gap-3 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:bg-rose-950/20 dark:text-rose-200"
                        role="alert"
                    >
                        <span>{{ standingAddressError }}</span>
                        <span
                            v-if="standingAddressOperatorReference"
                            class="text-xs font-semibold"
                        >
                            Operator reference:
                            {{ standingAddressOperatorReference }}
                        </span>
                        <button
                            v-if="
                                standing_funding_address.available &&
                                standingAddressErrorCode !==
                                    'standing_funding_address_binding_migration_required'
                            "
                            type="button"
                            class="h-9 rounded-lg border border-rose-300 bg-white px-3 text-xs font-semibold text-rose-800 transition hover:bg-rose-100 dark:border-rose-800 dark:bg-slate-950 dark:text-rose-200 dark:hover:bg-rose-950"
                            @click="openStandingFundingAddress"
                        >
                            Try again
                        </button>
                        <span
                            v-else-if="
                                standingAddressErrorCode ===
                                'standing_funding_address_binding_migration_required'
                            "
                            class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                            data-testid="standing-funding-address-migration-required"
                        >
                            Binding migration required
                        </span>
                    </div>
                </CockpitFundingMethodPanel>
            </section>

            <section
                v-show="activeFundingMode === 'bank_transfer'"
                id="funding-panel-bank_transfer"
                role="tabpanel"
                aria-labelledby="funding-mode-bank_transfer"
                class="-order-2 space-y-4 md:order-none"
                data-testid="cockpit-bank-transfer-funding"
            >
                <CockpitFundingMethodPanel
                    title="Bank Transfer"
                    :description="`Enter at least ${bankTransferInstructions.minimum_requested_amount}. We’ll reserve a unique transfer amount.`"
                    test-id="funding-method-panel-bank-transfer"
                >
                    <template #action>
                        <span
                            class="inline-flex min-h-9 max-w-full items-center whitespace-normal rounded-full bg-sky-50 px-3 py-1 text-center text-[0.65rem] font-semibold uppercase leading-4 text-sky-700 dark:bg-sky-950 dark:text-sky-200"
                        >
                            Exact amount
                        </span>
                    </template>

                    <form
                        class="grid min-w-0 gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-5"
                        data-testid="bank-transfer-funding-form"
                        @submit.prevent="submitBankTransferRequest"
                    >
                        <label class="block min-w-0 text-xs font-semibold">
                            Amount to add
                            <div
                                class="mt-1.5 flex h-11 min-w-0 overflow-hidden rounded-xl border border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950"
                            >
                                <span
                                    class="flex items-center border-r border-slate-200 px-3 text-sm font-semibold text-slate-500 dark:border-slate-700"
                                >
                                    PHP
                                </span>
                                <input
                                    ref="bankTransferAmountInput"
                                    v-model="fundingRequestAmount"
                                    inputmode="decimal"
                                    :placeholder="`Minimum ${bankTransferInstructions.minimum_requested_amount}`"
                                    class="min-w-0 flex-1 bg-transparent px-3 text-base font-semibold outline-none"
                                    data-testid="bank-transfer-amount"
                                />
                            </div>
                            <span
                                v-if="
                                    fundingRequestAmountError ||
                                    fundingRequestForm.errors
                                        .requested_value_minor
                                "
                                class="mt-1 block text-xs text-rose-600"
                            >
                                {{
                                    fundingRequestAmountError ??
                                    fundingRequestForm.errors
                                        .requested_value_minor
                                }}
                            </span>
                        </label>
                        <fieldset
                            class="grid min-w-0 grid-cols-2 gap-2 min-[360px]:grid-cols-3 sm:col-start-1 sm:row-start-2 sm:flex sm:flex-wrap"
                            data-testid="bank-transfer-quick-amounts"
                        >
                            <legend class="sr-only">Quick amounts</legend>
                            <button
                                v-for="amountMinor in bankTransferQuickAmountsMinor"
                                :key="amountMinor"
                                type="button"
                                class="min-h-9 min-w-0 rounded-lg border px-2 py-1.5 text-xs font-semibold tabular-nums leading-4 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 disabled:cursor-not-allowed disabled:opacity-50 sm:min-w-20 sm:px-2.5"
                                :class="
                                    amountToMinor(fundingRequestAmount) ===
                                    amountMinor
                                        ? 'border-sky-700 bg-sky-700 text-white dark:border-sky-300 dark:bg-sky-300 dark:text-slate-950'
                                        : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-sky-300 hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-sky-700 dark:hover:bg-sky-950'
                                "
                                :aria-pressed="
                                    amountToMinor(fundingRequestAmount) ===
                                    amountMinor
                                "
                                :disabled="fundingRequestForm.processing"
                                :data-testid="`bank-transfer-quick-amount-${amountMinor}`"
                                @click="
                                    selectBankTransferQuickAmount(amountMinor)
                                "
                            >
                                {{
                                    bankTransferQuickAmountFormatter.format(
                                        amountMinor / 100,
                                    )
                                }}
                            </button>
                        </fieldset>
                        <button
                            type="submit"
                            class="inline-flex min-h-11 w-full min-w-0 max-w-full items-center justify-center gap-2 whitespace-normal rounded-xl bg-sky-700 px-3 py-2.5 text-center text-sm font-semibold leading-5 text-white transition hover:bg-sky-800 disabled:opacity-50 sm:col-start-2 sm:row-start-1 sm:w-auto sm:self-end sm:px-5 dark:bg-sky-400 dark:text-slate-950"
                            :disabled="
                                fundingRequestForm.processing ||
                                !bankTransferInstructions.enabled
                            "
                            data-testid="reserve-bank-transfer-amount"
                        >
                            <FileText
                                class="size-4 shrink-0"
                                aria-hidden="true"
                                data-testid="reserve-bank-transfer-amount-icon"
                            />
                            {{
                                fundingRequestForm.processing
                                    ? 'Preparing…'
                                    : 'Get bank transfer instructions'
                            }}
                        </button>
                    </form>
                </CockpitFundingMethodPanel>

                <Teleport
                    v-if="
                        bankTransferInstructionsOpen &&
                        selectedBankTransferRequest?.transfer
                    "
                    to="body"
                >
                    <div
                        class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/55 p-4 backdrop-blur-sm"
                        @click.self="closeBankTransferInstructions"
                        @keydown.esc.prevent="closeBankTransferInstructions"
                    >
                        <section
                            ref="bankTransferInstructionDialog"
                            tabindex="-1"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="bank-transfer-dialog-title"
                            aria-describedby="bank-transfer-dialog-description"
                            class="w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl outline-none dark:border-slate-800 dark:bg-slate-900"
                            data-testid="bank-transfer-instruction-dialog"
                        >
                            <header
                                class="border-b border-slate-200 bg-slate-50 p-5 text-left dark:border-slate-800 dark:bg-slate-950"
                            >
                                <h2
                                    id="bank-transfer-dialog-title"
                                    class="text-lg font-semibold text-slate-950 dark:text-white"
                                >
                                    Bank transfer instructions
                                </h2>
                                <p
                                    id="bank-transfer-dialog-description"
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    Transfer the exact amount before the
                                    instruction expires.
                                </p>
                            </header>

                            <div class="grid gap-4 p-5">
                                <div
                                    class="rounded-2xl bg-sky-50 p-4 text-center dark:bg-sky-950/40"
                                >
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300"
                                    >
                                        Transfer exactly
                                    </p>
                                    <p
                                        class="mt-1 text-3xl font-bold tracking-tight text-slate-950 dark:text-white"
                                    >
                                        {{
                                            selectedBankTransferRequest.transfer
                                                .expected_amount
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            selectedBankTransferRequest.transfer
                                                .matching_adjustment
                                        "
                                        class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                                    >
                                        {{
                                            selectedBankTransferRequest.transfer
                                                .requested_amount
                                        }}
                                        requested +
                                        {{
                                            selectedBankTransferRequest.transfer
                                                .matching_adjustment
                                        }}
                                        matching amount. The full amount will be
                                        credited.
                                    </p>
                                </div>

                                <dl
                                    class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 rounded-xl border border-slate-200 p-4 text-sm dark:border-slate-800"
                                    data-testid="bank-transfer-instructions"
                                >
                                    <dt class="text-slate-500">Bank</dt>
                                    <dd class="font-semibold">
                                        {{
                                            bankTransferInstructions.institution
                                        }}
                                    </dd>
                                    <dt class="text-slate-500">Account</dt>
                                    <dd class="font-semibold">
                                        {{
                                            bankTransferInstructions.account_name
                                        }}
                                    </dd>
                                    <dt class="text-slate-500">Number</dt>
                                    <dd
                                        class="font-mono font-semibold tracking-wide"
                                    >
                                        {{
                                            bankTransferInstructions.account_number
                                        }}
                                    </dd>
                                    <dt class="text-slate-500">Valid until</dt>
                                    <dd class="font-semibold">
                                        {{
                                            displayTime(
                                                selectedBankTransferRequest
                                                    .transfer
                                                    .instruction_expires_at,
                                            )
                                        }}
                                    </dd>
                                </dl>

                                <div
                                    class="grid gap-2 text-xs text-slate-600 sm:grid-cols-2 dark:text-slate-300"
                                >
                                    <div
                                        class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                                    >
                                        <p
                                            class="font-semibold text-slate-900 dark:text-white"
                                        >
                                            InstaPay
                                        </p>
                                        <p class="mt-1">
                                            Usually reflected immediately;
                                            delays may occur.
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                                    >
                                        <p
                                            class="font-semibold text-slate-900 dark:text-white"
                                        >
                                            PESONet
                                        </p>
                                        <p class="mt-1">
                                            Processed in banking-day batches,
                                            subject to the sending bank’s
                                            cutoff.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <footer
                                class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 p-4 sm:flex-row sm:justify-end dark:border-slate-800 dark:bg-slate-950"
                            >
                                <button
                                    type="button"
                                    class="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                    data-testid="close-bank-transfer-instructions"
                                    @click="
                                        bankTransferInstructionsOpen = false
                                    "
                                >
                                    Close
                                </button>
                                <button
                                    v-if="
                                        selectedBankTransferRequest.transfer
                                            .can_check
                                    "
                                    type="button"
                                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-sky-700 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 disabled:opacity-50 dark:bg-sky-400 dark:text-slate-950"
                                    :disabled="activeTransferCheck !== null"
                                    @click="
                                        checkTransfer(
                                            selectedBankTransferRequest.reference,
                                        )
                                    "
                                >
                                    <RefreshCw
                                        class="size-4 shrink-0"
                                        :class="{
                                            'animate-spin':
                                                activeTransferCheck ===
                                                selectedBankTransferRequest.reference,
                                        }"
                                        aria-hidden="true"
                                    />
                                    {{
                                        activeTransferCheck ===
                                        selectedBankTransferRequest.reference
                                            ? 'Checking…'
                                            : 'Check NetBank'
                                    }}
                                </button>
                            </footer>
                        </section>
                    </div>
                </Teleport>
            </section>

            <section
                v-show="activeFundingMode === 'pay_code'"
                id="funding-panel-pay_code"
                role="tabpanel"
                aria-labelledby="funding-mode-pay_code"
                class="-order-2 space-y-4 md:order-none"
                data-testid="cockpit-pay-code-funding"
            >
                <CockpitFundingMethodPanel
                    title="Pay Code"
                    description="Add funds with a one-time Pay Code without a provider payout."
                    test-id="pay-code-funding-primary"
                >
                    <template #action>
                        <span
                            class="inline-flex h-9 items-center rounded-full bg-emerald-50 px-3 text-[0.65rem] font-semibold uppercase text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200"
                        >
                            no provider payout
                        </span>
                    </template>
                    <form
                        class="grid gap-2 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-5"
                        data-testid="pay-code-funding-inspection-form"
                        @submit.prevent="inspectPayCodeFunding"
                    >
                        <p
                            class="flex items-center gap-2 text-xs font-semibold text-slate-600 sm:col-span-2 dark:text-slate-300"
                        >
                            <span
                                class="grid size-5 place-items-center rounded-full bg-slate-950 text-[0.65rem] text-white dark:bg-emerald-400 dark:text-slate-950"
                            >
                                1
                            </span>
                            Check the code
                        </p>
                        <label class="sr-only" for="pay-code-funding-code">
                            Pay Code
                        </label>
                        <input
                            id="pay-code-funding-code"
                            v-model="payCodeInspectionForm.code"
                            name="code"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            placeholder="Enter Pay Code"
                            class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-base font-semibold uppercase tracking-wide outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-500 dark:focus:ring-emerald-950"
                        />
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-50 dark:bg-emerald-400 dark:text-slate-950 dark:hover:bg-emerald-300"
                            :disabled="
                                payCodeInspectionForm.processing ||
                                payCodeInspectionForm.code.trim() === ''
                            "
                            data-testid="inspect-pay-code-funding"
                        >
                            <Search
                                class="size-4 shrink-0"
                                :class="{
                                    'animate-pulse':
                                        payCodeInspectionForm.processing,
                                }"
                                aria-hidden="true"
                                data-testid="inspect-pay-code-funding-icon"
                            />
                            {{
                                payCodeInspectionForm.processing
                                    ? 'Checking…'
                                    : 'Check Code'
                            }}
                        </button>
                        <p
                            v-if="payCodeInspectionForm.errors.code"
                            class="text-xs font-medium text-rose-600 sm:col-span-2 dark:text-rose-300"
                            role="alert"
                        >
                            {{ payCodeInspectionForm.errors.code }}
                        </p>
                    </form>
                    <div
                        v-if="pay_code_funding_preview"
                        class="border-t px-4 py-4 sm:px-5"
                        :class="
                            pay_code_funding_preview.eligible
                                ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/20'
                                : 'border-amber-200 bg-amber-50/70 dark:border-amber-900 dark:bg-amber-950/20'
                        "
                        data-testid="pay-code-funding-preview"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="mb-2 flex items-center gap-2 text-xs font-semibold text-emerald-800 dark:text-emerald-200"
                                >
                                    <span
                                        class="grid size-5 place-items-center rounded-full bg-emerald-600 text-[0.65rem] text-white"
                                    >
                                        2
                                    </span>
                                    Confirm Account funding
                                </p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold">
                                        {{
                                            pay_code_funding_preview.code_hint ??
                                            'Pay Code'
                                        }}
                                    </p>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[0.65rem] font-semibold uppercase"
                                        :class="
                                            pay_code_funding_preview.eligible
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200'
                                        "
                                    >
                                        {{
                                            pay_code_funding_preview.eligible
                                                ? 'Ready to add'
                                                : 'Unavailable'
                                        }}
                                    </span>
                                </div>
                                <p
                                    v-if="pay_code_funding_preview.amount"
                                    class="mt-1 text-xl font-bold text-slate-950 dark:text-white"
                                >
                                    {{ pay_code_funding_preview.amount }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <span
                                        v-if="pay_code_funding_preview.eligible"
                                        class="font-semibold text-slate-800 dark:text-slate-100"
                                    >
                                        Code checked. No funds have moved yet.
                                    </span>
                                    <span
                                        v-if="pay_code_funding_preview.eligible"
                                    >
                                        ·
                                    </span>
                                    {{ pay_code_funding_preview.message }}
                                    <template
                                        v-if="
                                            pay_code_funding_preview.expires_at
                                        "
                                    >
                                        · Expires
                                        {{
                                            displayTime(
                                                pay_code_funding_preview.expires_at,
                                            )
                                        }}
                                    </template>
                                </p>
                            </div>
                            <button
                                v-if="
                                    pay_code_funding_preview.eligible &&
                                    pay_code_funding_preview.inspection_token
                                "
                                type="button"
                                class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-50 sm:w-auto"
                                :disabled="payCodeFundingClaimForm.processing"
                                data-testid="claim-pay-code-funding"
                                @click="claimPayCodeFunding"
                            >
                                <CirclePlus
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{
                                    payCodeFundingClaimForm.processing
                                        ? 'Adding to Account…'
                                        : payCodeFundingActionLabel
                                }}
                            </button>
                        </div>
                        <p
                            v-if="payCodeFundingClaimError"
                            class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300"
                            role="alert"
                        >
                            {{ payCodeFundingClaimError }}
                        </p>
                    </div>
                </CockpitFundingMethodPanel>
            </section>

            <section
                v-show="activeFundingMode === 'reviewed_value'"
                id="funding-panel-reviewed_value"
                role="tabpanel"
                aria-labelledby="funding-mode-reviewed_value"
                class="-order-2 space-y-4 md:order-none"
                data-testid="cockpit-reviewed-value-funding"
            >
                <article
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="funding-request-form"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5"
                    >
                        <div>
                            <h2 class="text-base font-semibold">
                                Request a value review
                            </h2>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                For funds or property already received outside
                                QR Ph and bank transfer.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-[0.65rem] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            independent review
                        </span>
                    </header>
                    <div
                        v-if="submittedFundingRequest"
                        class="border-t border-emerald-200 bg-emerald-50/70 p-4 sm:p-5 dark:border-emerald-900 dark:bg-emerald-950/20"
                        data-testid="funding-request-result"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700 dark:text-emerald-300"
                                >
                                    Review requested
                                </p>
                                <p class="mt-1 text-2xl font-bold">
                                    {{
                                        submittedFundingRequest.requested_value
                                    }}
                                </p>
                                <div
                                    v-if="submittedFundingRequest.pay_code"
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <code
                                        class="rounded-lg bg-white px-3 py-1.5 text-base font-bold tracking-wider text-slate-950 shadow-sm dark:bg-slate-900 dark:text-white"
                                    >
                                        {{
                                            submittedFundingRequest.pay_code
                                                .display_code
                                        }}
                                    </code>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[0.65rem] font-semibold uppercase"
                                        :class="
                                            fundingRequestStatusTone(
                                                submittedFundingRequest.receipt_status,
                                            )
                                        "
                                    >
                                        {{
                                            submittedFundingRequest.receipt_status_label
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="submittedFundingRequest.pay_code"
                                class="flex flex-wrap gap-2"
                            >
                                <button
                                    type="button"
                                    class="h-10 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white dark:bg-white dark:text-slate-950"
                                    @click="
                                        copyFundingRequest(
                                            `${submittedFundingRequest.reference}:code`,
                                            submittedFundingRequest.pay_code
                                                .code,
                                        )
                                    "
                                >
                                    {{
                                        copiedFundingRequest ===
                                        `${submittedFundingRequest.reference}:code`
                                            ? 'Copied'
                                            : 'Copy Pay Code'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="h-10 rounded-lg border border-slate-300 px-4 text-sm font-semibold dark:border-slate-700"
                                    @click="
                                        copyFundingRequest(
                                            `${submittedFundingRequest.reference}:message`,
                                            fundingRequestMessage(
                                                submittedFundingRequest.requested_value,
                                                submittedFundingRequest.pay_code
                                                    .code,
                                            ),
                                        )
                                    "
                                >
                                    {{
                                        copiedFundingRequest ===
                                        `${submittedFundingRequest.reference}:message`
                                            ? 'Message copied'
                                            : 'Copy request message'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="h-10 px-2 text-sm font-semibold text-slate-600 dark:text-slate-300"
                                    @click="
                                        dismissedFundingRequestResult = true
                                    "
                                >
                                    Request another
                                </button>
                            </div>
                        </div>
                    </div>
                    <form
                        v-else
                        class="border-t border-slate-200 p-4 sm:p-5 dark:border-slate-800"
                        @submit.prevent="submitFundingRequest"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <h2 class="text-base font-semibold">
                                Reviewed Value
                            </h2>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="block text-xs font-semibold">
                                Amount
                                <div
                                    class="mt-1.5 flex h-10 rounded-lg border border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <span
                                        class="flex items-center border-r border-slate-200 px-3 text-sm font-semibold text-slate-500 dark:border-slate-700"
                                    >
                                        PHP
                                    </span>
                                    <input
                                        v-model="fundingRequestAmount"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        class="min-w-0 flex-1 bg-transparent px-3 text-sm outline-none"
                                    />
                                </div>
                                <span
                                    v-if="
                                        fundingRequestAmountError ||
                                        fundingRequestForm.errors
                                            .requested_value_minor
                                    "
                                    class="mt-1 block text-xs text-rose-600"
                                >
                                    {{
                                        fundingRequestAmountError ??
                                        fundingRequestForm.errors
                                            .requested_value_minor
                                    }}
                                </span>
                            </label>
                            <label class="block text-xs font-semibold">
                                Message
                                <span class="font-normal text-slate-500">
                                    (optional)
                                </span>
                                <textarea
                                    v-model="fundingRequestForm.requester_notes"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    placeholder="Bank transfer sent, cash provided…"
                                />
                            </label>
                            <details
                                class="rounded-xl border border-slate-200 sm:col-span-2 dark:border-slate-800"
                            >
                                <summary
                                    class="cursor-pointer list-none px-3 py-2.5 text-xs font-semibold marker:hidden"
                                >
                                    Add proof or transfer details
                                    <span class="font-normal text-slate-500">
                                        (optional)
                                    </span>
                                </summary>
                                <div
                                    class="grid gap-3 border-t border-slate-200 p-3 sm:grid-cols-3 dark:border-slate-800"
                                >
                                    <label class="text-xs font-semibold">
                                        Source
                                        <select
                                            v-model="
                                                fundingRequestForm.funding_type
                                            "
                                            class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                        >
                                            <option value="unspecified">
                                                Not specified
                                            </option>
                                            <option value="bank_transfer">
                                                Bank transfer
                                            </option>
                                            <option value="cash_handover">
                                                Cash handover
                                            </option>
                                            <option value="precious_metal">
                                                Gold or precious metal
                                            </option>
                                            <option value="jewelry">
                                                Jewelry
                                            </option>
                                            <option value="vehicle">
                                                Vehicle
                                            </option>
                                            <option value="other">
                                                Other reviewed value
                                            </option>
                                        </select>
                                    </label>
                                    <label class="text-xs font-semibold">
                                        Reference
                                        <input
                                            v-model="
                                                fundingRequestForm.external_reference
                                            "
                                            class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                            placeholder="Optional"
                                        />
                                    </label>
                                    <label class="text-xs font-semibold">
                                        Date
                                        <input
                                            v-model="
                                                fundingRequestForm.occurred_on
                                            "
                                            type="date"
                                            class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                        />
                                    </label>
                                    <label class="text-xs font-semibold">
                                        Evidence type
                                        <select
                                            v-model="
                                                fundingRequestForm.evidence_document_type
                                            "
                                            class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                        >
                                            <option value="supporting_document">
                                                Supporting document
                                            </option>
                                            <option value="bank_transfer_proof">
                                                Bank transfer proof
                                            </option>
                                            <option value="custody_receipt">
                                                Custody receipt
                                            </option>
                                            <option value="asset_photo">
                                                Asset photo
                                            </option>
                                            <option value="ownership_document">
                                                Ownership document
                                            </option>
                                            <option value="valuation_document">
                                                Valuation document
                                            </option>
                                        </select>
                                    </label>
                                    <label
                                        class="text-xs font-semibold sm:col-span-2"
                                    >
                                        Evidence document
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                            class="mt-1.5 block min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-semibold dark:border-slate-700 dark:bg-slate-950 dark:file:bg-slate-800"
                                            data-testid="funding-request-evidence"
                                            @change="
                                                selectFundingRequestEvidence
                                            "
                                        />
                                        <span
                                            v-if="
                                                fundingRequestForm.errors
                                                    .evidence_document
                                            "
                                            class="mt-1 block text-xs text-rose-600"
                                        >
                                            {{
                                                fundingRequestForm.errors
                                                    .evidence_document
                                            }}
                                        </span>
                                    </label>
                                </div>
                            </details>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="submit"
                                class="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="fundingRequestForm.processing"
                                data-testid="submit-funding-request"
                            >
                                {{
                                    fundingRequestForm.processing
                                        ? 'Requesting…'
                                        : fundingRequestButtonLabel
                                }}
                            </button>
                        </div>
                    </form>
                </article>
            </section>

            <CockpitFundingActivity
                v-show="activeFundingMode !== 'simulation'"
                :activity="fundingActivity"
                :initial-filter="fundingActivityFilter"
                :processing-key="fundingActivityProcessingKey"
                :copied-key="copiedFundingRequest"
                @view-instructions="openFundingActivityInstructions"
                @check-provider="checkFundingActivityProvider"
                @copy-pay-code="copyFundingActivityPayCode"
                @approve-receipt="approveFundingActivityReceipt"
            />

            <section
                v-if="funding_simulation"
                v-show="activeFundingMode === 'simulation'"
                id="funding-panel-simulation"
                role="tabpanel"
                aria-labelledby="funding-mode-simulation"
                class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-950 dark:bg-slate-900"
                data-testid="cockpit-qrph-funding-simulation"
            >
                <div
                    class="grid gap-5 p-5 md:grid-cols-[10rem_minmax(0,1fr)_auto] md:items-center"
                >
                    <div
                        class="mx-auto rounded-xl border border-slate-200 bg-white p-2 shadow-sm md:mx-0"
                    >
                        <img
                            :src="funding_simulation.qr_code"
                            alt="Illustrative QR Ph funding simulation code"
                            class="size-36"
                        />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:text-violet-300"
                            >
                                QR Ph lifecycle lab
                            </p>
                            <span
                                class="rounded-full bg-violet-50 px-2 py-1 text-[0.65rem] font-semibold uppercase text-violet-700 dark:bg-violet-950 dark:text-violet-200"
                            >
                                Rollback only
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                No monetary value
                            </span>
                        </div>
                        <h2 class="mt-1.5 text-lg font-semibold">
                            Simulate a {{ funding_simulation.amount }} QR Ph
                            funding payment
                        </h2>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            Appreciate how a verified payer mobile resolves the
                            Account, how signed evidence is independently
                            checked, and why an identical callback cannot credit
                            twice. Every database and Treasury-position change
                            is rolled back.
                        </p>
                    </div>
                    <div class="md:text-right">
                        <button
                            type="button"
                            :disabled="
                                simulationRunning ||
                                funding_simulation.enabled !== true ||
                                funding_simulation.mobile_ready !== true
                            "
                            class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="run-qrph-funding-simulation"
                            @click="runQrPhFundingSimulation"
                        >
                            {{
                                simulationRunning
                                    ? 'Simulating safely…'
                                    : funding_simulation.enabled !== true
                                      ? 'Unavailable here'
                                      : funding_simulation.mobile_ready !== true
                                        ? 'Verified mobile required'
                                        : 'Simulate scan and payment'
                            }}
                        </button>
                        <p class="mt-2 text-xs text-slate-500">
                            0 provider calls · 0 retained changes
                        </p>
                    </div>
                </div>

                <div
                    v-if="simulationError"
                    class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-200"
                    role="alert"
                >
                    {{ simulationError }}
                </div>

                <div
                    v-if="simulationResult && activeSimulationStep"
                    class="border-t border-violet-100 bg-slate-50/70 p-4 sm:p-5 dark:border-violet-950 dark:bg-slate-950/40"
                    data-testid="qrph-funding-simulation-stepper"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div
                            class="flex flex-wrap items-center gap-1.5"
                            aria-label="QR Ph simulation steps"
                        >
                            <button
                                v-for="(step, index) in simulationResult.steps"
                                :key="step.key"
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition"
                                :class="
                                    index === activeSimulationStepIndex
                                        ? 'bg-violet-600 text-white'
                                        : 'bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-700'
                                "
                                :aria-label="`Open step ${index + 1}: ${step.label}`"
                                @click="activeSimulationStepIndex = index"
                            >
                                {{ index + 1 }}
                            </button>
                        </div>
                        <p class="text-xs font-medium text-slate-500">
                            Step {{ activeSimulationStepIndex + 1 }} of
                            {{ simulationResult.steps.length }}
                        </p>
                    </div>

                    <article
                        class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <h3 class="text-base font-semibold">
                                {{ activeSimulationStep.label }}
                            </h3>
                            <span
                                class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                            >
                                {{ displayLabel(activeSimulationStep.outcome) }}
                            </span>
                        </div>
                        <dl
                            class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="fact in activeSimulationStep.facts"
                                :key="fact.label"
                                class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900"
                            >
                                <dt class="text-[0.68rem] text-slate-500">
                                    {{ fact.label }}
                                </dt>
                                <dd class="mt-0.5 text-xs font-semibold">
                                    {{ fact.value }}
                                </dd>
                            </div>
                        </dl>
                    </article>

                    <div
                        class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p
                            class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                        >
                            Rollback confirmed · one simulated credit · replay
                            no-op · nothing persisted
                        </p>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="activeSimulationStepIndex === 0"
                                class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950"
                                @click="
                                    activeSimulationStepIndex = Math.max(
                                        0,
                                        activeSimulationStepIndex - 1,
                                    )
                                "
                            >
                                Previous
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white dark:bg-violet-400 dark:text-slate-950"
                                @click="
                                    activeSimulationStepIndex =
                                        activeSimulationStepIndex <
                                        simulationResult.steps.length - 1
                                            ? activeSimulationStepIndex + 1
                                            : 0
                                "
                            >
                                {{
                                    activeSimulationStepIndex <
                                    simulationResult.steps.length - 1
                                        ? 'Next'
                                        : 'Restart'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <details
                v-if="canViewTreasuryControls"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="funding-provider-controls"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 marker:hidden"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400"
                        >
                            Treasury controls
                        </p>
                        <h2 class="mt-0.5 text-sm font-semibold">
                            Provider diagnostics
                        </h2>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ availableFundingProviders.length }} ready ·
                        {{ funding_read_model.providers.length }} installed
                    </span>
                </summary>
                <div
                    class="grid gap-5 border-t border-slate-200 p-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)] dark:border-slate-800"
                >
                    <p
                        v-if="standing_funding_address?.scheme_warning"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 xl:col-span-2 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                        data-testid="standing-funding-address-scheme-warning"
                    >
                        Address scheme:
                        {{ standing_funding_address.scheme_label }}.
                        {{ standing_funding_address.scheme_warning }}
                    </p>
                    <article
                        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                                >
                                    Funding rails
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    Available providers
                                </h2>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ availableFundingProviders.length }} ready ·
                                {{ funding_read_model.providers.length }}
                                installed
                            </span>
                        </div>
                        <div
                            v-if="funding_read_model.providers.length"
                            class="grid gap-3 p-4 sm:grid-cols-2"
                        >
                            <div
                                v-for="provider in funding_read_model.providers"
                                :key="provider.code"
                                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p class="font-semibold">
                                        {{ provider.label }}
                                    </p>
                                    <span
                                        class="rounded-full px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-wide"
                                        :class="{
                                            'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300':
                                                provider.status === 'blocked',
                                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300':
                                                provider.status === 'disabled',
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300':
                                                provider.status === 'available',
                                        }"
                                    >
                                        {{ provider.status }}
                                    </span>
                                </div>
                                <p
                                    class="mt-2 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        displayLabel(
                                            provider.destination_mode ??
                                                'shared',
                                        )
                                    }}
                                    ·
                                    {{
                                        provider.destination_reference ??
                                        'Not configured'
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        provider.status === 'blocked'
                                            ? 'Dedicated funding is unavailable until authoritative destination verification succeeds.'
                                            : provider.status === 'disabled'
                                              ? 'Adapter installed; Funding Intake remains locked until this provider is explicitly enabled.'
                                              : provider.simulation_only
                                                ? 'Local-only Funding Intent happy path with zero bank or EMI calls.'
                                                : 'Signed intake plus independent authoritative status verification.'
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="p-5 text-sm text-slate-500">
                            No funding provider is enabled for this environment.
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                        >
                            Safeguards
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            Non-negotiable controls
                        </h2>
                        <ol class="mt-4 space-y-3">
                            <li
                                v-for="(safeguard, index) in safeguards"
                                :key="safeguard"
                                class="flex gap-3 text-sm leading-5 text-slate-600 dark:text-slate-300"
                            >
                                <span
                                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-950 text-xs font-semibold text-white dark:bg-sky-300 dark:text-slate-950"
                                >
                                    {{ index + 1 }}
                                </span>
                                <span>{{ safeguard }}</span>
                            </li>
                        </ol>
                    </article>
                    <section
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2 dark:border-slate-800 dark:bg-slate-900"
                        data-testid="cockpit-funding-activity"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500"
                                >
                                    Historical provider intake
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    One-time Funding Intent History
                                </h2>
                            </div>
                            <span class="text-xs text-slate-500"
                                >Legacy exact-amount funding intents</span
                            >
                        </div>
                        <div
                            v-if="operationalFundingIntents.length"
                            class="overflow-x-auto"
                        >
                            <table
                                class="w-full min-w-[56rem] text-left text-sm"
                            >
                                <thead
                                    class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/40 dark:text-slate-400"
                                >
                                    <tr>
                                        <th class="px-5 py-3 font-semibold">
                                            Reference
                                        </th>
                                        <th class="px-5 py-3 font-semibold">
                                            Provider
                                        </th>
                                        <th class="px-5 py-3 font-semibold">
                                            Amount
                                        </th>
                                        <th class="px-5 py-3 font-semibold">
                                            Status
                                        </th>
                                        <th class="px-5 py-3 font-semibold">
                                            Last checked
                                        </th>
                                        <th class="px-5 py-3 font-semibold">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="intent in operationalFundingIntents"
                                        :key="intent.reference"
                                    >
                                        <td
                                            class="px-5 py-3 font-mono text-xs text-slate-700 dark:text-slate-300"
                                        >
                                            {{ intent.reference }}
                                        </td>
                                        <td class="px-5 py-3 font-medium">
                                            {{ displayLabel(intent.provider) }}
                                        </td>
                                        <td class="px-5 py-3 font-semibold">
                                            {{ intent.amount }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            >
                                                {{
                                                    displayLabel(
                                                        intent.verification_status,
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-5 py-3 text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                displayTime(
                                                    intent.last_checked_at,
                                                )
                                            }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <div
                                                class="flex min-w-52 flex-wrap items-center gap-2"
                                            >
                                                <button
                                                    v-if="
                                                        intent.can_check_provider
                                                    "
                                                    type="button"
                                                    class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-sky-600 px-3 text-xs font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                    :disabled="
                                                        activeVerificationCheck !==
                                                        null
                                                    "
                                                    :data-testid="`check-netbank-${intent.reference}`"
                                                    @click="
                                                        checkNetBank(
                                                            intent.reference,
                                                        )
                                                    "
                                                >
                                                    <RefreshCw
                                                        class="size-3.5 shrink-0"
                                                        :class="{
                                                            'animate-spin':
                                                                activeVerificationCheck ===
                                                                intent.reference,
                                                        }"
                                                        aria-hidden="true"
                                                    />
                                                    {{
                                                        activeVerificationCheck ===
                                                        intent.reference
                                                            ? 'Checking…'
                                                            : 'Check NetBank'
                                                    }}
                                                </button>
                                                <span
                                                    v-else
                                                    class="text-xs text-slate-400"
                                                >
                                                    —
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="px-5 py-8 text-center">
                            <p
                                class="text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                No one-time funding intents
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                This remains available only for historical
                                exact-amount provider instructions.
                            </p>
                        </div>
                    </section>
                </div>
            </details>

            <details
                v-if="hasFundingExceptions"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="funding-exception-controls"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 marker:hidden"
                >
                    <div>
                        <h2 class="text-sm font-semibold">
                            Funding exceptions
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Provider evidence or accounting needs review.
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        {{ fundingExceptionCount }} open
                    </span>
                </summary>
                <div
                    class="space-y-5 border-t border-slate-200 p-4 dark:border-slate-800"
                >
                    <section
                        v-if="canManageTreasuryReconciliation"
                        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        data-testid="cockpit-funding-approval-queue"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-700 dark:text-indigo-300"
                                >
                                    Dual control
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    Reconciliation approval queue
                                </h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    Requests are visible across Treasury
                                    operators so a different person can approve.
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ funding_read_model.approval_queue.length }}
                                pending
                            </span>
                        </div>
                        <div
                            v-if="funding_read_model.approval_queue.length"
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <div
                                v-for="approval in funding_read_model.approval_queue"
                                :key="approval.reference"
                                class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p class="font-semibold">
                                            {{
                                                reconciliationActionLabel(
                                                    approval.action,
                                                )
                                            }}
                                        </p>
                                        <span
                                            class="rounded-full bg-indigo-50 px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
                                        >
                                            {{
                                                displayLabel(approval.provider)
                                            }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Case {{ approval.case_reference }} ·
                                        {{ displayLabel(approval.reason) }} ·
                                        {{ displayTime(approval.requested_at) }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Exact immutable evidence only; amount
                                        and evidence inputs are disabled by
                                        contract.
                                    </p>
                                </div>
                                <button
                                    v-if="approval.can_approve"
                                    type="button"
                                    class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="approvalForm.processing"
                                    @click="
                                        approveFundingReconciliation(
                                            approval.reference,
                                        )
                                    "
                                >
                                    {{
                                        activeApproval === approval.reference
                                            ? 'Approving…'
                                            : 'Approve and execute'
                                    }}
                                </button>
                                <span
                                    v-else
                                    class="shrink-0 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-500 dark:border-slate-700"
                                >
                                    Awaiting another operator
                                </span>
                            </div>
                        </div>
                        <p v-else class="px-5 py-6 text-sm text-slate-500">
                            No reconciliation requests are awaiting approval.
                        </p>
                        <p
                            v-if="approvalForm.errors.approval"
                            class="border-t border-rose-200 bg-rose-50 px-5 py-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-300"
                        >
                            {{ approvalForm.errors.approval }}
                        </p>
                    </section>

                    <section class="grid gap-5 xl:grid-cols-2">
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300"
                                    >
                                        Exception control
                                    </p>
                                    <h2 class="mt-1 text-lg font-semibold">
                                        Suspense
                                    </h2>
                                </div>
                                <span class="text-sm font-semibold">{{
                                    funding_read_model.suspense_cases.length
                                }}</span>
                            </div>
                            <div
                                v-if="funding_read_model.suspense_cases.length"
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="item in funding_read_model.suspense_cases"
                                    :key="item.reference"
                                    class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900 dark:bg-amber-950/20"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p class="font-mono text-xs">
                                            {{ item.reference }}
                                        </p>
                                        <span
                                            class="text-xs font-semibold text-amber-800 dark:text-amber-300"
                                            >{{
                                                displayLabel(item.reason)
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            item.pending_approval
                                                ? 'Maker request pending independent approval.'
                                                : 'Awaiting a controlled reconciliation request.'
                                        }}
                                    </p>
                                    <div
                                        v-if="item.allowed_actions.length"
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <button
                                            v-for="action in item.allowed_actions"
                                            :key="action"
                                            type="button"
                                            class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                            :disabled="
                                                reconciliationForm.processing
                                            "
                                            @click="
                                                requestReconciliation(
                                                    item.reference,
                                                    action,
                                                )
                                            "
                                        >
                                            {{
                                                activeReconciliationCase ===
                                                item.reference
                                                    ? 'Submitting…'
                                                    : reconciliationActionLabel(
                                                          action,
                                                      )
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="mt-4 text-sm text-slate-500">
                                No open funding exceptions.
                            </p>
                            <p
                                v-if="reconciliationForm.errors.reconciliation"
                                class="mt-3 text-xs text-rose-600 dark:text-rose-300"
                            >
                                {{ reconciliationForm.errors.reconciliation }}
                            </p>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-700 dark:text-rose-300"
                                    >
                                        Issuance protection
                                    </p>
                                    <h2 class="mt-1 text-lg font-semibold">
                                        Recovery holds
                                    </h2>
                                </div>
                                <span class="text-sm font-semibold">{{
                                    funding_read_model.recovery_holds.length
                                }}</span>
                            </div>
                            <div
                                v-if="funding_read_model.recovery_holds.length"
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="hold in funding_read_model.recovery_holds"
                                    :key="hold.reference"
                                    class="rounded-lg border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900 dark:bg-rose-950/20"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p class="font-mono text-xs">
                                            {{ hold.reference }}
                                        </p>
                                        <p
                                            class="font-semibold text-rose-800 dark:text-rose-300"
                                        >
                                            {{ hold.outstanding }}
                                        </p>
                                    </div>
                                    <p
                                        class="mt-2 text-xs text-slate-600 dark:text-slate-400"
                                    >
                                        Future verified funding repays this hold
                                        before becoming usable.
                                    </p>
                                </div>
                            </div>
                            <p v-else class="mt-4 text-sm text-slate-500">
                                No active funding recovery holds.
                            </p>
                        </article>
                    </section>
                </div>
            </details>
        </div>
    </CockpitLayout>
</template>
