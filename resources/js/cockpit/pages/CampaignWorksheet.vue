<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    ArrowLeft,
    Download,
    LockKeyhole,
    Mail,
    MessageSquare,
    Plus,
    RotateCcw,
    Send,
} from 'lucide-vue-next';
import { index } from '@/routes/x-change/cockpit/campaigns';
import rows from '@/routes/x-change/cockpit/campaigns/rows';
import authorizations from '@/routes/x-change/cockpit/campaigns/authorizations';
import fulfillments from '@/routes/x-change/cockpit/campaigns/fulfillments';
import exports from '@/routes/x-change/cockpit/campaigns/exports';
import deliveries from '@/routes/x-change/cockpit/campaigns/deliveries';
import { show as claimShow } from '@/routes/x-change/claim';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import CockpitCampaignWorksheetBeneficiaries, {
    type CampaignWorksheetBeneficiaryRow,
} from '../components/CockpitCampaignWorksheetBeneficiaries.vue';
import CockpitCampaignImportWorkspace, {
    type CampaignImport,
} from '../components/CockpitCampaignImportWorkspace.vue';
import CockpitCampaignPayCodeExperience from '../components/CockpitCampaignPayCodeExperience.vue';
import { formatAbsoluteTime, formatRelativeTime } from '../utils/dateTime';
import type {
    CockpitHeaderPageProps,
    CockpitInstructionCapabilityReadinessMap,
} from '../types';

type Worksheet = {
    reference: string;
    profile: string;
    name: string;
    currency: string;
    status: string;
    fulfillment_mode: string;
    instruction_blueprint: Record<string, unknown>;
    instruction_blueprint_hash: string | null;
    instruction_blueprint_revision: number;
    rows: CampaignWorksheetBeneficiaryRow[];
};
type Authorization = {
    reference?: string;
    status?: string;
    approval_pay_code?: string | null;
    beneficiary_count?: number;
    principal_minor?: number;
    instruction_summary?: Record<string, unknown>;
};
type Fulfillment = {
    reference: string;
    ordinal: number;
    beneficiary: string;
    amount_minor: number;
    mode: string;
    status: string;
    provider_transfer_reference: string | null;
    pay_code: string | null;
};
type DeliveryAttempt = {
    reference: string;
    channel: string;
    attempt_number: number;
    retry_of_reference: string | null;
    purpose: string;
    beneficiary: string;
    pay_code: string | null;
    status: string;
    safe_error_code: string | null;
    requested_at: string | null;
    can_retry: boolean;
    can_resend: boolean;
};
type Delivery = {
    channels: { sms: boolean; email: boolean };
    attempts: DeliveryAttempt[];
};
type Props = CockpitHeaderPageProps & {
    worksheet: Worksheet;
    imports: CampaignImport[];
    fulfillment_summary: Record<string, number>;
    authorization: Authorization;
    fulfillments: Fulfillment[];
    direct_bank_transfer_enabled: boolean;
    onboarding_otp_required?: boolean;
    delivery: Delivery;
    instruction_capabilities?: CockpitInstructionCapabilityReadinessMap;
    instruction_capability_blockers?: string[];
};
const props = defineProps<Props>();
const form = useForm({
    amount: '',
    name: '',
    mobile: '',
    bank_account: '',
    bank_code: '',
    email: '',
    remarks: '',
    external_reference: '',
    delivery_preference: 'manual',
});
const authorizationForm = useForm({});
const fulfillmentForm = useForm({});
const transferForm = useForm({});
const reconciliationForm = useForm({});
const fallbackForm = useForm({});
const deliveryForm = useForm({});
const resendConfirmation = ref<string | null>(null);
const approvalDeliveryForm = useForm({
    recipient: '',
    request_token: crypto.randomUUID(),
});
const approvalDeliveryChannel = ref<'sms' | 'email'>(
    props.delivery.channels.sms ? 'sms' : 'email',
);
const approvalDeliveryAttempts = computed(() =>
    props.delivery.attempts
        .filter((attempt) => attempt.purpose === 'officer_authorization')
        .slice(0, 3),
);
const peso = (minor: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);
const plannedCount = (): number => props.fulfillment_summary.planned_count ?? 0;
const issuedCount = (): number => props.fulfillment_summary.issued_count ?? 0;
const completedCount = (): number =>
    props.fulfillment_summary.completed_count ?? 0;
