<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CockpitLayout from '../../../cockpit/layouts/CockpitLayout.vue';
import type { CockpitHeaderReadModel } from '../../../cockpit/types';

defineProps<{
    cockpit_header_read_model?: CockpitHeaderReadModel;
    offers: Array<{
        reference: string;
        title: string;
        consent_version: string;
        active: boolean;
        consents_count: number;
        public_url: string;
    }>;
    store_url: string;
}>();

const form = useForm({ title: '', consent_statement: '', consent_version: '' });
</script>

<template>
    <CockpitLayout active-navigation="campaigns" :cockpit-header-read-model="cockpit_header_read_model">
        <Head title="Product enrollment" />
        <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 text-slate-950">
            <header>
                <h1 class="text-2xl font-semibold">Product enrollment</h1>
                <p class="mt-1 text-sm text-slate-600">Publish a consent statement and share its enrollment link. This records an opt-in; it does not approve a product.</p>
            </header>

            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-5" @submit.prevent="form.post(store_url)">
                <h2 class="text-lg font-semibold">New enrollment link</h2>
                <label class="block text-sm font-medium">Product or offer
                    <input v-model="form.title" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required maxlength="120" />
                    <span v-if="form.errors.title" class="text-red-700">{{ form.errors.title }}</span>
                </label>
                <label class="block text-sm font-medium">Consent version
                    <input v-model="form.consent_version" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" required maxlength="40" placeholder="2026-10" />
                    <span v-if="form.errors.consent_version" class="text-red-700">{{ form.errors.consent_version }}</span>
                </label>
                <label class="block text-sm font-medium">Consent statement
                    <textarea v-model="form.consent_statement" class="mt-1 min-h-28 w-full rounded-lg border border-slate-300 px-3 py-2" required maxlength="2000" />
                    <span v-if="form.errors.consent_statement" class="text-red-700">{{ form.errors.consent_statement }}</span>
                </label>
                <button class="rounded-lg bg-slate-950 px-4 py-2 font-semibold text-white disabled:opacity-50" :disabled="form.processing">Create link</button>
            </form>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">Enrollment activity</h2>
                <p v-if="offers.length === 0" class="mt-3 text-sm text-slate-600">No enrollment links yet.</p>
                <ul v-else class="mt-3 divide-y divide-slate-200">
                    <li v-for="offer in offers" :key="offer.reference" class="space-y-1 py-3 text-sm">
                        <p class="font-semibold">{{ offer.title }} · {{ offer.consents_count }} recorded</p>
                        <p class="text-slate-600">Consent {{ offer.consent_version }} · {{ offer.active ? 'Open' : 'Closed' }}</p>
                        <a :href="offer.public_url" class="break-all text-blue-700 underline">{{ offer.public_url }}</a>
                    </li>
                </ul>
            </section>
        </main>
    </CockpitLayout>
</template>
