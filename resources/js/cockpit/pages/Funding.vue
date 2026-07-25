<script setup lang="ts">
import { router, useForm, usePoll } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { update as updateFundingQrMerchantProfile } from '@/routes/x-change/cockpit/accounts/funding-qr-merchant-profile';
import { approve as approveReconciliation } from '@/routes/x-change/cockpit/funding/reconciliations';
import { store as refreshFundingLiquidityRoute } from '@/routes/x-change/cockpit/funding/liquidity-refreshes';
import { store as claimPayCodeFundingRoute } from '@/routes/x-change/cockpit/funding/pay-code-claims';
import { store as inspectPayCodeFundingRoute } from '@/routes/x-change/cockpit/funding/pay-code-inspections';
import { store as approveFundingRequest } from '@/routes/x-change/cockpit/funding/requests/approvals';
import { store as storeFundingRequest } from '@/routes/x-change/cockpit/funding/requests';
import { store as claimReviewedFundingPayCode } from '@/routes/x-change/cockpit/funding/requests/pay-code-claims';
import { store as prepareFundingRequest } from '@/routes/x-change/cockpit/funding/requests/reviews';
import { store as storeVerificationCheck } from '@/routes/x-change/cockpit/funding/intents/verification-checks';
import { store as openStandingFundingAddressRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank';
import { store as checkStandingFundingHistoryRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank/history-checks';
import { approve as approveStandingFundingReceiptRoute } from '@/routes/x-change/cockpit/funding/standing-addresses/netbank/receipts';
import { store as runQrPhFundingSimulationRoute } from '@/routes/x-change/cockpit/funding/scenarios/qrph';
import { store as storeReconciliationRequest } from '@/routes/x-change/cockpit/funding/suspense/reconciliation-requests';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitFundingPageProps,
    CockpitQrPhFundingSimulationResult,
    CockpitStandingFundingAddress,
    CockpitStandingFundingReceipt,
} from '../types';

const props = defineProps<CockpitFundingPageProps>();
const fundingRequests = computed(
    () =>
        props.funding_requests ?? {
            schema: 'x-change.cockpit.account-funding-requests.v1',
            requests: [],
            notices: [],
            review_queue: [],
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
const standingHistoryCheckedAt = ref<string | null>(null);
const activeStandingReceiptApproval = ref<string | null>(null);
const standingActionNotice = ref<string | null>(null);
const liquidityRefreshRunning = ref(false);
const liquidityRefreshError = ref<string | null>(null);
const activeFundingRequestReview = ref<string | null>(null);
const fundingRequestAmount = ref('');
const fundingReviewAmount = ref('');
const fundingRequestAmountError = ref<string | null>(null);
type FundingWorkspaceMode = 'self_top_up' | 'pay_code' | 'simulation';
const activeFundingMode = ref<FundingWorkspaceMode>('self_top_up');
const fundingQrMerchantProfile = computed(
    () =>
        props.funding_qr_merchant_profile ?? {
            name: 'Account Holder',
            city: 'Manila',
            merchant_category_code: '0000',
            merchant_name_template: '{name} - {city}',
            category_options: [],
            presentation_only: true as const,
            controls_routing: false as const,
            controls_settlement: false as const,
        },
);
const fundingWorkspaceModes = computed(() => [
    {
        key: 'self_top_up' as const,
        label: 'Self Top-Up',
    },
    {
        key: 'pay_code' as const,
        label: 'Pay Code Funding',
    },
]);
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
const staleProviderLiquidity = computed(() =>
    props.funding_read_model.treasury_portfolio.connections.find(
        (connection) =>
            connection.mode !== 'disabled' &&
            connection.provider_liquidity_is_stale,
    ),
);
const refreshableProviderLiquidity = computed(() =>
    props.funding_read_model.treasury_portfolio.connections.find(
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
        only: ['funding_read_model', 'funding_requests', 'funding_notice'],
    },
    {
        autoStart: hasOpenFundingWork.value,
        mode: 'rest',
    },
);
const merchantProfileForm = useForm({
    name: fundingQrMerchantProfile.value.name,
    city: fundingQrMerchantProfile.value.city,
    merchant_category_code:
        fundingQrMerchantProfile.value.merchant_category_code,
    merchant_name_template:
        fundingQrMerchantProfile.value.merchant_name_template,
});
const reconciliationForm = useForm({
    action: '',
});
const approvalForm = useForm({});
const verificationForm = useForm({});
const fundingRequestForm = useForm({
    funding_type: 'bank_transfer',
    requested_value_minor: 0,
    currency: 'PHP',
    description: '',
    external_reference: '',
    occurred_on: '',
    requester_notes: '',
    idempotency_key: newIdempotencyKey(),
});
const fundingRequestReviewForm = useForm({
    recognized_value_minor: 0,
    currency: 'PHP',
    connection_reference: 'netbank-primary',
    evidence_reference: '',
    review_notes: '',
});
const fundingRequestApprovalForm = useForm({});
const reviewedFundingPayCodeClaimForm = useForm({});
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
type FundingProjectionChangedPayload = {
    schema: string;
    event_id: string;
    reason: string;
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
        label: 'Recovery Outstanding',
        value: props.funding_read_model.summary.recovery_outstanding,
        helper: 'Reversed funding still held against future Issuance Capacity.',
        tone: 'text-rose-700 dark:text-rose-300',
    },
]);

const treasuryPositionControl = computed(() => {
    const activeConnections =
        props.funding_read_model.treasury_portfolio.connections.filter(
            (connection) => connection.mode !== 'disabled',
        );

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

function submitFundingRequest(): void {
    fundingRequestForm.clearErrors();
    fundingRequestAmountError.value = null;
    const amountMinor = amountToMinor(fundingRequestAmount.value);

    if (amountMinor === null) {
        fundingRequestAmountError.value =
            'Enter the value you want independently reviewed.';

        return;
    }

    fundingRequestForm.requested_value_minor = amountMinor;
    fundingRequestForm.post(storeFundingRequest(), {
        preserveScroll: true,
        onSuccess: () => {
            fundingRequestAmount.value = '';
            fundingRequestForm.reset(
                'description',
                'external_reference',
                'occurred_on',
                'requester_notes',
            );
            fundingRequestForm.requested_value_minor = 0;
            fundingRequestForm.idempotency_key = newIdempotencyKey();
        },
    });
}

function prepareRequest(reference: string): void {
    const amountMinor = amountToMinor(fundingReviewAmount.value);

    if (amountMinor === null) {
        fundingRequestAmountError.value =
            'Enter the independently recognized value.';

        return;
    }

    activeFundingRequestReview.value = reference;
    fundingRequestReviewForm.recognized_value_minor = amountMinor;
    fundingRequestReviewForm.post(prepareFundingRequest(reference), {
        preserveScroll: true,
        onFinish: () => {
            activeFundingRequestReview.value = null;
        },
    });
}

function approveRequest(reference: string): void {
    activeFundingRequestReview.value = reference;
    fundingRequestApprovalForm.post(approveFundingRequest(reference), {
        preserveScroll: true,
        onFinish: () => {
            activeFundingRequestReview.value = null;
        },
    });
}

function claimReviewedPayCode(requestReference: string): void {
    reviewedFundingPayCodeClaimForm.post(
        claimReviewedFundingPayCode.url(requestReference),
        {
            preserveScroll: true,
        },
    );
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
        standingHistoryCheckedAt.value =
            typeof persistedHistory.last_checked_at === 'string'
                ? persistedHistory.last_checked_at
                : null;
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
        standingHistoryCheckedAt.value =
            typeof body.checked_at === 'string' ? body.checked_at : null;
        standingActionNotice.value =
            body.balance_changed === true
                ? 'New NetBank funding was applied to Client Funds exactly once.'
                : body.observations.length > 0
                  ? 'NetBank history refreshed. Previously applied receipts were not applied again.'
                  : null;

        if (body.balance_changed === true) {
            refreshFundingProjections();
        }
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
        ],
        preserveScroll: true,
        preserveState: true,
    });
}

