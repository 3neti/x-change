<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { parsePayCodeInput, parsePayCodeOcr } from './storedValueScan';

defineOptions({ layout: [] });

type Collection = {
    reference: string;
    status: string;
    amount_minor: number;
    currency: string;
    authorization_method?: 'collector' | 'holder_otp';
    available_minor?: number;
    balance_after_minor?: number;
    expires_at: string;
};
type CollectionSummary = Pick<Collection, 'reference' | 'status' | 'amount_minor' | 'currency' | 'authorization_method'> & {
    created_at: string | null;
};

const props = defineProps<{
    channel: 'cockpit' | 'operations';
    view?: 'collect' | 'activity' | 'exceptions';
    recent_collections?: CollectionSummary[];
    fixed_amount_minor: number;
    endpoints: { start: string; status: string; verify: string; commit: string };
}>();

const input = ref('');
const amount = ref('');
const otp = ref('');
const resumeReference = ref('');
const collection = ref<Collection | null>(null);
const showCollector = ref(false);
const busy = ref(false);
const scanning = ref(false);
const error = ref('');
const confirmedCode = ref('');
const idempotencyKey = ref('');
const formattedAmount = computed(() => money(collection.value?.amount_minor ?? 0));

function money(minor: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(minor / 100);
}

function endpoint(template: string): string {
    return template.replace('__REFERENCE__', encodeURIComponent(collection.value?.reference ?? ''));
}

async function submit(url: string, payload: Record<string, unknown>, key?: string): Promise<Collection> {
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            ...(key ? { 'Idempotency-Key': key } : {}),
        },
        body: JSON.stringify(payload),
    });
    const body = await response.json();
    if (!response.ok) {
        throw new Error(Object.values(body.errors ?? {}).flat().join(' ') || body.message || 'Collection could not continue.');
    }

    return body.data as Collection;
}

async function start(): Promise<void> {
    const code = parsePayCodeInput(input.value);
    const amountText = props.fixed_amount_minor > 0 ? String(props.fixed_amount_minor / 100) : String(amount.value).trim();
    const validAmount = /^\d+(?:\.\d{1,2})?$/.test(amountText);
    const [whole, fraction = ''] = amountText.split('.');
    const amountMinor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    if (!code || !validAmount || !Number.isSafeInteger(amountMinor) || amountMinor <= 0) {
        error.value = 'Enter a valid Pay Code and a positive amount in pesos.';
        return;
    }
    error.value = '';
    busy.value = true;
    idempotencyKey.value ||= crypto.randomUUID();
    try {
        collection.value = await submit(props.endpoints.start, {
            code,
            ...(props.fixed_amount_minor > 0 ? {} : { amount_minor: amountMinor }),
            currency: 'PHP',
        }, idempotencyKey.value);
        confirmedCode.value = code;
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'Collection could not start.';
    } finally {
        busy.value = false;
    }
}

async function resume(): Promise<void> {
    if (!/^[0-9A-HJKMNP-TV-Z]{26}$/i.test(resumeReference.value.trim())) {
        error.value = 'Enter the collection reference from the earlier attempt.';
        return;
    }
    busy.value = true;
    error.value = '';
    try {
        const url = props.endpoints.status.replace('__REFERENCE__', encodeURIComponent(resumeReference.value.trim()));
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('This collection cannot be opened in this session.');
        collection.value = (await response.json()).data as Collection;
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'Collection could not be resumed.';
    } finally {
        busy.value = false;
    }
}

async function openCollection(reference: string): Promise<void> {
    resumeReference.value = reference;
    await resume();
}

async function verify(): Promise<void> {
    if (!collection.value) return;
    error.value = '';
    busy.value = true;
    try {
        collection.value = await submit(endpoint(props.endpoints.verify), { code: otp.value });
        otp.value = '';
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'Holder verification failed.';
    } finally {
        busy.value = false;
    }
}

async function commit(): Promise<void> {
    if (!collection.value || !['ready', 'verified'].includes(collection.value.status)) return;
    error.value = '';
    busy.value = true;
    try {
        collection.value = await submit(endpoint(props.endpoints.commit), {});
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'Collection was not completed. Check the reference before retrying.';
    } finally {
        busy.value = false;
    }
}

async function scan(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    error.value = '';
    scanning.value = true;
    try {
        const { createWorker } = await import('tesseract.js');
        const worker = await createWorker('eng');
        try {
            const result = await worker.recognize(file);
            const code = parsePayCodeOcr(result.data.text);
            if (!code) {
                throw new Error('No single complete || Pay Code || mark was found. Type the code if the print is unclear.');
            }
            input.value = code;
            idempotencyKey.value = '';
        } finally {
            await worker.terminate();
        }
    } catch (reason) {
        error.value = reason instanceof Error ? reason.message : 'The image could not be read.';
    } finally {
        scanning.value = false;
        (event.target as HTMLInputElement).value = '';
    }
}

function reset(): void {
    showCollector.value = true;
    collection.value = null;
    input.value = '';
    amount.value = '';
    otp.value = '';
    confirmedCode.value = '';
    idempotencyKey.value = '';
    error.value = '';
}
</script>