const isDraft = (): boolean => props.worksheet.status === 'draft';
const readableLabel = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const isCampaignComplete = computed(
    () =>
        props.worksheet.status === 'authorized' &&
        props.worksheet.rows.length > 0 &&
        completedCount() === props.worksheet.rows.length,
);
const worksheetDisplayStatus = computed(() =>
    isCampaignComplete.value
        ? 'Completed'
        : readableLabel(props.worksheet.status),
);
const hasPendingImportRows = computed(() =>
    props.imports.some((item) => item.unapplied_valid_count > 0),
);
const representativeRow = computed(() => props.worksheet.rows[0]);
const worksheetTotalMinor = computed(() =>
    props.worksheet.rows.reduce((total, row) => total + row.amount_minor, 0),
);
const isPayroll = computed(() => props.worksheet.profile === 'payroll');
const profileLabel = computed(() => (isPayroll.value ? 'Payroll' : 'Ayuda'));
const peopleLabel = computed(() =>
    isPayroll.value ? 'Employees' : 'Beneficiaries',
);
const personLabel = computed(() =>
    isPayroll.value ? 'employee' : 'beneficiary',
);
const valueLabel = computed(() =>
    isPayroll.value ? 'Net Payroll' : 'Assistance Total',
);
const requestedRelativeTime = (value: string | null): string =>
    formatRelativeTime(value, Date.now()) ?? 'Time unavailable';
const fulfillmentReadinessDescription = (): string => {
    const count = plannedCount();

    if (isCampaignComplete.value) {
        return completedCount() === 1
            ? `The ${personLabel.value} payment is complete.`
            : `All ${completedCount()} ${personLabel.value} payments are complete.`;
    }

    if (count === 0 && issuedCount() > 0) {
        return `All ${issuedCount()} ${issuedCount() === 1 ? 'Pay Code is' : 'Pay Codes are'} ready to distribute.`;
    }

    if (count === 0) {
        return `Preparing the approved ${personLabel.value} list.`;
    }

    return (
        count +
        ' ' +
        (count === 1
            ? `${personLabel.value} is`
            : `${personLabel.value}s are`) +
        ' ready for Pay Code issuance.'
    );
};
const add = (): void =>
    form.post(rows.store(props.worksheet.reference).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const authorize = (): void =>
    authorizationForm.post(
        authorizations.store(props.worksheet.reference).url,
        {
            preserveScroll: true,
        },
    );
const issue = (): void =>
    fulfillmentForm.post(
        fulfillments.payCodes.store(props.worksheet.reference).url,
        { preserveScroll: true },
    );
const dispatchTransfers = (): void =>
    transferForm.post(
        fulfillments.bankTransfers.store(props.worksheet.reference).url,
        { preserveScroll: true },
    );
const reconcileTransfers = (): void =>
    reconciliationForm.post(
        fulfillments.bankTransfers.reconciliations.store(
            props.worksheet.reference,
        ).url,
        { preserveScroll: true },
    );
const planFallbacks = (): void =>
    fallbackForm.post(
        fulfillments.fallbacks.store(props.worksheet.reference).url,
        {
            preserveScroll: true,
        },
    );
const deliver = (channel: 'sms' | 'email'): void =>
    deliveryForm.post(
        deliveries.store({ worksheet: props.worksheet.reference, channel }).url,
        { preserveScroll: true },
    );
const retryDelivery = (reference: string): void =>
    deliveryForm.post(
        deliveries.retries.store({
            worksheet: props.worksheet.reference,
            attempt: reference,
        }).url,
        { preserveScroll: true },
    );
const resendDelivery = (reference: string): void =>
    deliveryForm.post(
        deliveries.resends.store({
            worksheet: props.worksheet.reference,
            attempt: reference,
        }).url,
        {
            preserveScroll: true,
            onFinish: () => {
                resendConfirmation.value = null;
            },
        },
    );
const sendApproval = (): void => {
    if (!props.authorization.reference) return;
    approvalDeliveryForm.post(
        authorizations.deliveries.store({
            worksheet: props.worksheet.reference,
            authorization: props.authorization.reference,
            channel: approvalDeliveryChannel.value,
        }).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                approvalDeliveryForm.reset('recipient');
                approvalDeliveryForm.request_token = crypto.randomUUID();
            },
        },
    );
};
</script>

