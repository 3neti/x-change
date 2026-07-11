<script setup lang="ts">
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitRuntimeProfilePageProps } from '../types';

defineProps<CockpitRuntimeProfilePageProps>();
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
        </section>
    </CockpitLayout>
</template>