<template>
    <Head title="Collect stored value" />
    <main class="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:py-12" data-testid="stored-value-collection">
        <div class="mx-auto w-full max-w-lg space-y-6">
            <header>
                <p class="text-sm font-semibold uppercase tracking-widest text-slate-500">Pay Code · Stored value</p>
                <h1 class="mt-2 text-3xl font-semibold">{{ props.view === 'activity' ? 'Collection activity' : props.view === 'exceptions' ? 'Unfinished collections' : 'Collect a payment' }}</h1>
                <p v-if="!props.view || props.view === 'collect'" class="mt-2 text-sm text-slate-600">{{ props.fixed_amount_minor > 0 ? 'Enter or scan the marked Pay Code to collect the configured fare.' : 'Enter or scan the marked Pay Code, then set the amount to collect.' }}</p>
                <button v-else type="button" class="mt-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold" @click="reset">New collection</button>
            </header>

            <section v-if="props.view === 'activity' || props.view === 'exceptions'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" data-testid="stored-value-collection-activity">
                <p v-if="!props.recent_collections?.length" class="text-sm text-slate-600">No collections in this view.</p>
                <ul v-else class="divide-y divide-slate-200">
                    <li v-for="item in props.recent_collections" :key="item.reference" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-mono text-xs text-slate-500">{{ item.reference }}</p>
                            <p class="font-semibold">{{ money(item.amount_minor) }} · {{ item.status }}</p>
                        </div>
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold" :disabled="busy" @click="openCollection(item.reference)">Open</button>
                    </li>
                </ul>
            </section>

            <section v-if="!props.view || props.view === 'collect' || showCollector || collection" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <form v-if="!collection" class="space-y-5" @submit.prevent="start">
                    <div>
                        <label for="collection-code" class="block text-sm font-medium">Pay Code</label>
                        <input id="collection-code" v-model="input" autofocus autocomplete="off" spellcheck="false"
                            placeholder="|| ABCD-1234 ||" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-lg uppercase focus:border-slate-950 focus:outline-none"
                            @input="idempotencyKey = ''" />
                        <label class="mt-3 inline-flex cursor-pointer items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium">
                            {{ scanning ? 'Reading image…' : 'Scan with camera' }}
                            <input class="sr-only" type="file" accept="image/*" capture="environment" :disabled="scanning || busy" @change="scan" />
                        </label>
                    </div>
                    <div v-if="props.fixed_amount_minor > 0" class="rounded-xl bg-slate-100 px-4 py-3 text-lg font-semibold">
                        Fare · {{ money(props.fixed_amount_minor) }}
                    </div>
                    <div v-else>
                        <label for="collection-amount" class="block text-sm font-medium">Amount to collect · PHP</label>
                        <input id="collection-amount" v-model="amount" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="25.00"
                            class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-lg focus:border-slate-950 focus:outline-none"
                            @input="idempotencyKey = ''" />
                    </div>
                    <button type="submit" :disabled="busy || scanning" class="w-full rounded-xl bg-slate-950 px-4 py-3 font-semibold text-white disabled:opacity-50">{{ busy ? 'Starting…' : 'Review collection' }}</button>
                </form>

                <div v-else class="space-y-5" aria-live="polite">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4">
                        <div><p class="text-sm text-slate-500">Collect</p><p class="text-3xl font-semibold">{{ formattedAmount }}</p></div>
                        <p v-if="confirmedCode" class="font-mono text-sm">|| {{ confirmedCode }} ||</p>
                    </div>
                    <p class="text-xs text-slate-500">Reference {{ collection.reference }}</p>
                    <template v-if="collection.status === 'pending'">
                        <p class="text-sm">The holder approval code was sent to the verified holder mobile.</p>
                        <form class="space-y-3" @submit.prevent="verify">
                            <label for="collection-otp" class="block text-sm font-medium">Holder approval code</label>
                            <input id="collection-otp" v-model="otp" inputmode="numeric" autocomplete="one-time-code" class="w-full rounded-xl border border-slate-300 px-4 py-3" />
                            <button type="submit" :disabled="busy" class="w-full rounded-xl bg-slate-950 px-4 py-3 font-semibold text-white disabled:opacity-50">Verify holder</button>
                        </form>
                    </template>
                    <template v-else-if="collection.status === 'verified' || collection.status === 'ready'">
                        <p class="text-sm">{{ collection.status === 'verified' ? 'Holder approved.' : 'Collector authorized.' }} Available balance: <strong>{{ money(collection.available_minor ?? 0) }}</strong></p>
                        <p v-if="(collection.available_minor ?? 0) < collection.amount_minor" class="text-sm font-medium text-red-700">The available balance is below the collection amount. Start a new collection for a lower amount.</p>
                        <p v-else class="text-sm">Check the amount before collecting.</p>
                        <button type="button" :disabled="busy || (collection.available_minor ?? 0) < collection.amount_minor" class="w-full rounded-xl bg-slate-950 px-4 py-3 font-semibold text-white disabled:opacity-50" @click="commit">{{ busy ? 'Collecting…' : `Collect ${formattedAmount}` }}</button>
                        <button type="button" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold" @click="reset">Start a new collection</button>
                    </template>
                    <template v-else-if="collection.status === 'committed'">
                        <p class="text-lg font-semibold text-emerald-700">Payment collected</p>
                        <p class="text-sm">Remaining Pay Code balance: <strong>{{ money(collection.balance_after_minor ?? 0) }}</strong></p>
                        <button type="button" class="w-full rounded-xl border border-slate-300 px-4 py-3 font-semibold" @click="reset">New collection</button>
                    </template>
                    <div v-else class="space-y-3"><p class="text-sm">This approval is no longer active.</p><button type="button" class="w-full rounded-xl border border-slate-300 px-4 py-3 font-semibold" @click="reset">Start a new collection</button></div>
                </div>
                <p v-if="error" role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ error }}</p>
            </section>
            <form v-if="!collection && (!props.view || props.view === 'collect')" class="flex gap-2" @submit.prevent="resume">
                <input v-model="resumeReference" aria-label="Collection reference" placeholder="Resume by reference" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                <button type="submit" :disabled="busy" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Resume</button>
            </form>
            <p v-if="!props.view || props.view === 'collect'" class="text-center text-xs text-slate-500">The balance is shown after collection authorization. Camera text recognition runs on this device.</p>
        </div>
    </main>
</template>
