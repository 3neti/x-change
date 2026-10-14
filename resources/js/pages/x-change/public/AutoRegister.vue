<script setup lang="ts">
import { computed, ref } from 'vue';
import PublicIssuanceTurnstile from '../../../cockpit/components/PublicIssuanceTurnstile.vue';

type Props = { amount_minor: number; currency: string; endpoints: Record<string, string>; turnstile: { enabled: boolean; site_key?: string } };
const props = defineProps<Props>();
const registration = ref<any>(null);
const pin = ref('');
const loading = ref(false);
const error = ref('');
const turnstileToken = ref('');
const amount = computed(() => `${props.currency} ${(props.amount_minor / 100).toFixed(2)}`);

async function start() {
    loading.value = true; error.value = '';
    try {
        const response = await fetch(props.endpoints.start, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '', 'Accept': 'application/json', 'Content-Type': 'application/json', ...(turnstileToken.value ? { 'X-Turnstile-Token': turnstileToken.value } : {}) }, body: JSON.stringify({ idempotency_key: crypto.randomUUID() }) });
        if (!response.ok) throw new Error((await response.json()).message ?? 'Unable to start registration.');
        registration.value = await response.json();
    } catch (e: any) { error.value = e.message; } finally { loading.value = false; }
}
async function complete() {
    if (!registration.value) return;
    loading.value = true; error.value = '';
    const url = props.endpoints.complete.replace('__REFERENCE__', registration.value.reference);
    try {
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '', 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ pin: pin.value }) });
        const body = await response.json(); if (!response.ok) throw new Error(body.message ?? 'Unable to finish registration.'); registration.value = body;
    } catch (e: any) { error.value = e.message; } finally { loading.value = false; }
}
</script>
<template>
    <main class="mx-auto max-w-2xl space-y-6 p-6">
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">QR Ph self-registration</p>
            <h1 class="mt-2 text-3xl font-semibold">Open your x-change account</h1>
            <p class="mt-3 text-slate-600">Pay {{ amount }} through QR Ph. Your verified payer name and mobile are used to create your account. KYC is completed later when a protected operation requires it.</p>
            <PublicIssuanceTurnstile v-if="props.turnstile.enabled && props.turnstile.site_key && !registration" :site-key="props.turnstile.site_key" @verified="turnstileToken = $event" />
            <button v-if="!registration" :disabled="loading" class="mt-6 rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50" @click="start">Are you ready?</button>
            <p v-if="registration" class="mt-5 rounded-xl bg-slate-50 p-4 text-sm">{{ registration.status === 'awaiting_payment' ? 'Scan the QR Ph payment instructions below. This page will be ready for your PIN after payment is verified.' : registration.status === 'payment_settled' ? 'Payment verified. Choose a PIN to finish creating your account.' : registration.status === 'active' ? 'Your account is ready. A cockpit link will be sent by SMS.' : 'Registration is being processed.' }}</p>
            <pre v-if="registration?.instructions" class="mt-4 overflow-auto rounded-xl bg-slate-900 p-4 text-xs text-white">{{ JSON.stringify(registration.instructions, null, 2) }}</pre>
            <div v-if="registration?.status === 'payment_settled'" class="mt-4 space-y-3"><input v-model="pin" inputmode="numeric" maxlength="8" placeholder="Choose a 4–8 digit PIN" class="w-full rounded-xl border px-4 py-3"><button :disabled="loading" class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white" @click="complete">Finish registration</button></div>
            <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
        </section>
    </main>
</template>
