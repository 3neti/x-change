<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ClipboardList, Plus, Send, Users } from 'lucide-vue-next';
import { store } from '@/routes/x-change/cockpit/campaigns';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderPageProps } from '../types';

type CampaignWorksheet = {
    reference: string;
    profile: 'payroll' | 'assistance';
    name: string;
    currency: string;
    status: string;
    fulfillment_mode: string;
    delivery_plan: string[];
    beneficiary_count: number;
    principal_minor: number;
    updated_at: string | null;
};

type CampaignsPageProps = CockpitHeaderPageProps & {
    worksheets: CampaignWorksheet[];
};

const props = defineProps<CampaignsPageProps>();

const form = useForm({
    name: '',
    profile: 'payroll',
    fulfillment_mode: 'pay_code_distribution',
    delivery_plan: ['csv'],
});

function createWorksheet(): void {
    form.post(store(), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
}

function display(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function peso(minor: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(minor / 100);
}

function dateTime(value: string | null): string {
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
</script>

<template>
    <CockpitLayout
        active-navigation="campaigns"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main class="mx-auto max-w-7xl space-y-5" data-testid="cockpit-campaigns-page">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <ClipboardList class="size-3.5" aria-hidden="true" />
                            Campaigns
                        </div>
                        <h1 class="mt-1 text-xl font-semibold text-slate-950 dark:text-slate-50">
                            Batch Pay Codes, Prepared With Care
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-slate-600 dark:text-slate-300">
                            Start a private worksheet for payroll or assistance. Beneficiaries, authorization, issuance, and delivery come next—nothing is sent from a draft.
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs sm:w-72">
                        <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950">
                            <dt class="text-slate-500 dark:text-slate-400">Worksheets</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">{{ props.worksheets.length }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950">
                            <dt class="text-slate-500 dark:text-slate-400">Beneficiaries</dt>
                            <dd class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">{{ props.worksheets.reduce((total, worksheet) => total + worksheet.beneficiary_count, 0) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Your Worksheets</p>
                            <h2 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">Campaign Activity</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">Draft only</span>
                    </div>

                    <div v-if="props.worksheets.length === 0" class="flex min-h-64 flex-col items-center justify-center px-6 text-center">
                        <Users class="size-8 text-slate-300 dark:text-slate-700" aria-hidden="true" />
                        <p class="mt-3 font-semibold text-slate-950 dark:text-slate-50">No campaign worksheets yet</p>
                        <p class="mt-1 max-w-sm text-sm leading-5 text-slate-500 dark:text-slate-400">Create one to assemble beneficiaries before any authorization or financial action.</p>
                    </div>

                    <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                        <article v-for="worksheet in props.worksheets" :key="worksheet.reference" class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-semibold text-slate-950 dark:text-slate-50">{{ worksheet.name }}</h3>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ display(worksheet.profile) }}</span>
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[0.65rem] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">{{ display(worksheet.status) }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ worksheet.reference }} · {{ display(worksheet.fulfillment_mode) }} · updated {{ dateTime(worksheet.updated_at) }}</p>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-4 text-right text-xs sm:min-w-44">
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">Beneficiaries</dt>
                                    <dd class="font-semibold text-slate-950 dark:text-slate-50">{{ worksheet.beneficiary_count }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">Principal</dt>
                                    <dd class="font-semibold text-slate-950 dark:text-slate-50">{{ peso(worksheet.principal_minor) }}</dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </div>

                <form class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900" @submit.prevent="createWorksheet">
                    <div class="flex items-center gap-2">
                        <Plus class="size-4 text-slate-500 dark:text-slate-400" aria-hidden="true" />
                        <div>
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">New Campaign</p>
                            <h2 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">Create A Worksheet</h2>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Campaign name
                            <input v-model="form.name" type="text" maxlength="160" placeholder="July payroll" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10" />
                            <span v-if="form.errors.name" class="text-xs font-normal text-rose-600 dark:text-rose-300">{{ form.errors.name }}</span>
                        </label>

                        <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Profile
                            <select v-model="form.profile" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10">
                                <option value="payroll">Payroll</option>
                                <option value="assistance">Assistance</option>
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                            Intended fulfillment
                            <select v-model="form.fulfillment_mode" class="rounded-lg border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:focus:border-slate-100 dark:focus:ring-slate-100/10">
                                <option value="pay_code_distribution">Pay Code distribution</option>
                                <option value="direct_bank_transfer">Direct bank transfer</option>
                            </select>
                        </label>
                    </div>

                    <button type="submit" :disabled="form.processing" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-950 dark:hover:bg-white">
                        <Send class="size-4" aria-hidden="true" />
                        {{ form.processing ? 'Creating…' : 'Create Worksheet' }}
                    </button>
                    <p class="mt-2 text-center text-xs leading-4 text-slate-500 dark:text-slate-400">A worksheet cannot move money. Add beneficiaries and request authorization in the next steps.</p>
                </form>
            </section>
        </main>
    </CockpitLayout>
</template>
