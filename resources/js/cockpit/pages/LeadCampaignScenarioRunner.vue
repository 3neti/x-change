<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    show,
    store,
} from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitLeadCampaignScenarioRunnerController';
import {
    ArrowRight,
    ClipboardList,
    FileText,
    Megaphone,
    PlayCircle,
    QrCode,
    ShieldCheck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderReadModel } from '../types';

defineOptions({ inheritAttrs: false });

type LeadScenario = {
    schema: string;
    key: string;
    title: string;
    description: string;
    entry_point: string;
    person_type: string;
    pay_code_generation: string;
    claim_surface: string;
    amount: string;
    details_label: string;
    details_description: string;
    fields: string[];
};

type RecentLeadCampaign = {
    reference: string;
    title: string;
    merchant_display_name: string;
    public_url: string;
    usage_count: number;
    last_started_at: string | null;
    status: string;
};

const props = defineProps<{
    cockpit_header_read_model?: CockpitHeaderReadModel;
    scenario: LeadScenario;
    scenarios: LeadScenario[];
    recent_lead_campaigns: RecentLeadCampaign[];
}>();

const running = ref(false);

function runScenario(): void {
    router.post(
        store.url(),
        { scenario: props.scenario.key },
        {
            preserveScroll: false,
            onStart: () => {
                running.value = true;
            },
            onFinish: () => {
                running.value = false;
            },
        },
    );
}

function selectScenario(key: string): void {
    router.get(show.url(), { scenario: key }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Lead Campaign Scenario Runner" />

    <CockpitLayout
        active-navigation="campaigns"
        mobile-presentation="edge"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <main
            class="mx-auto max-w-5xl space-y-5 px-4 py-4 md:px-0"
            data-testid="cockpit-lead-scenario-runner"
        >
            <section
                class="-mx-4 border-y border-slate-200 bg-white p-4 shadow-none md:mx-0 md:rounded-2xl md:border md:p-6 md:shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="max-w-2xl">
                        <div
                            class="flex items-center gap-2 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            <Megaphone class="size-3.5" aria-hidden="true" />
                            Lead Campaign Lab
                        </div>
                        <h1
                            class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50"
                        >
                            {{ props.scenario.title }}
                        </h1>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ props.scenario.description }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-70"
                        data-testid="cockpit-lead-scenario-run-button"
                        :disabled="running"
                        @click="runScenario"
                    >
                        <PlayCircle class="size-4" aria-hidden="true" />
                        {{ running ? 'Starting…' : 'Run in browser' }}
                    </button>
                </div>
            </section>

            <section
                class="grid gap-2 sm:grid-cols-2"
                aria-label="Browser lifecycle scenarios"
                data-testid="cockpit-lead-scenario-selector"
            >
                <button
                    v-for="option in props.scenarios"
                    :key="option.key"
                    type="button"
                    class="min-w-0 rounded-xl border px-4 py-3 text-left transition"
                    :class="
                        option.key === props.scenario.key
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-950 dark:border-emerald-400 dark:bg-emerald-950/30 dark:text-emerald-100'
                            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200'
                    "
                    :aria-pressed="option.key === props.scenario.key"
                    :data-testid="`cockpit-lead-scenario-option-${option.key}`"
                    @click="selectScenario(option.key)"
                >
                    <span class="block truncate text-sm font-semibold">
                        {{ option.title }}
                    </span>
                    <span class="mt-1 block text-xs opacity-75">
                        {{ option.amount }} · {{ option.person_type }}
                    </span>
                </button>
            </section>

            <section
                class="grid gap-3 md:grid-cols-4"
                data-testid="cockpit-lead-scenario-contract"
            >
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <QrCode class="size-5 text-emerald-600" />
                    <p class="mt-3 text-xs text-slate-500">Entry point</p>
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ props.scenario.entry_point }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <ClipboardList class="size-5 text-emerald-600" />
                    <p class="mt-3 text-xs text-slate-500">Person type</p>
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ props.scenario.person_type }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <ShieldCheck class="size-5 text-emerald-600" />
                    <p class="mt-3 text-xs text-slate-500">Claim surface</p>
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ props.scenario.claim_surface }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                >
                    <FileText class="size-5 text-emerald-600" />
                    <p class="mt-3 text-xs text-slate-500">Pay Code amount</p>
                    <p class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                        {{ props.scenario.amount }}
                    </p>
                </article>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-lead-scenario-fields"
            >
                <h2 class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                    {{ props.scenario.details_label }}
                </h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ props.scenario.details_description }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-for="field in props.scenario.fields"
                        :key="field"
                        class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                    >
                        {{ field }}
                    </span>
                </div>
            </section>

            <section
                v-if="props.recent_lead_campaigns.length > 0"
                class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-lead-scenario-recent"
            >
                <h2 class="text-sm font-semibold text-slate-950 dark:text-slate-50">
                    Recent Lead Campaign endpoints
                </h2>
                <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    <a
                        v-for="campaign in props.recent_lead_campaigns"
                        :key="campaign.reference"
                        :href="campaign.public_url"
                        class="flex items-center justify-between gap-3 py-3 text-sm"
                    >
                        <span>
                            <span
                                class="block font-semibold text-slate-950 dark:text-slate-50"
                            >
                                {{ campaign.title }}
                            </span>
                            <span class="block text-xs text-slate-500">
                                {{ campaign.merchant_display_name }} ·
                                {{ campaign.usage_count }} starts
                            </span>
                        </span>
                        <ArrowRight class="size-4 shrink-0 text-slate-400" />
                    </a>
                </div>
            </section>
        </main>
    </CockpitLayout>
</template>
