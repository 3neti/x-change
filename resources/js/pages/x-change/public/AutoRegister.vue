<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicIssuanceTurnstile from '../../../cockpit/components/PublicIssuanceTurnstile.vue';

type Props = { amount_minor: number; currency: string; endpoints: Record<string, string>; turnstile: { enabled: boolean; site_key?: string } };
const props = defineProps<Props>();
const registration = ref<any>(null);
const pin = ref('');
const loading = ref(false);
const error = ref('');
const turnstileToken = ref('');
const attemptKeyStorage = 'x-change.public-auto-register.idempotency-key';
const registrationStorage = 'x-change.public-auto-register.reference';
let statusTimer: ReturnType<typeof setInterval> | null = null;
const amount = computed(() => `${props.currency} ${(props.amount_minor / 100).toFixed(2)}`);
const qrSource = computed(() => {
    const qr = registration.value?.instructions?.qr_code;

    if (typeof qr?.base64_payload !== 'string' || qr.base64_payload === '') {
        return null;
    }

    return `data:${qr.mime_type ?? 'image/png'};base64,${qr.base64_payload}`;
});

function csrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=').slice(1).join('=');

    return token ? decodeURIComponent(token) : '';
}

function statusUrl(reference: string): string {
    return props.endpoints.status.replace('__REFERENCE__', reference);
}

function isTerminal(status: string | undefined): boolean {
    return status === 'active' || status === 'expired' || status === 'cancelled';
}

async function refreshStatus(): Promise<void> {
    const reference = registration.value?.reference;

    if (!reference) {
        return;
    }

    try {
        const response = await fetch(statusUrl(reference), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(body.message ?? 'Unable to check payment status.');
        }

        registration.value = body;

        if (isTerminal(body.status) && statusTimer !== null) {
            clearInterval(statusTimer);
            statusTimer = null;
        }
    } catch (e: any) {
        error.value = e.message;
    }
}

function beginStatusPolling(): void {
    if (statusTimer !== null) {
        clearInterval(statusTimer);
    }

    statusTimer = setInterval(() => void refreshStatus(), 3000);
}

async function start() {
    loading.value = true; error.value = '';
    try {
        let attemptKey = sessionStorage.getItem(attemptKeyStorage);

        if (!attemptKey) {
            attemptKey = crypto.randomUUID();
            sessionStorage.setItem(attemptKeyStorage, attemptKey);
        }

        const response = await fetch(props.endpoints.start, { method: 'POST', headers: { 'X-XSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json', ...(turnstileToken.value ? { 'X-Turnstile-Token': turnstileToken.value } : {}) }, body: JSON.stringify({ idempotency_key: attemptKey }) });
        const body = await response.json().catch(() => ({}));
        if (response.status === 422 && body.errors?.idempotency_key) {
            sessionStorage.removeItem(attemptKeyStorage);
        }
        if (!response.ok) throw new Error(body.message ?? 'Unable to start registration.');
        registration.value = body;
        sessionStorage.setItem(registrationStorage, body.reference);
        sessionStorage.removeItem(attemptKeyStorage);
        beginStatusPolling();
    } catch (e: any) { error.value = e.message; } finally { loading.value = false; }
}

onMounted(async () => {
    const reference = sessionStorage.getItem(registrationStorage);

    if (!reference) {
        return;
    }

    sessionStorage.removeItem(attemptKeyStorage);

    registration.value = { reference, status: 'checking_payment' };
    await refreshStatus();
    if (!isTerminal(registration.value?.status)) {
        beginStatusPolling();
    }
});

onBeforeUnmount(() => {
    if (statusTimer !== null) {
        clearInterval(statusTimer);
    }
});
async function complete() {
    if (!registration.value) return;
    loading.value = true; error.value = '';
    const url = props.endpoints.complete.replace('__REFERENCE__', registration.value.reference);
    try {
        const response = await fetch(url, { method: 'POST', headers: { 'X-XSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ pin: pin.value }) });
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
            <PublicIssuanceTurnstile v-if="props.turnstile.enabled && props.turnstile.site_key && !registration" :site-key="props.turnstile.site_key" action="public_auto_register" @verified="turnstileToken = $event" />
            <button v-if="!registration" :disabled="loading" class="mt-6 rounded-xl bg-indigo-600 px-5 py-3 font-semibold text-white disabled:opacity-50" @click="start">Are you ready?</button>
            <div v-if="registration" class="mt-5 rounded-xl bg-slate-50 p-4 text-sm">
                <p>{{ registration.status === 'awaiting_payment' ? 'Scan the QR Ph payment instructions below. We will check for payment automatically.' : registration.status === 'payment_settled' ? 'Payment verified. Choose a PIN to finish creating your account.' : registration.status === 'active' ? 'Your account is ready. A cockpit link will be sent by SMS.' : 'Checking payment status.' }}</p>
                <p class="mt-2 text-xs text-slate-500">Registration reference: {{ registration.reference }}</p>
                <button v-if="registration.status === 'awaiting_payment' || registration.status === 'checking_payment'" type="button" class="mt-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700" :disabled="loading" @click="refreshStatus">Check payment</button>
            </div>
            <div v-if="registration?.instructions" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center">
                <p class="text-sm font-semibold text-slate-900">Scan to pay {{ amount }}</p>
                <img v-if="qrSource" :src="qrSource" alt="QR Ph payment code" class="mx-auto mt-4 h-72 w-72 rounded-xl bg-white p-3 shadow-sm" />
                <p v-else class="mt-4 text-sm text-red-600">The payment QR is temporarily unavailable. Please refresh and try again.</p>
                <p class="mt-3 text-xs text-slate-500">Use GCash, Maya, or another QR Ph-enabled bank or wallet.</p>
            </div>
            <div v-if="registration?.status === 'payment_settled'" class="mt-4 space-y-3"><input v-model="pin" inputmode="numeric" maxlength="8" placeholder="Choose a 4–8 digit PIN" class="w-full rounded-xl border px-4 py-3"><button :disabled="loading" class="rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white" @click="complete">Finish registration</button></div>
            <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
        </section>
    </main>
</template>