<template>
    <CockpitLayout
        active-navigation="campaigns"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main
            class="mx-auto w-full min-w-0 max-w-7xl space-y-5"
            data-testid="cockpit-campaign-worksheet-page"
        >
            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div>
                    <Link
                        :href="index()"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"
                        ><ArrowLeft class="size-3.5" /> Campaigns</Link
                    >
                    <div
                        class="mt-2 flex min-w-0 flex-col gap-3 2xl:flex-row 2xl:items-end 2xl:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ profileLabel }}
                                </span>
                                <span
                                    :class="
                                        isCampaignComplete
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200'
                                            : 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-200'
                                    "
                                    class="rounded-full px-2 py-0.5 text-[0.65rem] font-semibold"
                                >
                                    {{ worksheetDisplayStatus }}
                                </span>
                            </div>
                            <h1
                                class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ props.worksheet.name }}
                            </h1>
                        </div>
                        <dl
                            class="grid w-full min-w-0 grid-cols-1 gap-2 text-left min-[360px]:grid-cols-3 2xl:w-auto 2xl:min-w-[21rem] 2xl:text-right"
                            data-testid="campaign-worksheet-stats"
                        >
                            <div
                                class="min-w-0 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950"
                            >
                                <dt
                                    class="text-[0.65rem] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ peopleLabel }}
                                </dt>
                                <dd
                                    class="mt-0.5 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ props.worksheet.rows.length }}
                                </dd>
                            </div>
                            <div
                                class="min-w-0 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950"
                            >
                                <dt
                                    class="text-[0.65rem] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    {{ valueLabel }}
                                </dt>
                                <dd
                                    class="mt-0.5 whitespace-nowrap text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ peso(worksheetTotalMinor) }}
                                </dd>
                            </div>
                            <div
                                class="min-w-0 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950"
                            >
                                <dt
                                    class="text-[0.65rem] font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Method
                                </dt>
                                <dd
                                    class="mt-0.5 text-sm font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{
                                        readableLabel(
                                            props.worksheet.fulfillment_mode,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <CockpitCampaignPayCodeExperience
                :worksheet-reference="props.worksheet.reference"
                :worksheet-name="props.worksheet.name"
                :fulfillment-mode="props.worksheet.fulfillment_mode"
                :status="props.worksheet.status"
                :currency="props.worksheet.currency"
                :beneficiary-count="props.worksheet.rows.length"
                :representative-amount-minor="
                    representativeRow?.amount_minor ?? 0
                "
                :representative-recipient="
                    representativeRow?.beneficiary.mobile ??
                    representativeRow?.beneficiary.name ??
                    ''
                "
                :blueprint="props.worksheet.instruction_blueprint"
                :revision="props.worksheet.instruction_blueprint_revision"
                :blueprint-hash="props.worksheet.instruction_blueprint_hash"
                :onboarding-otp-required="props.onboarding_otp_required ?? true"
                :instruction-capabilities="props.instruction_capabilities ?? {}"
            />

            <section
                class="grid min-w-0 gap-5 2xl:grid-cols-[minmax(0,1fr)_24rem]"
                data-testid="campaign-worksheet-recipient-workspace"
            >
                <div
                    class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="font-semibold text-slate-950 dark:text-slate-50"
                                >
                                    {{ peopleLabel }}
                                </h2>
                                <span
                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ worksheetDisplayStatus }}
                                </span>
                            </div>
                            <p
                                v-if="isDraft()"
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{
                                    props.worksheet.rows.length === 0
                                        ? `Add at least one ${personLabel}.`
                                        : hasPendingImportRows
                                          ? 'Finish or discard the pending import.'
                                          : `${props.worksheet.rows.length} ${props.worksheet.rows.length === 1 ? personLabel : `${personLabel}s`} ready for approval.`
                                }}
                            </p>
                        </div>
                        <button
                            v-if="isDraft()"
                            type="button"
                            data-testid="campaign-create-approval-pay-code"
                            :disabled="
                                props.worksheet.rows.length === 0 ||
                                hasPendingImportRows ||
                                (props.instruction_capability_blockers
                                    ?.length ?? 0) > 0 ||
                                authorizationForm.processing
                            "
                            class="inline-flex w-full min-w-0 items-center justify-center gap-2 whitespace-normal rounded-xl bg-slate-950 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto dark:bg-slate-100 dark:text-slate-950"
                            @click="authorize"
                        >
                            <LockKeyhole class="size-4" />
                            {{
                                authorizationForm.processing
                                    ? 'Preparing…'
                                    : 'Request Approval'
                            }}
                        </button>
                    </div>
                    <div
                        v-if="
                            isDraft() &&
                            (props.instruction_capability_blockers?.length ??
                                0) > 0
                        "
                        class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-100"
                        data-testid="campaign-approval-capability-warning"
                    >
                        Update the {{ profileLabel }} experience before
                        requesting approval:
                        {{ props.instruction_capability_blockers?.join(' ') }}
                    </div>
                    <CockpitCampaignWorksheetBeneficiaries
                        :rows="props.worksheet.rows"
                        :draft="isDraft()"
                    />
                </div>

                <form
                    v-if="isDraft()"
                    class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="campaign-add-recipient-panel"
                    @submit.prevent="add"
                >
                    <div class="flex items-center gap-2">
                        <Plus class="size-4 text-slate-500" />
                        <div>
                            <h2
                                class="font-semibold text-slate-950 dark:text-slate-50"
                            >
                                Add {{ isPayroll ? 'Employee' : 'Beneficiary' }}
                            </h2>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <label
                            class="grid min-w-0 gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >Amount
                            <span
                                class="flex overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:ring-2 focus-within:ring-slate-400 dark:border-slate-700 dark:bg-slate-950"
                                ><span
                                    class="grid place-items-center border-r border-slate-200 px-3 text-slate-500 dark:border-slate-800"
                                    >₱</span
                                ><input
                                    v-model="form.amount"
                                    inputmode="decimal"
                                    placeholder="0.00"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-slate-950 outline-none dark:text-white" /></span></label
                        ><label
                            class="grid min-w-0 gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >Name
                            <input
                                v-model="form.name"
                                class="w-full min-w-0 rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label
                        ><label
                            class="grid min-w-0 gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >Destination
                            <input
                                v-model="form.mobile"
                                placeholder="Mobile number"
                                class="w-full min-w-0 rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /><input
                                v-model="form.bank_account"
                                placeholder="Bank account"
                                class="w-full min-w-0 rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /><input
                                v-model="form.bank_code"
                                placeholder="Bank name or code"
                                class="w-full min-w-0 rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label
                        ><label
                            class="grid min-w-0 gap-1 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >Remarks
                            <textarea
                                v-model="form.remarks"
                                rows="2"
                                class="w-full min-w-0 rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </label>
                    </div>
                    <p
                        v-if="
                            form.errors.mobile ||
                            form.errors.bank_account ||
                            form.errors.amount
                        "
                        class="mt-2 text-xs text-rose-600 dark:text-rose-300"
                    >
                        {{
                            form.errors.amount ||
                            form.errors.mobile ||
                            form.errors.bank_account
                        }}
                    </p>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950"
                    >
                        {{
                            form.processing
                                ? 'Adding…'
                                : `Add ${isPayroll ? 'Employee' : 'Beneficiary'}`
                        }}
                    </button>
                    <p
                        class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400"
                    >
                        Added to this draft only.
                    </p>
                </form>
            </section>

            <CockpitCampaignImportWorkspace
                v-if="isDraft()"
                :worksheet-reference="props.worksheet.reference"
                :fulfillment-mode="props.worksheet.fulfillment_mode"
                :imports="props.imports"
            />

            <section
                v-if="
                    props.worksheet.status === 'authorized' &&
                    props.worksheet.fulfillment_mode === 'pay_code_distribution'
                "
                data-testid="campaign-fulfillment-readiness"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            Issuance
                        </p>
                        <h2
                            class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                isCampaignComplete
                                    ? 'Campaign Complete'
                                    : plannedCount() > 0
                                      ? `${peopleLabel} Ready`
                                      : issuedCount() > 0
                                        ? 'Pay Codes Issued'
                                        : 'Preparing Pay Codes'
                            }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{ fulfillmentReadinessDescription() }}
                        </p>
                    </div>
                    <button
                        v-if="plannedCount() > 0"
                        type="button"
                        :disabled="fulfillmentForm.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950"
                        @click="issue"
                    >
                        <Send class="size-4" />
                        {{
                            fulfillmentForm.processing
                                ? 'Issuing…'
                                : 'Issue Next 100'
                        }}
                    </button>
                </div>
            </section>
            <section
                v-if="
                    props.worksheet.status === 'authorized' &&
                    props.worksheet.fulfillment_mode === 'direct_bank_transfer'
                "
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            Bank Transfers
                        </p>
                        <h2
                            class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                props.direct_bank_transfer_enabled
                                    ? 'Transfers Ready'
                                    : 'Bank Transfers Unavailable'
                            }}
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            {{
                                props.direct_bank_transfer_enabled
                                    ? 'Send or check the next authorized transfer batch.'
                                    : 'This batch remains authorized. Transfers can begin when NetBank is enabled.'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="
                                (props.fulfillment_summary.fallback_count ??
                                    0) > 0
                            "
                            type="button"
                            :disabled="fulfillmentForm.processing"
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold dark:border-slate-700"
                            @click="issue"
                        >
                            {{
                                fulfillmentForm.processing
                                    ? 'Issuing…'
                                    : 'Issue Planned Fallbacks'
                            }}</button
                        ><button
                            type="button"
                            :disabled="fallbackForm.processing"
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold dark:border-slate-700"
                            @click="planFallbacks"
                        >
                            {{
                                fallbackForm.processing
                                    ? 'Planning…'
                                    : 'Plan Pay Code Fallbacks'
                            }}</button
                        ><template v-if="props.direct_bank_transfer_enabled"
                            ><button
                                type="button"
                                :disabled="reconciliationForm.processing"
                                class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold dark:border-slate-700"
                                @click="reconcileTransfers"
                            >
                                {{
                                    reconciliationForm.processing
                                        ? 'Checking…'
                                        : 'Check NetBank'
                                }}</button
                            ><button
                                type="button"
                                :disabled="transferForm.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950"
                                @click="dispatchTransfers"
                            >
                                <Send class="size-4" />
                                {{
                                    transferForm.processing
                                        ? 'Dispatching…'
                                        : 'Dispatch Next 100'
                                }}
                            </button></template
                        >
                    </div>
                </div>
            </section>
            <section
                v-if="props.worksheet.status === 'authorized'"
                class="grid grid-cols-2 gap-3 sm:grid-cols-5"
            >
                <div
                    v-for="[label, value] in [
                        [
                            'Planned',
                            props.fulfillment_summary.planned_count ?? 0,
                        ],
                        ['Issued', props.fulfillment_summary.issued_count ?? 0],
                        [
                            'Completed',
                            props.fulfillment_summary.completed_count ?? 0,
                        ],
                        [
                            'Provider Ready',
                            props.fulfillment_summary.provider_ready_count ?? 0,
                        ],
                        [
                            'Pay Code Fallback',
                            props.fulfillment_summary.fallback_count ?? 0,
                        ],
                    ]"
                    :key="label"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ label }}
                    </p>
                    <p
                        class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50"
                    >
                        {{ value }}
                    </p>
                </div>
            </section>
            <section
                v-if="
                    props.worksheet.status === 'authorized' && issuedCount() > 0
                "
                data-testid="campaign-delivery-controls"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                >
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            Distribution
                        </p>
                        <h2
                            class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Send Pay Codes
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Download the list or send through an enabled
                            channel.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="
                                exports.payCodes(props.worksheet.reference).url
                            "
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200"
                            ><Download class="size-4" /> Download CSV</a
                        >
                        <button
                            type="button"
                            :disabled="
                                !props.delivery.channels.sms ||
                                deliveryForm.processing
                            "
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-700 dark:text-slate-200"
                            @click="deliver('sms')"
                        >
                            <MessageSquare class="size-4" />
                            {{
                                props.delivery.channels.sms
                                    ? 'Send SMS'
                                    : 'SMS Disabled'
                            }}
                        </button>
                        <button
                            type="button"
                            :disabled="
                                !props.delivery.channels.email ||
                                deliveryForm.processing
                            "
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-700 dark:text-slate-200"
                            @click="deliver('email')"
                        >
                            <Mail class="size-4" />
                            {{
                                props.delivery.channels.email
                                    ? 'Send Email'
                                    : 'Email Disabled'
                            }}
                        </button>
                    </div>
                </div>
                <div
                    v-if="props.delivery.attempts.length === 0"
                    class="px-4 py-5 text-sm text-slate-500 dark:text-slate-400"
                >
                    Nothing sent yet.
                </div>
                <div
                    v-else
                    class="divide-y divide-slate-200 dark:divide-slate-800"
                >
                    <article
                        v-for="attempt in props.delivery.attempts"
                        :key="attempt.reference"
                        class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"
                    >
                        <div>
                            <p
                                class="font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ attempt.beneficiary }}
                            </p>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ attempt.channel.toUpperCase() }} · attempt
                                {{ attempt.attempt_number
                                }}<template v-if="attempt.pay_code">
                                    · {{ attempt.pay_code }}</template
                                >
                                <template v-if="attempt.requested_at">
                                    ·
                                    <time
                                        :datetime="attempt.requested_at"
                                        :title="
                                            formatAbsoluteTime(
                                                attempt.requested_at,
                                            )
                                        "
                                        >{{
                                            requestedRelativeTime(
                                                attempt.requested_at,
                                            )
                                        }}</time
                                    >
                                </template>
                            </p>
                        </div>
                        <span
                            class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >{{ attempt.status }}</span
                        >
                        <div
                            class="flex flex-wrap items-center justify-end gap-2"
                        >
                            <button
                                v-if="attempt.can_retry"
                                type="button"
                                :disabled="deliveryForm.processing"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-950 disabled:opacity-50 dark:text-slate-300 dark:hover:text-white"
                                @click="retryDelivery(attempt.reference)"
                            >
                                <RotateCcw class="size-3.5" /> Retry
                            </button>
                            <button
                                v-if="
                                    attempt.can_resend &&
                                    resendConfirmation !== attempt.reference
                                "
                                type="button"
                                :disabled="deliveryForm.processing"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-400 hover:text-sky-700 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-sky-600 dark:hover:text-sky-300"
                                :aria-label="`Resend ${attempt.channel.toUpperCase()} for ${attempt.pay_code ?? attempt.beneficiary}`"
                                @click="resendConfirmation = attempt.reference"
                            >
                                <Send class="size-3.5" /> Resend
                            </button>
                            <template
                                v-if="
                                    attempt.can_resend &&
                                    resendConfirmation === attempt.reference
                                "
                            >
                                <button
                                    type="button"
                                    :disabled="deliveryForm.processing"
                                    class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-700 disabled:opacity-50"
                                    @click="resendDelivery(attempt.reference)"
                                >
                                    <Send class="size-3.5" /> Confirm Resend
                                </button>
                                <button
                                    type="button"
                                    :disabled="deliveryForm.processing"
                                    class="text-xs font-semibold text-slate-500 hover:text-slate-900 disabled:opacity-50 dark:text-slate-400 dark:hover:text-white"
                                    @click="resendConfirmation = null"
                                >
                                    Cancel
                                </button>
                            </template>
                        </div>
                    </article>
                </div>
            </section>
            <section
                v-if="
                    props.authorization.status === 'awaiting_officer' &&
                    props.authorization.approval_pay_code
                "
                data-testid="campaign-approval-delivery"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/70 dark:bg-amber-950/30"
            >
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-amber-800 dark:text-amber-200"
                >
                    Approval
                </p>
                <div
                    class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="font-semibold text-slate-950 dark:text-slate-50"
                        >
                            Officer Approval Required
                        </h2>
                        <p
                            class="mt-1 text-sm text-slate-600 dark:text-slate-300"
                        >
                            <span class="font-semibold">{{
                                props.authorization.approval_pay_code
                            }}</span>
                            · {{ props.worksheet.rows.length }}
                            {{
                                props.worksheet.rows.length === 1
                                    ? personLabel
                                    : `${personLabel}s`
                            }}. The officer must sign in to approve this batch.
                        </p>
                    </div>
                    <Link
                        :href="claimShow(props.authorization.approval_pay_code)"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-950"
                        >Open Approval Pay Code</Link
                    >
                </div>
                <form
                    class="mt-4 grid gap-2 border-t border-amber-200 pt-4 sm:grid-cols-[auto_minmax(12rem,1fr)_auto] dark:border-amber-900/70"
                    @submit.prevent="sendApproval"
                >
                    <div
                        class="inline-flex rounded-xl border border-amber-300 bg-white p-1 dark:border-amber-800 dark:bg-slate-950"
                    >
                        <button
                            type="button"
                            :disabled="!props.delivery.channels.sms"
                            :class="
                                approvalDeliveryChannel === 'sms'
                                    ? 'bg-slate-950 text-white dark:bg-slate-100 dark:text-slate-950'
                                    : 'text-slate-600 dark:text-slate-300'
                            "
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40"
                            @click="approvalDeliveryChannel = 'sms'"
                        >
                            <MessageSquare class="size-3.5" /> SMS
                        </button>
                        <button
                            type="button"
                            :disabled="!props.delivery.channels.email"
                            :class="
                                approvalDeliveryChannel === 'email'
                                    ? 'bg-slate-950 text-white dark:bg-slate-100 dark:text-slate-950'
                                    : 'text-slate-600 dark:text-slate-300'
                            "
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40"
                            @click="approvalDeliveryChannel = 'email'"
                        >
                            <Mail class="size-3.5" /> Email
                        </button>
                    </div>
                    <input
                        v-model="approvalDeliveryForm.recipient"
                        :type="
                            approvalDeliveryChannel === 'email'
                                ? 'email'
                                : 'tel'
                        "
                        :placeholder="
                            approvalDeliveryChannel === 'email'
                                ? 'Officer email'
                                : 'Officer mobile'
                        "
                        :disabled="
                            !props.delivery.channels[approvalDeliveryChannel]
                        "
                        class="min-w-0 rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400 disabled:opacity-50 dark:border-amber-800 dark:bg-slate-950 dark:text-white"
                    />
                    <button
                        type="submit"
                        :disabled="
                            approvalDeliveryForm.processing ||
                            !approvalDeliveryForm.recipient ||
                            !props.delivery.channels[approvalDeliveryChannel]
                        "
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950"
                    >
                        <Send class="size-4" />
                        {{
                            approvalDeliveryForm.processing
                                ? 'Queueing…'
                                : 'Send To Officer'
                        }}
                    </button>
                    <p
                        v-if="approvalDeliveryForm.errors.recipient"
                        class="text-xs text-rose-700 sm:col-start-2 dark:text-rose-300"
                    >
                        {{ approvalDeliveryForm.errors.recipient }}
                    </p>
                </form>
                <div
                    v-if="approvalDeliveryAttempts.length"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <span
                        v-for="attempt in approvalDeliveryAttempts"
                        :key="attempt.reference"
                        class="rounded-full border border-amber-300 bg-white/80 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600 dark:border-amber-800 dark:bg-slate-950/70 dark:text-slate-300"
                        >{{ attempt.channel }} · {{ attempt.status }}</span
                    >
                </div>
                <p
                    v-if="
                        !props.delivery.channels.sms &&
                        !props.delivery.channels.email
                    "
                    class="mt-2 text-xs text-amber-800 dark:text-amber-200"
                >
                    SMS and email delivery are disabled by runtime
                    configuration. You can still share the code or link
                    manually.
                </p>
            </section>
            <section
                v-if="props.fulfillments.length > 0"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                >
                    <div>
                        <p
                            class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            Results
                        </p>
                        <h2
                            class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{
                                isPayroll
                                    ? 'Employee Results'
                                    : 'Beneficiary Results'
                            }}
                        </h2>
                    </div>
                    <a
                        :href="exports.results(props.worksheet.reference).url"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200"
                        ><Download class="size-4" /> Export All Results</a
                    >
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    <article
                        v-for="item in props.fulfillments"
                        :key="item.reference"
                        class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"
                    >
                        <div>
                            <p
                                class="font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ item.ordinal }} · {{ item.beneficiary }}
                            </p>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ item.mode }} · {{ item.status
                                }}<template
                                    v-if="item.provider_transfer_reference"
                                >
                                    ·
                                    {{
                                        item.provider_transfer_reference
                                    }}</template
                                ><template v-else-if="item.pay_code">
                                    · {{ item.pay_code }}</template
                                >
                            </p>
                        </div>
                        <p
                            class="text-sm font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ peso(item.amount_minor) }}
                        </p>
                        <span
                            class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >{{ item.status }}</span
                        >
                    </article>
                </div>
            </section>
        </main>
    </CockpitLayout>
</template>
