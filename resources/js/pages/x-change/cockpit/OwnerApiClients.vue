<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CockpitLayout from '@/cockpit/layouts/CockpitLayout.vue';

type Client = { reference: string; name: string; status: string; scopes: string[]; suspend_url: string; revoke_url: string };
const props = defineProps<{ clients: Client[]; scopes: Record<string, string>; store_url: string; csrf_token: string }>();
const name = ref('');
const scopes = ref<string[]>(['capabilities:read', 'pay-codes:estimate', 'pay-codes:read']);
const rails = ref<string[]>(['automatic', 'INSTAPAY']);
const profiles = ref<string[]>(['disbursement']);
const maximum = ref('1000');
const daily = ref('5000');
const unbound = ref(false);
const acknowledged = ref(false);
const storedMaximum = ref('5');
const storedDaily = ref('20');
const credential = ref<{ client_id: string; client_secret: string } | null>(null);
const error = ref('');
const busy = ref(false);

async function createClient() {
    error.value = '';
    busy.value = true;
    const stored = scopes.value.some(scope => scope.startsWith('stored-value:'));
    try {
        const response = await fetch(props.store_url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrf_token },
            body: JSON.stringify({ name: name.value, scopes: scopes.value, currencies: ['PHP'], settlement_rails: rails.value,
                maximum_amount_minor: Math.round(Number(maximum.value) * 100), daily_principal_limit_minor: Math.round(Number(daily.value) * 100),
                voucher_profiles: profiles.value, unbound_pay_codes: unbound.value, acknowledge_secret_once: acknowledged.value,
                stored_value_spend: stored ? { enabled: true, currencies: ['PHP'], maximum_amount_minor: Math.round(Number(storedMaximum.value) * 100), daily_amount_minor: Math.round(Number(storedDaily.value) * 100) } : { enabled: false } }),
        });
        const body = await response.json();
        if (!response.ok) { error.value = Object.values(body.errors || {}).flat().join(' ') || body.message || 'Could not create the API client.'; return; }
        credential.value = { client_id: body.client_id, client_secret: body.client_secret };
        router.reload({ only: ['clients'] });
    } catch { error.value = 'The request did not complete. Check your clients before retrying.'; }
    finally { busy.value = false; }
}
</script>

<template>
    <Head title="API Clients" />
    <CockpitLayout>
        <main class="mx-auto max-w-3xl space-y-6 p-4">
            <h1 class="text-2xl font-semibold">API Clients</h1>
            <p>Create credentials for your own account. Your client secret is shown only once.</p>
            <form class="space-y-4 rounded-xl border p-4" @submit.prevent="createClient">
                <label class="block">Client name <input v-model="name" required maxlength="120" class="block w-full rounded border p-2" /></label>
                <fieldset><legend>Scopes</legend><label v-for="(description, scope) in props.scopes" :key="scope" class="block"><input v-model="scopes" type="checkbox" :value="scope" /> {{ scope }} — {{ scope === 'funded-orders:create' ? 'Create account-backed funded orders.' : description }}</label></fieldset>
                <fieldset><legend>Pay Code profiles</legend><label><input v-model="profiles" type="checkbox" value="disbursement" /> Disbursement</label> <label><input v-model="profiles" type="checkbox" value="stored_value" /> Reusable balance</label></fieldset>
                <fieldset><legend>Transfer network</legend><label v-for="rail in ['automatic', 'INSTAPAY', 'PESONET']" :key="rail" class="mr-3"><input v-model="rails" type="checkbox" :value="rail" /> {{ rail }}</label></fieldset>
                <label class="block">Maximum per issue (₱) <input v-model="maximum" type="number" min="0.01" step="0.01" required class="block rounded border p-2" /></label>
                <label class="block">Daily principal limit (₱) <input v-model="daily" type="number" min="0.01" step="0.01" required class="block rounded border p-2" /></label>
                <template v-if="scopes.some(scope => scope.startsWith('stored-value:'))"><label class="block">Maximum per spend (₱) <input v-model="storedMaximum" type="number" min="0.01" step="0.01" class="rounded border p-2" /></label><label class="block">Daily spend limit (₱) <input v-model="storedDaily" type="number" min="0.01" step="0.01" class="rounded border p-2" /></label></template>
                <label class="block"><input v-model="unbound" type="checkbox" /> Allow unbound Pay Codes</label>
                <label class="block"><input v-model="acknowledged" type="checkbox" required /> I understand the client secret is shown once.</label>
                <p v-if="error" role="alert">{{ error }}</p>
                <button type="submit" :disabled="busy" class="rounded bg-emerald-600 px-4 py-2 text-white disabled:opacity-50">{{ busy ? 'Creating…' : 'Create API client' }}</button>
            </form>
            <section v-if="credential" class="space-y-2 rounded-xl border p-4"><h2 class="font-semibold">Save these credentials now</h2><p>Client ID: <code>{{ credential.client_id }}</code></p><p>Client secret: <code>{{ credential.client_secret }}</code></p><button @click="credential = null">Done</button></section>
            <section><h2 class="text-xl font-semibold">Your clients</h2><article v-for="client in clients" :key="client.reference" class="my-3 rounded-xl border p-4"><strong>{{ client.name }}</strong> · {{ client.status }}<p>{{ client.scopes.join(', ') }}</p><button v-if="client.status === 'active'" class="mr-4" @click="router.post(client.suspend_url)">Suspend</button><button v-if="client.status !== 'revoked'" @click="router.post(client.revoke_url)">Revoke</button></article></section>
        </main>
    </CockpitLayout>
</template>