function saveFundingQrMerchantProfile(): void {
    merchantProfileForm.patch(updateFundingQrMerchantProfile(), {
        preserveScroll: true,
        onSuccess: () => {
            resetStandingFundingAddress();
            standingActionNotice.value = 'Merchant label saved. Updating QR…';
            void openStandingFundingAddress();
        },
    });
}

function resetStandingFundingAddress(): void {
    standingAddress.value = null;
    standingReceipts.value = [];
    standingHistoryCheckedAt.value = null;
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
    >
        <div
            class="mx-auto max-w-7xl space-y-5"
            data-testid="cockpit-funding-page"
        >
            <div
                v-if="funding_notice"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                role="status"
            >
                {{ funding_notice }}
            </div>
            <section
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-funding-header"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.18em] text-sky-700 uppercase dark:text-sky-300"
                            >
                                Funding workspace
                            </p>
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.65rem] font-semibold tracking-wide text-emerald-700 uppercase dark:bg-emerald-950/60 dark:text-emerald-300"
                            >
                                provider verified
                            </span>
                        </div>
                        <h1
                            class="mt-1 text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            Account Funding
                        </h1>
                        <p
                            class="mt-1 max-w-3xl text-sm leading-5 text-slate-600 dark:text-slate-400"
                        >
                            Fund this Account and monitor authoritative
                            settlement.
                        </p>
                    </div>

                    <dl
                        class="grid shrink-0 grid-cols-3 divide-x divide-slate-200 rounded-xl border border-slate-200 bg-slate-50 dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-950/50"
                        aria-label="Funding control posture"
                    >
                        <div class="min-w-0 px-3 py-2 text-center">
                            <dt
                                class="text-[0.62rem] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Authority
                            </dt>
                            <dd
                                class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                Provider
                            </dd>
                        </div>
                        <div class="min-w-0 px-3 py-2 text-center">
                            <dt
                                class="text-[0.62rem] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Posting
                            </dt>
                            <dd
                                class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                Atomic
                            </dd>
                        </div>
                        <div class="min-w-0 px-3 py-2 text-center">
                            <dt
                                class="text-[0.62rem] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Manual load
                            </dt>
                            <dd
                                class="mt-0.5 text-xs font-semibold text-slate-900 dark:text-white"
                            >
                                Disabled
                            </dd>
                        </div>
                    </dl>
                </div>

                <details
                    class="mt-3 border-t border-slate-200 pt-2 text-xs text-slate-600 dark:border-slate-800 dark:text-slate-400"
                >
                    <summary
                        class="cursor-pointer font-semibold text-slate-700 marker:text-slate-400 dark:text-slate-300"
                    >
                        Funding controls
                    </summary>
                    <div class="mt-2 grid gap-2 leading-5 sm:grid-cols-2">
                        <p>
                            There is no manual “add funds” control. Only
                            verified bank or EMI evidence can increase the
                            Client Funds.
                        </p>
                        <p>
                            Webhook evidence ≠ Account credit. The provider is
                            queried independently before Inventory recognition
                            and Account posting occur atomically.
                        </p>
                    </div>
                </details>
            </section>

            <section
                class="grid grid-cols-2 gap-2 xl:grid-cols-4"
                aria-label="Funding summary"
                data-testid="cockpit-funding-summary-strip"
            >
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p
                        class="truncate text-[0.65rem] font-semibold tracking-[0.1em] text-slate-500 uppercase dark:text-slate-400"
                    >
                        {{ card.label }}
                    </p>
                    <p
                        :class="[
                            'mt-1 text-lg font-semibold tracking-tight',
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

            <section
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="funding-treasury-portfolio"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-sky-700 uppercase dark:text-sky-300"
                            >
                                Treasury controls
                            </p>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
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
                    <div
                        class="flex flex-col items-start gap-2 sm:items-end"
                        data-testid="funding-liquidity-control"
                    >
                        <p
                            v-if="staleProviderLiquidity"
                            class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 sm:text-right dark:bg-amber-950/40 dark:text-amber-300"
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
                            class="text-xs text-slate-500 sm:text-right dark:text-slate-400"
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
                            class="text-xs text-slate-500 sm:text-right dark:text-slate-400"
                            data-testid="funding-liquidity-freshness"
                        >
                            Cached projections only · no provider call on page
                            load
                        </p>
                        <button
                            v-if="refreshableProviderLiquidity"
                            type="button"
                            class="inline-flex min-h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-400 hover:text-sky-700 focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-wait disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-600 dark:hover:text-sky-300 dark:focus-visible:ring-offset-slate-900"
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
                            class="text-[0.65rem] font-semibold tracking-[0.08em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Provider Inventory
                        </dt>
                        <dd
                            class="mt-1 text-base font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{
                                funding_read_model.treasury_portfolio.totals
                                    .provider_inventory ?? 'Not available'
                            }}
                        </dd>
                    </div>
                    <div
                        class="min-w-0 border-t border-slate-200 px-4 py-3 sm:border-t-0 sm:border-l dark:border-slate-800"
                    >
                        <dt
                            class="text-[0.65rem] font-semibold tracking-[0.08em] text-slate-500 uppercase dark:text-slate-400"
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
                    data-testid="funding-treasury-provider-breakdown"
                >
                    <summary
                        class="cursor-pointer text-xs font-semibold text-slate-700 marker:text-slate-400 dark:text-slate-300"
                    >
                        Provider controls
                        <span class="font-normal text-slate-500">
                            ({{
                                funding_read_model.treasury_portfolio
                                    .connections.length
                            }})
                        </span>
                    </summary>
                    <div
                        v-if="
                            funding_read_model.treasury_portfolio.connections
                                .length
                        "
                        class="mt-3 grid gap-3 lg:grid-cols-2"
                    >
                        <article
                            v-for="connection in funding_read_model
                                .treasury_portfolio.connections"
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
                                    class="rounded-full bg-white px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 uppercase ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700"
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
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-funding-mode-switcher"
            >
                <div
                    class="grid gap-2 sm:grid-cols-2"
                    role="tablist"
                    aria-label="Funding workspace mode"
                >
                    <button
                        v-for="mode in fundingWorkspaceModes"
                        :id="`funding-mode-${mode.key}`"
                        :key="mode.key"
                        type="button"
                        role="tab"
                        class="min-h-10 rounded-xl px-3 py-2 text-left text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600"
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
                        {{ mode.label }}
                    </button>
                </div>
                <details
                    v-if="funding_simulation"
                    class="mx-2 mt-1 mb-1 border-t border-slate-200 pt-2 dark:border-slate-800"
                    data-testid="funding-advanced-paths"
                >
                    <summary
                        class="cursor-pointer text-xs font-semibold text-slate-500"
                    >
                        Testing tools
                    </summary>
                    <div class="flex flex-wrap gap-2 py-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold dark:border-slate-700"
                            data-testid="funding-mode-simulation"
                            @click="activeFundingMode = 'simulation'"
                        >
                            Lifecycle simulation
                        </button>
                    </div>
                </details>
            </section>

            <section
                v-if="standing_funding_address"
                v-show="activeFundingMode === 'self_top_up'"
                id="funding-panel-self_top_up"
                role="tabpanel"
                aria-labelledby="funding-mode-self_top_up"
                class="overflow-hidden rounded-2xl border border-sky-200 bg-white shadow-sm dark:border-sky-950 dark:bg-slate-900"
                data-testid="cockpit-standing-funding-address"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                >
                    <h2 class="text-sm font-semibold">Account Funding QR Ph</h2>
                    <div class="flex flex-wrap items-center gap-2">
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
                                standing_funding_address.available === true
                            "
                            type="button"
                            class="h-9 rounded-lg border border-sky-300 bg-white px-3 text-xs font-semibold text-sky-800 transition hover:bg-sky-50 dark:border-sky-800 dark:bg-slate-950 dark:text-sky-200 dark:hover:bg-sky-950"
                            data-testid="open-standing-funding-address"
                            @click="openStandingFundingAddress"
                        >
                            Try again
                        </button>
                        <button
                            v-if="standingAddress"
                            type="button"
                            class="h-9 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-sky-400 dark:text-slate-950 dark:hover:bg-sky-300"
                            :disabled="
                                standingHistoryLoading ||
                                standingHistoryCooldownSeconds > 0
                            "
                            data-testid="check-standing-funding-history"
                            @click="checkStandingFundingHistory"
                        >
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
                            Self top-up unavailable
                        </span>
                    </div>
                </div>

                <div
                    v-if="standingAddress"
                    class="border-t border-sky-100 bg-sky-50/50 p-4 dark:border-sky-950 dark:bg-sky-950/10"
                >
                    <div
                        class="grid gap-4 md:grid-cols-[12rem_minmax(0,1fr)] md:items-start"
                    >
                        <div
                            class="mx-auto rounded-xl border border-sky-200 bg-white p-2 shadow-sm md:mx-0 dark:border-sky-900"
                        >
                            <img
                                :src="standingAddress.qr_code"
                                alt="Account Funding Address QR Ph code"
                                class="size-44 object-contain"
                                data-testid="standing-funding-address-qr"
                            />
                        </div>
                        <form
                            class="rounded-xl border border-sky-200 bg-white p-4 shadow-sm dark:border-sky-900 dark:bg-slate-950"
                            data-testid="funding-qr-merchant-profile"
                            @submit.prevent="saveFundingQrMerchantProfile"
                        >
                            <h3 class="text-sm font-semibold">
                                Merchant label
                            </h3>
                            <div
                                class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.72fr)_auto] xl:items-end"
                            >
                                <label class="grid gap-1.5 text-xs font-medium">
                                    <span>Merchant name</span>
                                    <input
                                        v-model="merchantProfileForm.name"
                                        type="text"
                                        maxlength="25"
                                        autocomplete="organization"
                                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 transition outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:ring-sky-950"
                                    />
                                    <span
                                        v-if="merchantProfileForm.errors.name"
                                        class="text-rose-700 dark:text-rose-300"
                                    >
                                        {{ merchantProfileForm.errors.name }}
                                    </span>
                                </label>
                                <label class="grid gap-1.5 text-xs font-medium">
                                    <span>City</span>
                                    <input
                                        v-model="merchantProfileForm.city"
                                        type="text"
                                        maxlength="15"
                                        autocomplete="address-level2"
                                        class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 transition outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:ring-sky-950"
                                    />
                                    <span
                                        v-if="merchantProfileForm.errors.city"
                                        class="text-rose-700 dark:text-rose-300"
                                    >
                                        {{ merchantProfileForm.errors.city }}
                                    </span>
                                </label>
                                <button
                                    type="submit"
                                    class="h-10 rounded-lg bg-sky-700 px-4 text-sm font-semibold whitespace-nowrap text-white transition hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2 xl:col-span-1"
                                    :disabled="merchantProfileForm.processing"
                                >
                                    {{
                                        merchantProfileForm.processing
                                            ? 'Updating…'
                                            : 'Update QR'
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="
                                    merchantProfileForm.errors
                                        .merchant_name_template
                                "
                                class="mt-2 text-xs text-rose-700 dark:text-rose-300"
                            >
                                {{
                                    merchantProfileForm.errors
                                        .merchant_name_template
                                }}
                            </p>
                        </form>
                    </div>

                    <div
                        v-if="standingActionNotice"
                        class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
                        role="status"
                    >
                        {{ standingActionNotice }}
                    </div>

                    <div
                        class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-800"
                        >
                            <div>
                                <h3 class="text-sm font-semibold">
                                    Account Funding Receipts
                                </h3>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Sanitized classification and recognition
                                    status. Payer identity and raw provider data
                                    stay hidden.
                                </p>
                            </div>
                            <span class="text-xs text-slate-500">
                                {{
                                    standingHistoryCheckedAt
                                        ? `Last synchronized ${displayTime(
                                              standingHistoryCheckedAt,
                                          )}`
                                        : 'Not synchronized yet'
                                }}
                            </span>
                        </div>
                        <div
                            v-if="standingReceipts.length"
                            class="overflow-x-auto"
                        >
                            <table
                                class="w-full min-w-[52rem] text-left text-sm"
                            >
                                <thead
                                    class="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-900"
                                >
                                    <tr>
                                        <th class="px-4 py-2.5">Reference</th>
                                        <th class="px-4 py-2.5">Amount</th>
                                        <th class="px-4 py-2.5">NetBank</th>
                                        <th class="px-4 py-2.5">Applied</th>
                                        <th class="px-4 py-2.5">Observed</th>
                                        <th class="px-4 py-2.5 text-right">
                                            Control
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="receipt in standingReceipts"
                                        :key="receipt.reference"
                                        class="border-t border-slate-100 dark:border-slate-800"
                                    >
                                        <td
                                            class="px-4 py-3 font-mono text-xs font-semibold"
                                        >
                                            {{ receipt.reference }}
                                        </td>
                                        <td class="px-4 py-3 font-semibold">
                                            {{ receipt.gross_amount }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{
                                                displayLabel(
                                                    receipt.provider_status,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div
                                                v-if="receipt.applied"
                                                class="flex flex-col gap-0.5"
                                            >
                                                <span
                                                    class="font-semibold text-emerald-700 dark:text-emerald-300"
                                                >
                                                    Yes ·
                                                    {{ receipt.applied_amount }}
                                                </span>
                                                <span
                                                    v-if="receipt.provisional"
                                                    class="text-[0.7rem] font-medium text-amber-700 dark:text-amber-300"
                                                >
                                                    Provisional provider status
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-[0.7rem] text-slate-500"
                                                >
                                                    {{
                                                        displayTime(
                                                            receipt.applied_at,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                v-else
                                                class="flex flex-col gap-0.5"
                                            >
                                                <span
                                                    class="font-medium text-slate-600 dark:text-slate-300"
                                                >
                                                    No
                                                </span>
                                                <span
                                                    class="text-[0.7rem] text-slate-500"
                                                >
                                                    {{
                                                        displayLabel(
                                                            receipt.status,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500">
                                            {{
                                                displayTime(receipt.occurred_at)
                                            }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                v-if="receipt.can_approve"
                                                type="button"
                                                class="h-8 rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="
                                                    activeStandingReceiptApproval !==
                                                    null
                                                "
                                                data-testid="approve-standing-funding-receipt"
                                                @click="
                                                    approveStandingFundingReceipt(
                                                        receipt,
                                                    )
                                                "
                                            >
                                                {{
                                                    activeStandingReceiptApproval ===
                                                    receipt.reference
                                                        ? 'Posting…'
                                                        : 'Approve verified credit'
                                                }}
                                            </button>
                                            <span
                                                v-else
                                                class="text-xs text-slate-400"
                                            >
                                                —
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p
                            v-else
                            class="px-4 py-5 text-sm text-slate-500"
                            data-testid="standing-funding-history-empty"
                        >
                            {{
                                standingHistoryCheckedAt
                                    ? 'No persisted incoming receipts were found during the last NetBank synchronization.'
                                    : 'Check NetBank after a human scans and pays the QR. Persisted receipts will remain here after refresh.'
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="standingAddressError"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/20 dark:text-rose-200"
                    role="alert"
                >
                    <span>{{ standingAddressError }}</span>
                    <button
                        v-if="standing_funding_address.available"
                        type="button"
                        class="h-9 rounded-lg border border-rose-300 bg-white px-3 text-xs font-semibold text-rose-800 transition hover:bg-rose-100 dark:border-rose-800 dark:bg-slate-950 dark:text-rose-200 dark:hover:bg-rose-950"
                        @click="openStandingFundingAddress"
                    >
                        Try again
                    </button>
                </div>
            </section>

            <section
                v-show="activeFundingMode === 'pay_code'"
                id="funding-panel-pay_code"
                role="tabpanel"
                aria-labelledby="funding-mode-pay_code"
                class="space-y-4"
                data-testid="cockpit-pay-code-funding"
            >
                <article
                    class="overflow-hidden rounded-2xl border border-emerald-300 bg-white shadow-sm dark:border-emerald-900 dark:bg-slate-900"
                    data-testid="pay-code-funding-primary"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-emerald-100 px-4 py-3 sm:px-5 dark:border-emerald-950"
                    >
                        <div>
                            <h2 class="text-base font-semibold">
                                Fund with Pay Code
                            </h2>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Add an eligible Pay Code directly to Client
                                Funds.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-[0.65rem] font-semibold text-emerald-700 uppercase dark:bg-emerald-950 dark:text-emerald-200"
                        >
                            no provider payout
                        </span>
                    </div>
                    <form
                        class="grid gap-2 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-5"
                        data-testid="pay-code-funding-inspection-form"
                        @submit.prevent="inspectPayCodeFunding"
                    >
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
                            class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-base font-semibold tracking-wide uppercase transition outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-emerald-500 dark:focus:ring-emerald-950"
                        />
                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-wait disabled:opacity-50 dark:bg-emerald-400 dark:text-slate-950 dark:hover:bg-emerald-300"
                            :disabled="
                                payCodeInspectionForm.processing ||
                                payCodeInspectionForm.code.trim() === ''
                            "
                        >
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
                                                ? 'Eligible'
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
                                class="h-11 shrink-0 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-50"
                                :disabled="payCodeFundingClaimForm.processing"
                                data-testid="claim-pay-code-funding"
                                @click="claimPayCodeFunding"
                            >
                                {{
                                    payCodeFundingClaimForm.processing
                                        ? 'Adding…'
                                        : 'Add to Account'
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
                </article>

                <details
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="funding-request-form"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold marker:hidden sm:px-5"
                    >
                        <span>Request a Reviewed Funding Pay Code</span>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
                        >
                            optional review
                        </span>
                    </summary>
                    <form
                        class="border-t border-slate-200 p-4 sm:p-5 dark:border-slate-800"
                        @submit.prevent="submitFundingRequest"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <h2 class="text-base font-semibold">
                                Reviewed Funding Pay Code
                            </h2>
                            <span
                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-[0.65rem] font-semibold text-emerald-700 uppercase dark:bg-emerald-950 dark:text-emerald-200"
                            >
                                review only
                            </span>
                        </div>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                        >
                            <label class="block text-xs font-semibold">
                                Funding source
                                <select
                                    v-model="fundingRequestForm.funding_type"
                                    class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option value="bank_transfer">
                                        Bank transfer
                                    </option>
                                    <option value="cash_handover">
                                        Cash handover
                                    </option>
                                    <option value="precious_metal">
                                        Gold or precious metal
                                    </option>
                                    <option value="jewelry">Jewelry</option>
                                    <option value="vehicle">Vehicle</option>
                                    <option value="other">
                                        Other approved asset
                                    </option>
                                </select>
                            </label>
                            <label class="block text-xs font-semibold">
                                Requested value
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
                                Reference
                                <input
                                    v-model="
                                        fundingRequestForm.external_reference
                                    "
                                    class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    placeholder="Optional"
                                />
                            </label>
                            <label class="block text-xs font-semibold">
                                Date
                                <input
                                    v-model="fundingRequestForm.occurred_on"
                                    type="date"
                                    class="mt-1.5 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                                />
                            </label>
                            <label
                                class="block text-xs font-semibold sm:col-span-2"
                            >
                                Verification details
                                <textarea
                                    v-model="fundingRequestForm.description"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    placeholder="Transfer or asset details"
                                />
                                <span
                                    v-if="fundingRequestForm.errors.description"
                                    class="mt-1 block text-xs text-rose-600"
                                >
                                    {{ fundingRequestForm.errors.description }}
                                </span>
                            </label>
                            <label
                                class="block text-xs font-semibold sm:col-span-2"
                            >
                                Notes
                                <textarea
                                    v-model="fundingRequestForm.requester_notes"
                                    rows="2"
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    placeholder="Optional"
                                />
                            </label>
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
                                        ? 'Submitting…'
                                        : 'Submit for Review'
                                }}
                            </button>
                        </div>
                    </form>
                </details>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="my-funding-requests"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold">
                                Reviewed funding requests
                            </h2>
                        </div>
                        <span class="text-sm font-semibold">
                            {{ fundingRequests.requests.length }}
                        </span>
                    </div>
                    <div
                        v-if="fundingRequests.requests.length"
                        class="mt-4 divide-y divide-slate-200 dark:divide-slate-800"
                    >
                        <div
                            v-for="item in fundingRequests.requests"
                            :key="item.reference"
                            class="grid gap-3 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                        >
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold">
                                        {{ item.type_label }}
                                    </span>
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold uppercase dark:bg-slate-800"
                                    >
                                        {{ displayLabel(item.status) }}
                                    </span>
                                </div>
                                <p
                                    class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                                >
                                    {{ item.requested_value }} ·
                                    {{ item.description }}
                                </p>
                                <p
                                    v-if="item.pay_code"
                                    class="mt-1 text-xs text-emerald-700 dark:text-emerald-300"
                                >
                                    Pay Code
                                    <span class="font-mono font-semibold">
                                        {{ item.pay_code.code }}
                                    </span>
                                    · {{ item.pay_code.amount }} ·
                                    {{ displayLabel(item.pay_code.status) }}
                                </p>
                            </div>
                            <button
                                v-if="item.pay_code?.can_claim"
                                type="button"
                                class="min-h-10 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="
                                    reviewedFundingPayCodeClaimForm.processing
                                "
                                data-testid="claim-reviewed-funding-pay-code"
                                @click="claimReviewedPayCode(item.reference)"
                            >
                                {{
                                    reviewedFundingPayCodeClaimForm.processing
                                        ? 'Claiming…'
                                        : 'Add Pay Code to Account'
                                }}
                            </button>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">
                        No reviewed funding requests yet.
                    </p>
                </article>

                <article
                    v-if="fundingRequests.controls.reviewer"
                    class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm dark:border-amber-950 dark:bg-amber-950/10"
                    data-testid="funding-request-review-queue"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-semibold tracking-[0.16em] text-amber-700 uppercase dark:text-amber-300"
                            >
                                Operator queue
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                Funding Requests requiring control
                            </h2>
                        </div>
                        <span class="font-semibold">
                            {{ fundingRequests.review_queue.length }}
                        </span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="item in fundingRequests.review_queue"
                            :key="item.reference"
                            class="rounded-xl border border-amber-200 bg-white p-4 dark:border-amber-900 dark:bg-slate-900"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p class="text-sm font-semibold">
                                        {{ item.type_label }} ·
                                        {{ item.requested_value }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ item.description }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-amber-100 px-2 py-1 text-[0.65rem] font-semibold uppercase dark:bg-amber-950"
                                >
                                    {{ displayLabel(item.status) }}
                                </span>
                            </div>
                            <div
                                v-if="item.can_prepare"
                                class="mt-4 grid gap-3 md:grid-cols-2"
                            >
                                <label class="text-xs font-semibold">
                                    Recognized value
                                    <input
                                        v-model="fundingReviewAmount"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                    />
                                </label>
                                <label class="text-xs font-semibold">
                                    Independent evidence reference
                                    <input
                                        v-model="
                                            fundingRequestReviewForm.evidence_reference
                                        "
                                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                                        placeholder="Bank match or custody receipt"
                                    />
                                </label>
                                <button
                                    type="button"
                                    class="min-h-10 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 md:col-span-2"
                                    :disabled="
                                        activeFundingRequestReview !== null
                                    "
                                    @click="prepareRequest(item.reference)"
                                >
                                    Record backing and request approval
                                </button>
                            </div>
                            <button
                                v-else-if="item.can_approve"
                                type="button"
                                class="mt-4 min-h-10 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                                :disabled="activeFundingRequestReview !== null"
                                @click="approveRequest(item.reference)"
                            >
                                Approve reserved value and issue code
                            </button>
                            <p
                                v-else-if="item.status === 'awaiting_approval'"
                                class="mt-3 text-xs text-amber-700 dark:text-amber-300"
                            >
                                The maker cannot approve this request. A
                                different configured operator must complete it.
                            </p>
                        </div>
                    </div>
                </article>
            </section>

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
                                class="text-xs font-semibold tracking-[0.16em] text-violet-700 uppercase dark:text-violet-300"
                            >
                                QR Ph lifecycle lab
                            </p>
                            <span
                                class="rounded-full bg-violet-50 px-2 py-1 text-[0.65rem] font-semibold text-violet-700 uppercase dark:bg-violet-950 dark:text-violet-200"
                            >
                                Rollback only
                            </span>
                            <span
                                class="rounded-full bg-slate-100 px-2 py-1 text-[0.65rem] font-semibold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300"
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
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="funding-provider-controls"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 marker:hidden"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                        >
                            Secondary controls
                        </p>
                        <h2 class="mt-0.5 text-sm font-semibold">
                            Providers & safeguards
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
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
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
                                        class="rounded-full px-2 py-1 text-[0.65rem] font-semibold tracking-wide uppercase"
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
                            class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
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
                                    class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
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
                                    class="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"
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
                                                    class="h-8 rounded-lg bg-sky-600 px-3 text-xs font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
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
                        class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        data-testid="cockpit-funding-approval-queue"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-[0.16em] text-indigo-700 uppercase dark:text-indigo-300"
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
                                            class="rounded-full bg-indigo-50 px-2 py-1 text-[0.65rem] font-semibold tracking-wide text-indigo-700 uppercase dark:bg-indigo-950/50 dark:text-indigo-300"
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
                                        class="text-xs font-semibold tracking-[0.16em] text-amber-700 uppercase dark:text-amber-300"
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
                                        class="text-xs font-semibold tracking-[0.16em] text-rose-700 uppercase dark:text-rose-300"
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
