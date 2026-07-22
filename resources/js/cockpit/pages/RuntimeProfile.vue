<script setup lang="ts">
import { computed } from 'vue';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitRuntimeProfileComponent, CockpitRuntimeProfilePageProps } from '../types';

const props = defineProps<CockpitRuntimeProfilePageProps>();

const readModel = computed(() => props.runtime_profile_read_model);
const profile = computed(() => readModel.value.profile);

const summaryCards = computed(() => [
    {
        key: 'status',
        label: 'Runtime status',
        value: stringValue(profile.value.status),
        helper: 'Computed from explicit operator activity runtime configuration.',
    },
    {
        key: 'repository',
        label: 'Repository',
        value: profile.value.repository_enabled ? 'configured' : 'fallback',
        helper: 'Durable operator activity read storage.',
    },
    {
        key: 'journal',
        label: 'Journal handoff',
        value: profile.value.journal_handoff_enabled ? 'configured' : 'not wired',
        helper: 'Evidence handoff remains opt-in.',
    },
    {
        key: 'action-feedback',
        label: 'Action / Feedback',
        value: `${profile.value.action_handoff_enabled ? 'action configured' : 'action not wired'} · ${profile.value.feedback_handoff_enabled ? 'feedback configured' : 'feedback not wired'}`,
        helper: 'Presentation-only action and feedback planning handoffs.',
    },
]);

const components = computed<CockpitRuntimeProfileComponent[]>(() => {
    if (!Array.isArray(profile.value.components)) {
        return [];
    }

    return profile.value.components;
});

const safetyEntries = computed(() => Object.entries(readModel.value.safety ?? {}));
const runtimeSafetyEntries = computed(() => Object.entries(profile.value.safety ?? {}));

