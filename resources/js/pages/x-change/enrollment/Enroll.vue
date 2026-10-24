<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ClaimStepShell from '@/components/x-change/ClaimStepShell.vue';

defineOptions({ layout: null });

const props = defineProps<{
    offer: { title: string; consent_statement: string; consent_version: string };
    store_url: string;
    receipt?: string | null;
}>();

const form = useForm({ name: '', mobile: '', accepted: false });
</script>

<template>
    <ClaimStepShell brand-placement="center" brand-size="display" brand-variant="mark" width="md">
        <Head :title="props.offer.title" />
        <div class="space-y-6 text-slate-950">
            <header class="text-center">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Product opt-in</p>
                <h1 class="mt-2 text-2xl font-semibold">{{ props.offer.title }}</h1>
            </header>
            <div v-if="props.receipt" class="rounded-xl border border-emerald-300 bg-emerald-50 p-5 text-center">
                <p class="font-semibold">Your opt-in was recorded.</p>
                <p class="mt-2 font-mono text-xs">Receipt {{ props.receipt }}</p>
                <p class="mt-2 text-sm">This is an enrollment record, not product approval.</p>
            </div>
            <form v-else class="space-y-4" @submit.prevent="form.post(props.store_url)">
                <label class="block text-sm font-medium">Name
                    <input v-model="form.name" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" required maxlength="120" />
                    <span v-if="form.errors.name" class="text-red-700">{{ form.errors.name }}</span>
                </label>
                <label class="block text-sm font-medium">Mobile number
                    <input v-model="form.mobile" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" required inputmode="tel" autocomplete="tel" placeholder="09173011987" />
                    <span v-if="form.errors.mobile" class="text-red-700">{{ form.errors.mobile }}</span>
                </label>
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm leading-6">{{ props.offer.consent_statement }}</div>
                <label class="flex items-start gap-2 text-sm">
                    <input v-model="form.accepted" type="checkbox" class="mt-1" required />
                    <span>I agree to this consent statement (version {{ props.offer.consent_version }}).</span>
                </label>
                <span v-if="form.errors.accepted" class="text-sm text-red-700">{{ form.errors.accepted }}</span>
                <button class="w-full rounded-xl bg-slate-950 px-4 py-3 font-semibold text-white disabled:opacity-50" :disabled="form.processing">Record my opt-in</button>
            </form>
        </div>
    </ClaimStepShell>
</template>
