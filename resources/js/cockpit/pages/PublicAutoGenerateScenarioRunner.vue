<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { store } from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPublicAutoGenerateScenarioRunnerController';
import { BadgeCheck, CircleDot, RotateCcw, ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderReadModel } from '../types';

type ScenarioFact = { label: string; value: string };
type ScenarioStep = {
    key: string;
    label: string;
    outcome: string;
    facts: ScenarioFact[];
};
type ScenarioRun = {
    success: boolean;
    message: string;
    rollback_completed: boolean;
    simulation: {
        rollback_only: boolean;
        provider_calls: number;
        monetary_value: boolean;
        persisted: boolean;
    };
    steps: ScenarioStep[];
    artifacts: {
        commercial_principal_reference: string;
        order_reference: string;
        pay_code: string;
        claim_qr_present: boolean;
        share_result_present: boolean;
        expected_payment_minor: number;
    };
};

const props = defineProps<{
    cockpit_header_read_model?: CockpitHeaderReadModel;
    run: ScenarioRun | null;
}>();

const processing = ref(false);
const payment = computed(() =>
    props.run ? `₱${(props.run.artifacts.expected_payment_minor / 100).toFixed(2)}` : '₱43.00',
);

function runScenario(): void {
    router.post(
        store.url(),
        {},
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
    <Head title="Public Auto-Generate Lifecycle" />

    <CockpitLayout
        active-navigation="campaigns"
        mobile-presentation="edge"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main
            class="mx-auto max-w-5xl space-y-5 px-4 py-4 md:px-0"
            data-testid="public-auto-generate-scenario-runner"
        >
            <section class="-mx-4 border-y border-slate-200 bg-white p-5 md:mx-0 md:rounded-2xl md:border md:p-6 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-400">
                    <ShieldCheck class="size-4" />
                    Rollback-only browser lifecycle
                </div>
                <div class="mt-3 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-950 dark:text-white">
                            Public Auto-Generate
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Prove the public composition, server-selected Commercial Principal, complete pricing, browser isolation, simulated exact payment, and exactly-once issuance. Every database and wallet change is rolled back.
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-950 px-5 py-3 text-right text-white dark:bg-white dark:text-slate-950">
                        <p class="text-xs opacity-70">Expected total</p>
                        <p class="text-xl font-semibold">{{ payment }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-950 dark:text-white">Safe acceptance proof</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            No provider is called, no real payment is accepted, and no Pay Code or balance survives this run.
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="processing"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-60"
                        data-testid="public-auto-generate-run"
                        @click="runScenario"
                    >
                        <RotateCcw class="size-4" />
                        {{ processing ? 'Running safely…' : props.run ? 'Run again' : 'Run rollback proof' }}
                    </button>
                </div>
            </section>

            <template v-if="props.run">
                <section class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="step in props.run.steps"
                        :key="step.key"
                        class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-start gap-3">
                            <BadgeCheck v-if="props.run.success" class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                            <CircleDot v-else class="mt-0.5 size-5 shrink-0 text-amber-500" />
                            <div>
                                <p class="font-semibold text-slate-950 dark:text-white">{{ step.label }}</p>
                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                                    {{ step.outcome }}
                                </p>
                            </div>
                        </div>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div v-for="fact in step.facts" :key="fact.label" class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ fact.label }}</dt>
                                <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ fact.value }}</dd>
                            </div>
                        </dl>
                    </article>
                </section>

                <section
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900 dark:bg-emerald-950/30"
                    data-testid="public-auto-generate-artifacts"
                >
                    <div class="flex items-center gap-2 font-semibold text-emerald-900 dark:text-emerald-100">
                        <BadgeCheck class="size-5" />
                        {{ props.run.message }}
                    </div>
                    <p class="mt-2 text-sm text-emerald-800 dark:text-emerald-200">
                        Rollback confirmed: {{ props.run.rollback_completed ? 'Yes' : 'No' }} · Provider calls: {{ props.run.simulation.provider_calls }} · Persisted value: {{ props.run.simulation.persisted ? 'Yes' : 'No' }}
                    </p>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-emerald-700 dark:text-emerald-300">Commercial Principal</dt><dd class="font-mono">{{ props.run.artifacts.commercial_principal_reference }}</dd></div>
                        <div><dt class="text-emerald-700 dark:text-emerald-300">Rolled-back order</dt><dd class="font-mono">{{ props.run.artifacts.order_reference }}</dd></div>
                        <div><dt class="text-emerald-700 dark:text-emerald-300">Rolled-back Pay Code</dt><dd class="font-mono">{{ props.run.artifacts.pay_code }}</dd></div>
                        <div><dt class="text-emerald-700 dark:text-emerald-300">Stamp/share projected</dt><dd>{{ props.run.artifacts.claim_qr_present && props.run.artifacts.share_result_present ? 'Yes' : 'No' }}</dd></div>
                    </dl>
                </section>
            </template>
        </main>
    </CockpitLayout>
</template>
