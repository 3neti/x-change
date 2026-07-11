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
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                    {{ runtime_profile_read_model.copy.eyebrow }}
                </p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-slate-50">
                    {{ runtime_profile_read_model.copy.title }}
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ runtime_profile_read_model.copy.description }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="cockpit-runtime-profile-summary-card"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                        {{ card.label }}
                    </p>
                    <p class="mt-3 text-xl font-semibold text-slate-950 dark:text-slate-50">
                        {{ card.value }}
                    </p>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                        {{ card.helper }}
                    </p>
                </article>
            </div>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="cockpit-runtime-profile-components"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                            Runtime components
                        </p>
                        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
                            Explicit configuration and fallbacks
                        </h3>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ components.length }} components
                    </span>
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
                    <div
                        v-for="component in components"
                        :key="component.key"
                        class="grid gap-3 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                        data-testid="cockpit-runtime-profile-component"
                    >
                        <div>
                            <p class="font-mono text-sm font-semibold text-slate-950 dark:text-slate-50">
                                {{ component.key }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ component.purpose }}
                            </p>
                            <p
                                class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="component.enabled ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                            >
                                {{ component.enabled ? 'configured' : 'fallback' }}
                            </p>
                        </div>
                        <dl class="grid gap-2 text-sm">
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
            </section>

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
