<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, Download, LockKeyhole, Mail, MessageSquare, Plus, RotateCcw, Send } from 'lucide-vue-next';
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
import CockpitCampaignImportWorkspace, { type CampaignImport } from '../components/CockpitCampaignImportWorkspace.vue';
import type { CockpitHeaderPageProps } from '../types';

type Worksheet = { reference: string; profile: string; name: string; status: string; fulfillment_mode: string; rows: CampaignWorksheetBeneficiaryRow[] };
type Authorization = { reference?: string; status?: string; approval_pay_code?: string | null; beneficiary_count?: number; principal_minor?: number };
type Fulfillment = { reference: string; ordinal: number; beneficiary: string; amount_minor: number; mode: string; status: string; provider_transfer_reference: string | null; pay_code: string | null };
type DeliveryAttempt = { reference: string; channel: string; attempt_number: number; retry_of_reference: string | null; purpose: string; beneficiary: string; pay_code: string | null; status: string; safe_error_code: string | null; requested_at: string | null; can_retry: boolean };
type Delivery = { channels: { sms: boolean; email: boolean }; attempts: DeliveryAttempt[] };
type Props = CockpitHeaderPageProps & { worksheet: Worksheet; imports: CampaignImport[]; fulfillment_summary: Record<string, number>; authorization: Authorization; fulfillments: Fulfillment[]; direct_bank_transfer_enabled: boolean; delivery: Delivery };
const props = defineProps<Props>();
const form = useForm({ amount: '', name: '', mobile: '', bank_account: '', bank_code: '', email: '', remarks: '', external_reference: '', delivery_preference: 'manual' });
const authorizationForm = useForm({});
const fulfillmentForm = useForm({});
const transferForm = useForm({});
const reconciliationForm = useForm({});
const fallbackForm = useForm({});
const deliveryForm = useForm({});
const approvalDeliveryForm = useForm({ recipient: '', request_token: crypto.randomUUID() });
const approvalDeliveryChannel = ref<'sms' | 'email'>(props.delivery.channels.sms ? 'sms' : 'email');
const approvalDeliveryAttempts = computed(() => props.delivery.attempts.filter((attempt) => attempt.purpose === 'officer_authorization').slice(0, 3));
const peso = (minor: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(minor / 100);
const plannedCount = (): number => props.fulfillment_summary.planned_count ?? 0;
const issuedCount = (): number => props.fulfillment_summary.issued_count ?? 0;
const isDraft = (): boolean => props.worksheet.status === 'draft';
const hasPendingImportRows = computed(() => props.imports.some((item) => item.unapplied_valid_count > 0));
const fulfillmentReadinessDescription = (): string => {
    const count = plannedCount();

    if (count === 0 && issuedCount() > 0) {
        return `All ${issuedCount()} ${issuedCount() === 1 ? 'beneficiary Pay Code has' : 'beneficiary Pay Codes have'} been issued. Export the list when you are ready to distribute it.`;
    }

    if (count === 0) {
        return 'The authorized worksheet is preparing its beneficiary issuance plan. No Pay Codes, delivery, or bank transfers have started.';
    }

    return count + ' ' + (count === 1 ? 'beneficiary is' : 'beneficiaries are') + ' ready for explicit issuance. No Pay Codes, delivery, or bank transfers have started.';
};
const add = (): void => form.post(rows.store(props.worksheet.reference), { preserveScroll: true, onSuccess: () => form.reset() });
const authorize = (): void => authorizationForm.post(authorizations.store(props.worksheet.reference), { preserveScroll: true });
const issue = (): void => fulfillmentForm.post(fulfillments.payCodes.store(props.worksheet.reference), { preserveScroll: true });
const dispatchTransfers = (): void => transferForm.post(fulfillments.bankTransfers.store(props.worksheet.reference), { preserveScroll: true });
const reconcileTransfers = (): void => reconciliationForm.post(fulfillments.bankTransfers.reconciliations.store(props.worksheet.reference), { preserveScroll: true });
const planFallbacks = (): void => fallbackForm.post(fulfillments.fallbacks.store(props.worksheet.reference), { preserveScroll: true });
const deliver = (channel: 'sms' | 'email'): void => deliveryForm.post(deliveries.store({ worksheet: props.worksheet.reference, channel }), { preserveScroll: true });
const retryDelivery = (reference: string): void => deliveryForm.post(deliveries.retries.store({ worksheet: props.worksheet.reference, attempt: reference }), { preserveScroll: true });
const sendApproval = (): void => {
    if (!props.authorization.reference) return;
    approvalDeliveryForm.post(authorizations.deliveries.store({
        worksheet: props.worksheet.reference,
        authorization: props.authorization.reference,
        channel: approvalDeliveryChannel.value,
    }).url, {
        preserveScroll: true,
        onSuccess: () => {
            approvalDeliveryForm.reset('recipient');
            approvalDeliveryForm.request_token = crypto.randomUUID();
        },
    });
};
</script>

<template>
    <CockpitLayout active-navigation="campaigns" :cockpit-header-read-model="props.cockpit_header_read_model">
        <main class="mx-auto max-w-7xl space-y-5" data-testid="cockpit-campaign-worksheet-page">
            <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <Link :href="index()" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"><ArrowLeft class="size-3.5" /> Campaigns</Link>
                    <p class="mt-2 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ props.worksheet.profile }} worksheet</p>
                    <h1 class="mt-0.5 text-xl font-semibold text-slate-950 dark:text-slate-50">{{ props.worksheet.name }}</h1>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-2 text-right dark:bg-slate-950"><p class="text-xs text-slate-500 dark:text-slate-400">Beneficiaries</p><p class="text-lg font-semibold text-slate-950 dark:text-slate-50">{{ props.worksheet.rows.length }}</p></div>
            </section>

            <CockpitCampaignImportWorkspace
                v-if="isDraft()"
                :worksheet-reference="props.worksheet.reference"
                :fulfillment-mode="props.worksheet.fulfillment_mode"
                :imports="props.imports"
            />

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ isDraft() ? 'Draft Beneficiaries' : 'Authorized Beneficiaries' }}</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ isDraft() ? 'Private Worksheet' : 'Authorized Worksheet' }}</h2></div>
                    <CockpitCampaignWorksheetBeneficiaries
                        :rows="props.worksheet.rows"
                        :draft="isDraft()"
                    />
                </div>

                <form v-if="isDraft()" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="add">
                    <div class="flex items-center gap-2"><Plus class="size-4 text-slate-500" /><div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Add Beneficiary</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">Recipient Details</h2></div></div>
                    <div class="mt-4 grid gap-3"><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Amount <span class="flex overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:ring-2 focus-within:ring-slate-400 dark:border-slate-700 dark:bg-slate-950"><span class="grid place-items-center border-r border-slate-200 px-3 text-slate-500 dark:border-slate-800">₱</span><input v-model="form.amount" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-slate-950 outline-none dark:text-white" /></span></label><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Name <input v-model="form.name" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Mobile Or Bank Destination <input v-model="form.mobile" placeholder="Mobile" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /><input v-model="form.bank_account" placeholder="Bank account" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /><input v-model="form.bank_code" placeholder="Bank code (for NetBank transfer)" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Remarks <textarea v-model="form.remarks" rows="2" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label></div>
                    <p v-if="form.errors.mobile || form.errors.bank_account || form.errors.amount" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.amount || form.errors.mobile || form.errors.bank_account }}</p>
                    <button type="submit" :disabled="form.processing" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950">{{ form.processing ? 'Adding…' : 'Add Beneficiary' }}</button>
                    <p class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400">Draft only. This does not authorize, issue, deliver, or transfer funds.</p>
                </form>
            </section>

            <section v-if="props.worksheet.status === 'draft'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-2"><LockKeyhole class="size-4 text-slate-500" /><div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Officer Authorization</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">Freeze And Create Approval Pay Code</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ hasPendingImportRows ? 'Add or discard the ready imported rows first.' : 'Locks this worksheet and creates one zero-value Settlement Pay Code. It does not issue, deliver, or transfer beneficiary funds.' }}</p></div></div><button type="button" :disabled="props.worksheet.rows.length === 0 || hasPendingImportRows || authorizationForm.processing" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950" @click="authorize"><LockKeyhole class="size-4" /> {{ authorizationForm.processing ? 'Creating…' : 'Create Approval Pay Code' }}</button></div>
            </section>
            <section v-if="props.worksheet.status === 'authorized' && props.worksheet.fulfillment_mode === 'pay_code_distribution'" data-testid="campaign-fulfillment-readiness" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Beneficiary Fulfillment</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ plannedCount() > 0 ? 'Beneficiaries Ready To Issue' : issuedCount() > 0 ? 'Pay Codes Issued' : 'Beneficiary Fulfillment Is Being Prepared' }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ fulfillmentReadinessDescription() }}</p></div><button v-if="plannedCount() > 0" type="button" :disabled="fulfillmentForm.processing" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950" @click="issue"><Send class="size-4" /> {{ fulfillmentForm.processing ? 'Issuing…' : 'Issue Next 100' }}</button></div></section>
            <section v-if="props.worksheet.status === 'authorized' && props.worksheet.fulfillment_mode === 'direct_bank_transfer'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">NetBank Fulfillment</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">{{ props.direct_bank_transfer_enabled ? 'Dispatch Next 100 Transfers' : 'Direct Bank Transfer Is Not Enabled' }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ props.direct_bank_transfer_enabled ? 'Checking never re-dispatches a transfer.' : 'This authorized worksheet remains intact. Enable the explicit NetBank runtime gate before any provider transfer controls are available.' }}</p></div><div class="flex flex-wrap gap-2"><button v-if="(props.fulfillment_summary.fallback_count ?? 0) > 0" type="button" :disabled="fulfillmentForm.processing" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold dark:border-slate-700" @click="issue">{{ fulfillmentForm.processing ? 'Issuing…' : 'Issue Planned Fallbacks' }}</button><button type="button" :disabled="fallbackForm.processing" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold dark:border-slate-700" @click="planFallbacks">{{ fallbackForm.processing ? 'Planning…' : 'Plan Pay Code Fallbacks' }}</button><template v-if="props.direct_bank_transfer_enabled"><button type="button" :disabled="reconciliationForm.processing" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold dark:border-slate-700" @click="reconcileTransfers">{{ reconciliationForm.processing ? 'Checking…' : 'Check NetBank' }}</button><button type="button" :disabled="transferForm.processing" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950" @click="dispatchTransfers"><Send class="size-4" /> {{ transferForm.processing ? 'Dispatching…' : 'Dispatch Next 100' }}</button></template></div></div></section>
            <section v-if="props.worksheet.status === 'authorized'" class="grid grid-cols-2 gap-3 sm:grid-cols-4"><div v-for="[label, value] in [['Planned', props.fulfillment_summary.planned_count ?? 0], ['Issued', props.fulfillment_summary.issued_count ?? 0], ['Provider Ready', props.fulfillment_summary.provider_ready_count ?? 0], ['Pay Code Fallback', props.fulfillment_summary.fallback_count ?? 0]]" :key="label" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900"><p class="text-xs text-slate-500 dark:text-slate-400">{{ label }}</p><p class="mt-1 text-lg font-semibold text-slate-950 dark:text-slate-50">{{ value }}</p></div></section>
            <section v-if="props.worksheet.status === 'authorized' && issuedCount() > 0" data-testid="campaign-delivery-controls" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Delivery</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">Choose How To Distribute</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Issuance never sends messages. Each action below is explicit and recorded.</p></div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="exports.payCodes(props.worksheet.reference).url" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200"><Download class="size-4" /> Download CSV</a>
                        <button type="button" :disabled="!props.delivery.channels.sms || deliveryForm.processing" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-700 dark:text-slate-200" @click="deliver('sms')"><MessageSquare class="size-4" /> {{ props.delivery.channels.sms ? 'Send SMS' : 'SMS Disabled' }}</button>
                        <button type="button" :disabled="!props.delivery.channels.email || deliveryForm.processing" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-45 dark:border-slate-700 dark:text-slate-200" @click="deliver('email')"><Mail class="size-4" /> {{ props.delivery.channels.email ? 'Send Email' : 'Email Disabled' }}</button>
                    </div>
                </div>
                <div v-if="props.delivery.attempts.length === 0" class="px-4 py-5 text-sm text-slate-500 dark:text-slate-400">No delivery attempts yet.</div>
                <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                    <article v-for="attempt in props.delivery.attempts" :key="attempt.reference" class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center">
                        <div><p class="font-semibold text-slate-950 dark:text-slate-50">{{ attempt.beneficiary }}</p><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ attempt.channel.toUpperCase() }} · attempt {{ attempt.attempt_number }}<template v-if="attempt.pay_code"> · {{ attempt.pay_code }}</template></p></div>
                        <span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ attempt.status }}</span>
                        <button v-if="attempt.can_retry" type="button" :disabled="deliveryForm.processing" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-950 disabled:opacity-50 dark:text-slate-300 dark:hover:text-white" @click="retryDelivery(attempt.reference)"><RotateCcw class="size-3.5" /> Retry</button>
                    </article>
                </div>
            </section>
            <section v-if="props.authorization.status === 'awaiting_officer' && props.authorization.approval_pay_code" data-testid="campaign-approval-delivery" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/70 dark:bg-amber-950/30">
                <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-amber-800 dark:text-amber-200">Officer Authorization</p>
                <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="font-semibold text-slate-950 dark:text-slate-50">Approval Pay Code Ready</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300"><span class="font-semibold">{{ props.authorization.approval_pay_code }}</span> authorizes this frozen beneficiary list. The officer must sign in and review it.</p></div>
                    <Link :href="claimShow(props.authorization.approval_pay_code)" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-950">Open Approval Pay Code</Link>
                </div>
                <form class="mt-4 grid gap-2 border-t border-amber-200 pt-4 sm:grid-cols-[auto_minmax(12rem,1fr)_auto] dark:border-amber-900/70" @submit.prevent="sendApproval">
                    <div class="inline-flex rounded-xl border border-amber-300 bg-white p-1 dark:border-amber-800 dark:bg-slate-950">
                        <button type="button" :disabled="!props.delivery.channels.sms" :class="approvalDeliveryChannel === 'sms' ? 'bg-slate-950 text-white dark:bg-slate-100 dark:text-slate-950' : 'text-slate-600 dark:text-slate-300'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40" @click="approvalDeliveryChannel = 'sms'"><MessageSquare class="size-3.5" /> SMS</button>
                        <button type="button" :disabled="!props.delivery.channels.email" :class="approvalDeliveryChannel === 'email' ? 'bg-slate-950 text-white dark:bg-slate-100 dark:text-slate-950' : 'text-slate-600 dark:text-slate-300'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40" @click="approvalDeliveryChannel = 'email'"><Mail class="size-3.5" /> Email</button>
                    </div>
                    <input v-model="approvalDeliveryForm.recipient" :type="approvalDeliveryChannel === 'email' ? 'email' : 'tel'" :placeholder="approvalDeliveryChannel === 'email' ? 'Officer email' : 'Officer mobile'" :disabled="!props.delivery.channels[approvalDeliveryChannel]" class="min-w-0 rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400 disabled:opacity-50 dark:border-amber-800 dark:bg-slate-950 dark:text-white" />
                    <button type="submit" :disabled="approvalDeliveryForm.processing || !approvalDeliveryForm.recipient || !props.delivery.channels[approvalDeliveryChannel]" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950"><Send class="size-4" /> {{ approvalDeliveryForm.processing ? 'Queueing…' : 'Send To Officer' }}</button>
                    <p v-if="approvalDeliveryForm.errors.recipient" class="text-xs text-rose-700 sm:col-start-2 dark:text-rose-300">{{ approvalDeliveryForm.errors.recipient }}</p>
                </form>
                <div v-if="approvalDeliveryAttempts.length" class="mt-3 flex flex-wrap gap-2">
                    <span v-for="attempt in approvalDeliveryAttempts" :key="attempt.reference" class="rounded-full border border-amber-300 bg-white/80 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600 dark:border-amber-800 dark:bg-slate-950/70 dark:text-slate-300">{{ attempt.channel }} · {{ attempt.status }}</span>
                </div>
                <p v-if="!props.delivery.channels.sms && !props.delivery.channels.email" class="mt-2 text-xs text-amber-800 dark:text-amber-200">SMS and email delivery are disabled by runtime configuration. You can still share the code or link manually.</p>
            </section>
            <section v-if="props.fulfillments.length > 0" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"><div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Beneficiary Outcomes</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">First 100 Results</h2></div><a :href="exports.results(props.worksheet.reference).url" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200"><Download class="size-4" /> Export All Results</a></div><div class="divide-y divide-slate-200 dark:divide-slate-800"><article v-for="item in props.fulfillments" :key="item.reference" class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"><div><p class="font-semibold text-slate-950 dark:text-slate-50">{{ item.ordinal }} · {{ item.beneficiary }}</p><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ item.mode }} · {{ item.status }}<template v-if="item.provider_transfer_reference"> · {{ item.provider_transfer_reference }}</template><template v-else-if="item.pay_code"> · {{ item.pay_code }}</template></p></div><p class="text-sm font-semibold text-slate-950 dark:text-slate-50">{{ peso(item.amount_minor) }}</p><span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ item.status }}</span></article></div></section>

        </main>
    </CockpitLayout>
</template>
