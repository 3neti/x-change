<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { store } from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitCommercialPayCodeScenarioRunnerController';
import {
    BadgeCheck,
    Building2,
    CircleDot,
    FileCheck2,
    ShieldCheck,
    UserRoundCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderReadModel } from '../types';

type Actor = { id: string; name: string; mobile: string };
type ScenarioRun = {
    reference: string;
    phase: 'awaiting_checker' | 'issued' | 'cancelled';
    amount: string;
    worksheet_reference: string;
    approval_pay_code: string | null;
    authorization_reference: string | null;
    funding_source: string;
    pay_code: string | null;
    steps: Array<{ label: string; complete: boolean }>;
};

const props = defineProps<{
    cockpit_header_read_model?: CockpitHeaderReadModel;
    commercial_principal: {
        reference: string | null;
        legal_name: string | null;
        active: boolean;
        funding_source: string;
        revenue_account_excluded: boolean;
        balances: {
            client_funds_minor: number;
            pay_code_reserve_minor: number;
            revenue_minor: number;
        } | null;
    };
    maker: Actor;
    maker_ready: boolean;
    checkers: Actor[];
    run: ScenarioRun | null;
}>();

const selectedChecker = ref(props.checkers[0]?.id ?? '');
const processing = ref(false);
const canPrepare = computed(
    () =>
        props.maker_ready &&
        props.commercial_principal.active &&
        selectedChecker.value !== '' &&
        props.run === null,
);

function submit(phase: 'prepare' | 'approve' | 'cancel'): void {
    router.post(
        store.url(),
        {
            phase,
            checker: selectedChecker.value,
            run_reference: props.run?.reference ?? null,
            amount: 50,
        },
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Commercial Pay Code Lifecycle" />

    <CockpitLayout
        active-navigation="campaigns"
        mobile-presentation="edge"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main
            class="mx-auto max-w-5xl space-y-5 px-4 py-4 md:px-0"
            data-testid="commercial-pay-code-scenario-runner"
        >
            <section
                class="-mx-4 border-y border-slate-200 bg-white p-5 md:mx-0 md:rounded-2xl md:border md:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">
                    <ShieldCheck class="size-4" />
                    Browser lifecycle scenario
                </div>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-950 dark:text-white">
                            Commercial ₱50 Pay Code
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            The local scenario first credits ₱100 of simulated provider-backed value to the Commercial Principal. A Maker then freezes a ₱50 instruction and a different Checker approves it before the Principal's funds are reserved.
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-950 px-5 py-3 text-right text-white dark:bg-white dark:text-slate-950">
                        <p class="text-xs opacity-70">Principal</p>
                        <p class="text-xl font-semibold">₱50.00</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <Building2 class="size-5 text-emerald-600" />
                    <p class="mt-3 text-xs text-slate-500">Commercial Principal</p>
                    <p class="mt-1 font-semibold text-slate-950 dark:text-white">
                        {{ props.commercial_principal.legal_name ?? 'Not commissioned' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">{{ props.commercial_principal.funding_source }}</p>
                    <dl v-if="props.commercial_principal.balances" class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div><dt class="text-slate-500">Client Funds</dt><dd class="font-semibold">₱{{ (props.commercial_principal.balances.client_funds_minor / 100).toFixed(2) }}</dd></div>
                        <div><dt class="text-slate-500">Reserve</dt><dd class="font-semibold">₱{{ (props.commercial_principal.balances.pay_code_reserve_minor / 100).toFixed(2) }}</dd></div>
                        <div><dt class="text-slate-500">Revenue</dt><dd class="font-semibold">₱{{ (props.commercial_principal.balances.revenue_minor / 100).toFixed(2) }}</dd></div>
                    </dl>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <FileCheck2 class="size-5 text-blue-600" />
                    <p class="mt-3 text-xs text-slate-500">Maker</p>
                    <p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ props.maker.name }}</p>
                    <p class="mt-1 text-xs" :class="props.maker_ready ? 'text-emerald-600' : 'text-amber-600'">
                        {{ props.maker_ready ? 'Authorized to prepare' : 'Preparation authority required' }}
                    </p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <UserRoundCheck class="size-5 text-violet-600" />
                    <label for="commercial-checker" class="mt-3 block text-xs text-slate-500">Checker</label>
                    <select
                        id="commercial-checker"
                        v-model="selectedChecker"
                        :disabled="props.run !== null"
                        class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                        <option value="" disabled>Select an authorized checker</option>
                        <option v-for="checker in props.checkers" :key="checker.id" :value="checker.id">
                            {{ checker.name }}
                        </option>
                    </select>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-950 dark:text-white">Controlled actions</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            The Commercial Revenue Account stays at ₱0.00. This scenario stops after issuance and can safely demonstrate cancellation back to the Commercial Principal.
                        </p>
                    </div>
                    <button
                        v-if="props.run === null"
                        type="button"
                        :disabled="!canPrepare || processing"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        data-testid="commercial-pay-code-prepare"
                        @click="submit('prepare')"
                    >
                        {{ processing ? 'Funding & preparing…' : 'Fund ₱100 & Maker: Prepare' }}
                    </button>
                    <button
                        v-else-if="props.run.phase === 'awaiting_checker'"
                        type="button"
                        :disabled="processing"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-wait disabled:opacity-60"
                        data-testid="commercial-pay-code-approve"
                        @click="submit('approve')"
                    >
                        {{ processing ? 'Approving…' : 'Checker: Approve & issue' }}
                    </button>
                    <button
                        v-else-if="props.run.phase === 'issued'"
                        type="button"
                        :disabled="processing"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-wait disabled:opacity-60"
                        data-testid="commercial-pay-code-cancel"
                        @click="submit('cancel')"
                    >
                        {{ processing ? 'Cancelling…' : 'Cancel & return ₱50' }}
                    </button>
                </div>

                <div v-if="props.run" class="mt-5 grid gap-3 md:grid-cols-2">
                    <div
                        v-for="step in props.run.steps"
                        :key="step.label"
                        class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800"
                    >
                        <BadgeCheck v-if="step.complete" class="size-5 text-emerald-600" />
                        <CircleDot v-else class="size-5 text-slate-400" />
                        <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ step.label }}</span>
                    </div>
                </div>
            </section>

            <section
                v-if="props.run"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950"
                data-testid="commercial-pay-code-artifacts"
            >
                <h2 class="font-semibold text-slate-950 dark:text-white">Lifecycle artifacts</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Run</dt><dd class="font-mono">{{ props.run.reference }}</dd></div>
                    <div><dt class="text-slate-500">Approval Pay Code</dt><dd class="font-mono">{{ props.run.approval_pay_code ?? 'Pending' }}</dd></div>
                    <div><dt class="text-slate-500">Funding source</dt><dd>{{ props.run.funding_source }}</dd></div>
                    <div><dt class="text-slate-500">Issued Pay Code</dt><dd class="font-mono text-lg font-semibold">{{ props.run.pay_code ?? 'Awaiting Checker' }}</dd></div>
                </dl>
            </section>
        </main>
    </CockpitLayout>
</template>
