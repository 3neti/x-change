<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    CheckCircle2,
    CircleAlert,
    CloudCog,
    MailCheck,
    RadioTower,
    RefreshCw,
    ServerCog,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type {
    CockpitRuntimeProfileComponent,
    CockpitRuntimeProfilePageProps,
    CockpitSystemReadinessSection,
} from '../types';

defineOptions({ inheritAttrs: false });

const props = defineProps<CockpitRuntimeProfilePageProps>();
const refreshing = ref(false);
const readModel = computed(() => props.runtime_profile_read_model);
const readiness = computed(() => readModel.value.system_readiness);
const technicalComponents = computed<CockpitRuntimeProfileComponent[]>(() =>
    Array.isArray(readiness.value.technical.operator_activity.components)
        ? readiness.value.technical.operator_activity.components
        : [],
);
const summaryCards = computed(() => [
    {
        key: 'state',
        label: 'System State',
        value:
            readiness.value.status === 'operational'
                ? 'Operational'
                : 'Attention Required',
        tone:
            readiness.value.status === 'operational' ? 'healthy' : 'warning',
    },
    {
        key: 'profile',
        label: 'Deployment Profile',
        value: titleCase(readiness.value.context.profile),
        tone: 'neutral',
    },
    {
        key: 'connections',
        label: 'Active Connections',
        value: String(readiness.value.context.active_connections.length),
        tone: 'neutral',
    },
    {
        key: 'checks',
        label: 'Readiness Checks',
        value: `${readiness.value.summary.ready} of ${readiness.value.summary.total}`,
        tone: readiness.value.summary.attention === 0 ? 'healthy' : 'warning',
    },
]);

const sectionIcons = {
    deployment: ShieldCheck,
    runtime: ServerCog,
    delivery: MailCheck,
};

