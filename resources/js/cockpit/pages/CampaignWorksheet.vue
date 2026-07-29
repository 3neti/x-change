<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Users } from 'lucide-vue-next';
import { index } from '@/routes/x-change/cockpit/campaigns';
import rows from '@/routes/x-change/cockpit/campaigns/rows';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderPageProps } from '../types';

type Row = { reference: string; ordinal: number; beneficiary: Record<string, string>; amount_minor: number; delivery_preference: string; status: string };
type Worksheet = { reference: string; profile: string; name: string; status: string; fulfillment_mode: string; rows: Row[] };
type Props = CockpitHeaderPageProps & { worksheet: Worksheet };
const props = defineProps<Props>();
const form = useForm({ amount_minor: null as number | null, name: '', mobile: '', bank_account: '', email: '', remarks: '', external_reference: '', delivery_preference: 'manual' });
const peso = (minor: number) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(minor / 100);
const add = (): void => form.post(rows.store(props.worksheet.reference), { preserveScroll: true, onSuccess: () => form.reset() });
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

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Draft Beneficiaries</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">Private Worksheet</h2></div>
                    <div v-if="props.worksheet.rows.length === 0" class="flex min-h-64 flex-col items-center justify-center text-center"><Users class="size-8 text-slate-300 dark:text-slate-700" /><p class="mt-3 font-semibold text-slate-950 dark:text-slate-50">No beneficiaries yet</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add the first recipient from the form.</p></div>
                    <div v-else class="divide-y divide-slate-200 dark:divide-slate-800"><article v-for="row in props.worksheet.rows" :key="row.reference" class="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"><div><p class="font-semibold text-slate-950 dark:text-slate-50">{{ row.beneficiary.name || row.beneficiary.mobile || row.beneficiary.bank_account }}</p><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ row.beneficiary.mobile || row.beneficiary.bank_account }} · {{ row.delivery_preference }}</p></div><p class="font-semibold text-slate-950 dark:text-slate-50">{{ peso(row.amount_minor) }}</p></article></div>
                </div>

                <form class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="add">
                    <div class="flex items-center gap-2"><Plus class="size-4 text-slate-500" /><div><p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Add Beneficiary</p><h2 class="mt-0.5 font-semibold text-slate-950 dark:text-slate-50">Recipient Details</h2></div></div>
                    <div class="mt-4 grid gap-3"><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Amount (centavos)<input v-model.number="form.amount_minor" type="number" min="1" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Name <input v-model="form.name" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Mobile or Bank Account <input v-model="form.mobile" placeholder="Mobile" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /><input v-model="form.bank_account" placeholder="Bank account" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label><label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">Remarks <textarea v-model="form.remarks" rows="2" class="rounded-lg border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white" /></label></div>
                    <p v-if="form.errors.mobile || form.errors.bank_account || form.errors.amount_minor" class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ form.errors.amount_minor || form.errors.mobile || form.errors.bank_account }}</p>
                    <button type="submit" :disabled="form.processing" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950">{{ form.processing ? 'Adding…' : 'Add Beneficiary' }}</button>
                    <p class="mt-2 text-center text-xs text-slate-500 dark:text-slate-400">Draft only. This does not authorize, issue, deliver, or transfer funds.</p>
                </form>
            </section>
        </main>
    </CockpitLayout>
</template>