function stringValue(value: unknown): string {
    if (typeof value === 'string' && value.trim() !== '') {
        return value.trim();
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return 'unknown';
}

function booleanLabel(value: unknown): string {
    if (value === true) {
        return 'yes';
    }

    if (value === false) {
        return 'no';
    }

    return stringValue(value);
}
</script>

<template>
    <CockpitLayout active-navigation="runtime-profile">
        <section class="space-y-6" data-testid="cockpit-runtime-profile-shell">
            <div
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-runtime-profile-header"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center"
                    data-testid="cockpit-runtime-profile-header-row"
                >
                    <div class="min-w-0 lg:w-72 lg:shrink-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                {{ runtime_profile_read_model.copy.eyebrow }}
                            </p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                read-only
                            </span>
                        </div>
                        <h2 class="mt-1 text-lg font-semibold leading-6 text-slate-950 dark:text-slate-50">
                            {{ runtime_profile_read_model.copy.title }}
                        </h2>
                        <p class="mt-0.5 text-xs leading-4 text-slate-600 dark:text-slate-300">
                            {{ runtime_profile_read_model.copy.description }}
                        </p>
                    </div>

                    <dl
                        class="grid w-full gap-1.5 rounded-lg bg-slate-50 p-1.5 text-xs sm:grid-cols-2 lg:min-w-0 lg:flex-1 xl:grid-cols-4 dark:bg-slate-950"
                        data-testid="cockpit-runtime-profile-header-facts"
                    >
                        <div
                            v-for="card in summaryCards"
                            :key="card.key"
                            class="min-w-0 rounded-lg bg-white px-2.5 py-1.5 ring-1 ring-slate-100 dark:bg-slate-900 dark:ring-slate-800"
                            data-testid="cockpit-runtime-profile-summary-card"
                        >
                            <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                {{ card.label }}
                            </dt>
                            <dd class="truncate text-xs font-semibold leading-4 text-slate-950 dark:text-slate-50">
                                {{ card.value }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <details
                    class="mt-2 border-t border-slate-200 pt-2 dark:border-slate-800"
                    data-testid="cockpit-runtime-profile-header-context"
                >
                    <summary class="cursor-pointer text-[0.7rem] font-semibold text-slate-500 dark:text-slate-400">
                        Runtime profile context
                    </summary>
                    <ul class="mt-2 grid gap-1 text-xs leading-5 text-slate-500 sm:grid-cols-2 dark:text-slate-400">
                        <li v-for="card in summaryCards" :key="card.key">
                            <span class="font-semibold text-slate-700 dark:text-slate-300">{{ card.label }}:</span>
                            {{ card.helper }}
                        </li>
                    </ul>
                </details>
            </div>

            <details
                class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-runtime-profile-components"
            >
                <summary class="flex cursor-pointer list-none flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            Runtime components
                        </p>
                        <h3 class="mt-0.5 text-base font-semibold text-slate-950 dark:text-slate-50">
                            Explicit configuration and fallbacks
                        </h3>
                    </div>
                    <span class="w-fit rounded-full bg-slate-100 px-2 py-0.5 text-[0.7rem] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ components.length }} components
                    </span>
                </summary>

                <div class="mt-3 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
                    <div
                        v-for="component in components"
                        :key="component.key"
                        class="grid gap-2 border-b border-slate-200 p-3 last:border-b-0 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                        data-testid="cockpit-runtime-profile-component"
                    >
                        <div>
                            <p class="font-mono text-sm font-semibold text-slate-950 dark:text-slate-50">
                                {{ component.key }}
                            </p>
                            <p class="mt-0.5 text-xs leading-5 text-slate-600 dark:text-slate-300">
                                {{ component.purpose }}
                            </p>
                            <p
                                class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[0.7rem] font-semibold"
                                :class="component.enabled ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                            >
                                {{ component.enabled ? 'configured' : 'fallback' }}
                            </p>
                        </div>
                        <dl class="grid gap-1.5 text-xs">
                            <div class="grid gap-1 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <dt class="font-semibold text-slate-500 dark:text-slate-400">
                                    Configured
                                </dt>
                                <dd class="break-all font-mono text-slate-800 dark:text-slate-200">
                                    {{ component.configured ?? 'null' }}
                                </dd>
                            </div>
                            <div class="grid gap-1 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <dt class="font-semibold text-slate-500 dark:text-slate-400">
                                    Resolved
                                </dt>
                                <dd class="break-all font-mono text-slate-800 dark:text-slate-200">
                                    {{ component.resolved_class }}
                                </dd>
                            </div>
                            <div class="grid gap-1 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <dt class="font-semibold text-slate-500 dark:text-slate-400">
                                    Fallback
                                </dt>
                                <dd class="break-all font-mono text-slate-800 dark:text-slate-200">
                                    {{ component.fallback_class }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </details>

            <div class="grid gap-4 lg:grid-cols-2">
                <section
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-runtime-profile-page-safety"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Page safety
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        This diagnostics surface is read-only
                    </h3>
                    <dl class="mt-4 grid gap-2">
                        <div
                            v-for="[key, value] in safetyEntries"
                            :key="key"
                            class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-950"
                        >
                            <dt class="font-mono text-slate-600 dark:text-slate-300">
                                {{ key }}
                            </dt>
                            <dd class="font-semibold text-slate-950 dark:text-slate-50">
                                {{ booleanLabel(value) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-runtime-profile-runtime-safety"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        Runtime safety
                    </p>
                    <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                        Runtime capabilities remain explicit opt-in
                    </h3>
                    <dl class="mt-4 grid gap-2">
                        <div
                            v-for="[key, value] in runtimeSafetyEntries"
                            :key="key"
                            class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-950"
                        >
                            <dt class="font-mono text-slate-600 dark:text-slate-300">
                                {{ key }}
                            </dt>
                            <dd class="font-semibold text-slate-950 dark:text-slate-50">
                                {{ booleanLabel(value) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>
        </section>
    </CockpitLayout>
</template>