function refresh(): void {
    router.reload({
        only: ['runtime_profile_read_model'],
        preserveScroll: true,
        onStart: () => {
            refreshing.value = true;
        },
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

function checkedAt(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Check time unavailable';
    }

    return `Checked ${new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date)}`;
}

function titleCase(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

function sectionIcon(section: CockpitSystemReadinessSection) {
    return sectionIcons[section.key as keyof typeof sectionIcons] ?? Activity;
}
</script>

<template>
    <Head title="System Readiness" />

    <CockpitLayout
        active-navigation="runtime-profile"
        :cockpit-header-read-model="props.cockpit_header_read_model"
    >
        <section
            class="mx-auto max-w-6xl space-y-5"
            data-testid="cockpit-runtime-profile-shell"
        >
            <header
                class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-system-readiness-header"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-950 text-white dark:bg-white dark:text-slate-950"
                        >
                            <RadioTower class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                {{ readModel.copy.eyebrow }}
                            </p>
                            <h1
                                class="text-xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{ readModel.copy.title }}
                            </h1>
                            <p
                                class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                            >
                                {{ readModel.copy.description }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <div class="text-right">
                            <p
                                class="text-xs font-semibold"
                                :class="
                                    readiness.status === 'operational'
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-amber-700 dark:text-amber-300'
                                "
                            >
                                {{
                                    readiness.status === 'operational'
                                        ? 'Operational'
                                        : 'Attention Required'
                                }}
                            </p>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ checkedAt(readiness.checked_at) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            :disabled="refreshing"
                            data-testid="cockpit-system-readiness-refresh"
                            @click="refresh"
                        >
                            <RefreshCw
                                class="size-4"
                                :class="{ 'animate-spin': refreshing }"
                            />
                            {{ refreshing ? 'Checking' : 'Run Checks' }}
                        </button>
                    </div>
                </div>
            </header>

            <dl
                class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                data-testid="cockpit-system-readiness-summary"
            >
                <div
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <dt
                        class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >
                        {{ card.label }}
                    </dt>
                    <dd
                        class="mt-1 text-lg font-semibold"
                        :class="
                            card.tone === 'healthy'
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : card.tone === 'warning'
                                  ? 'text-amber-700 dark:text-amber-300'
                                  : 'text-slate-950 dark:text-white'
                        "
                    >
                        {{ card.value }}
                    </dd>
                </div>
            </dl>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-system-readiness-providers"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <CloudCog class="size-4" />
                        </span>
                        <div>
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Providers And Connections
                            </h2>
                            <p
                                class="mt-0.5 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Selected deployment topology and registered
                                capabilities. No provider call occurs here.
                            </p>
                        </div>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            readiness.providers.status === 'ready'
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                        "
                    >
                        <CheckCircle2
                            v-if="readiness.providers.status === 'ready'"
                            class="size-3.5"
                        />
                        <CircleAlert v-else class="size-3.5" />
                        {{
                            readiness.providers.status === 'ready'
                                ? 'Ready'
                                : 'Attention'
                        }}
                    </span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-950">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Active Providers
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ readiness.providers.active.map(titleCase).join(', ') || 'None' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-950">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Connections
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ readiness.providers.connections.join(', ') || 'None' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-950">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Available, Not Selected
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ readiness.providers.installed_but_disabled.map(titleCase).join(', ') || 'None' }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-3">
                <section
                    v-for="section in readiness.sections"
                    :key="section.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-system-readiness-section"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <component :is="sectionIcon(section)" class="size-4" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-950 dark:text-white">
                                    {{ section.label }}
                                </h2>
                                <p class="mt-0.5 text-xs leading-4 text-slate-500 dark:text-slate-400">
                                    {{ section.description }}
                                </p>
                            </div>
                        </div>
                        <CheckCircle2
                            v-if="section.status === 'ready'"
                            class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <CircleAlert
                            v-else
                            class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                    </div>

                    <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <li
                            v-for="check in section.checks"
                            :key="check.name"
                            class="flex gap-2.5 py-2.5 first:pt-0 last:pb-0"
                        >
                            <CheckCircle2
                                v-if="check.passed"
                                class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <CircleAlert
                                v-else
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ titleCase(check.name) }}
                                </p>
                                <p class="mt-0.5 text-xs leading-4 text-slate-500 dark:text-slate-400">
                                    {{ check.message }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <details
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-system-readiness-runtime-processes"
            >
                <summary class="cursor-pointer list-none">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">
                                Runtime Responsibilities
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Required queues and deployment processes. Configuration cannot prove workers are running.
                            </p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ readiness.runtime_processes.queues.length }} queues
                        </span>
                    </div>
                </summary>
                <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3 md:grid-cols-2 dark:border-slate-800">
                    <div>
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Required Queues</p>
                        <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">
                            {{ readiness.runtime_processes.queues.join(', ') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Broadcasting</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ readiness.runtime_processes.broadcasting_required ? 'Reverb is required.' : 'Reverb is optional while broadcasts are disabled.' }}
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Local Development</p>
                        <div class="mt-1.5 grid gap-1.5">
                            <code
                                v-for="(command, key) in readiness.runtime_processes.local"
                                :key="key"
                                class="overflow-x-auto rounded-lg bg-slate-950 px-3 py-2 text-[0.7rem] text-slate-200"
                            >{{ command }}</code>
                        </div>
                    </div>
                </div>
            </details>

            <details
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-system-readiness-technical"
            >
                <summary class="cursor-pointer list-none">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">
                                Technical Details
                            </h2>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Adapter wiring for implementation and support teams.
                            </p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ technicalComponents.length }} components
                        </span>
                    </div>
                </summary>
                <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                    <div
                        v-for="component in technicalComponents"
                        :key="component.key"
                        class="grid gap-1 border-b border-slate-200 px-3 py-2.5 last:border-b-0 sm:grid-cols-[11rem_minmax(0,1fr)] dark:border-slate-800"
                    >
                        <div>
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                {{ titleCase(component.key) }}
                            </p>
                            <p class="text-[0.7rem] text-slate-500 dark:text-slate-400">
                                {{ component.enabled ? 'Configured' : 'Safe fallback' }}
                            </p>
                        </div>
                        <p class="break-all font-mono text-[0.7rem] leading-4 text-slate-500 dark:text-slate-400">
                            {{ component.resolved_class }}
                        </p>
                    </div>
                </div>
            </details>
        </section>
    </CockpitLayout>
</template>
