<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type {
    CockpitQuickGenerateDraftContract,
    CockpitQuickGenerateMutationContract,
    CockpitQuickGenerateTemplate,
} from '../types';

const props = defineProps<{
    mutationContract?: CockpitQuickGenerateMutationContract;
    draftContract?: CockpitQuickGenerateDraftContract;
    templates: CockpitQuickGenerateTemplate[];
}>();

const emit = defineEmits<{
    submitStart: [payload: Record<string, unknown>];
    submitSuccess: [response: Record<string, unknown>];
    submitError: [error: Record<string, unknown>];
}>();

const selectedTemplate = ref(stringValue(props.draftContract?.template_key) ?? props.templates[0]?.key ?? 'money-changer');
const amount = ref(stringValue(props.draftContract?.amount) ?? '25');
const currency = ref(stringValue(props.draftContract?.currency) ?? 'PHP');
const recipientReference = ref(stringValue(props.draftContract?.recipient_reference) ?? '');
const purpose = ref(stringValue(props.draftContract?.purpose) ?? '');
const processing = ref(false);
const lastStatus = ref('ready');
const lastMessage = ref('Submit will call the existing x-change issuance handoff route.');
const lastResponse = ref<Record<string, unknown> | null>(null);

const routeUrl = computed<string | null>(() => stringValue(props.mutationContract?.route_url));
const routeName = computed<string>(() => stringValue(props.mutationContract?.route) ?? 'not-loaded');
const allowedMethods = computed<string[]>(() => {
    if (!Array.isArray(props.mutationContract?.allowed_methods)) {
        return [];
    }

    return props.mutationContract.allowed_methods
        .map((method) => method.toUpperCase())
        .filter((method) => method.length > 0);
});

const canSubmit = computed<boolean>(() => {
    return props.mutationContract?.runtime_enabled === true
        && routeUrl.value !== null
        && allowedMethods.value.includes('POST');
});

const resultCode = computed<string | null>(() => {
    return stringValue(dataGet(lastResponse.value, ['result', 'code']));
});

const cockpitDetailUrl = computed<string | null>(() => {
    return stringValue(dataGet(lastResponse.value, ['result', 'links', 'cockpit_detail']));
});

const canRefreshReadModel = computed<boolean>(() => {
    return lastResponse.value !== null && !processing.value;
});

async function submit(): Promise<void> {
    if (!canSubmit.value || processing.value || routeUrl.value === null) {
        return;
    }

    processing.value = true;
    lastStatus.value = 'submitting';
    lastMessage.value = 'Submitting through the idempotency-protected issuance handoff.';

    const idempotencyKey = generateIdempotencyKey();
    const payload = buildPayload();

    emit('submitStart', payload);

    try {
        const response = await fetch(routeUrl.value, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'Idempotency-Key': idempotencyKey,
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeader(),
            },
            body: JSON.stringify(payload),
        });

        const body = await safeJson(response);

        if (!response.ok) {
            lastStatus.value = 'failed';
            lastMessage.value = stringValue(body.message) ?? 'Quick Generate submission failed.';
            emit('submitError', body);

            return;
        }

        lastStatus.value = body.status === 'replayed' ? 'replayed' : 'issued';
        lastMessage.value = body.status === 'replayed'
            ? 'Idempotent replay returned the existing operator-safe result.'
            : 'Pay Code issued through the existing x-change issuance handoff.';
        lastResponse.value = body;
        emit('submitSuccess', body);
    } catch (error) {
        const body = {
            message: error instanceof Error ? error.message : 'Quick Generate submission failed.',
        };

        lastStatus.value = 'failed';
        lastMessage.value = body.message;
        emit('submitError', body);
    } finally {
        processing.value = false;
    }
}

function refreshReadModel(): void {
    if (!canRefreshReadModel.value) {
        return;
    }

    router.reload({
        only: ['quick_generate_read_model'],
        preserveScroll: true,
    });
}

function buildPayload(): Record<string, unknown> {
    const normalizedAmount = Number(amount.value);
    const mobile = recipientReference.value.trim();
    const message = purpose.value.trim();

    return {
        cash: {
            amount: Number.isFinite(normalizedAmount) ? normalizedAmount : amount.value,
            currency: currency.value.trim() || 'PHP',
            validation: {},
        },
        inputs: {
            fields: [],
        },
        feedback: {
            mobile: mobile === '' ? null : mobile,
        },
        rider: {
            message: message === '' ? null : message,
        },
        metadata: {
            custom: {
                cockpit: {
                    template_key: selectedTemplate.value,
                    source: 'cockpit.quick-generate',
                },
            },
        },
    };
}

function generateIdempotencyKey(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `cockpit-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    return token ? { 'X-CSRF-TOKEN': token } : {};
}

async function safeJson(response: Response): Promise<Record<string, unknown>> {
    try {
        const body = await response.json();

        return typeof body === 'object' && body !== null ? body as Record<string, unknown> : {};
    } catch {
        return {};
    }
}

function stringValue(value: unknown): string | null {
    if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
        return null;
    }

    const normalized = String(value).trim();

    return normalized === '' ? null : normalized;
}

function dataGet(source: unknown, path: string[]): unknown {
    return path.reduce<unknown>((value, key) => {
        if (typeof value !== 'object' || value === null || !(key in value)) {
            return null;
        }

        return (value as Record<string, unknown>)[key];
    }, source);
}
</script>

<template>
    <form
        class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-900/70 dark:bg-emerald-950/40"
        data-testid="cockpit-quick-generate-submit-panel"
        @submit.prevent="submit"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700 dark:text-emerald-300">
                    Mutation Wave 1E
                </p>
                <h3 class="mt-2 text-sm font-semibold text-slate-950 dark:text-slate-50">
                    Submit through existing issuance handoff
                </h3>
                <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">
                    Route: {{ routeName }} · {{ routeUrl ?? 'not available' }}
                </p>
            </div>
            <span
                class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
                data-testid="cockpit-quick-generate-submit-status"
            >
                {{ lastStatus }}
            </span>
        </div>

        <div class="mt-4 grid gap-3">
            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Template
                <select
                    v-model="selectedTemplate"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-template"
                    :disabled="processing"
                >
                    <option v-for="template in templates" :key="template.key" :value="template.key">
                        {{ template.name }}
                    </option>
                </select>
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Amount
                <input
                    v-model="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-amount"
                    :disabled="processing"
                >
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Currency
                <input
                    v-model="currency"
                    type="text"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-currency"
                    :disabled="processing"
                >
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Recipient mobile/reference
                <input
                    v-model="recipientReference"
                    type="text"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-recipient"
                    :disabled="processing"
                >
            </label>

            <label class="grid gap-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                Purpose/message
                <textarea
                    v-model="purpose"
                    rows="2"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-quick-generate-submit-purpose"
                    :disabled="processing"
                />
            </label>
        </div>

        <button
            type="submit"
            class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600 dark:disabled:bg-slate-800 dark:disabled:text-slate-500"
            data-testid="cockpit-quick-generate-submit-button"
            :disabled="!canSubmit || processing"
        >
            {{ processing ? 'Submitting…' : 'Generate Pay Code' }}
        </button>

        <p class="mt-3 text-xs leading-5 text-slate-600 dark:text-slate-300">
            {{ lastMessage }}
        </p>

        <div
            v-if="lastResponse"
            class="mt-4 rounded-xl border border-emerald-200 bg-white p-3 text-xs text-slate-700 dark:border-emerald-900 dark:bg-slate-950 dark:text-slate-300"
            data-testid="cockpit-quick-generate-result-panel"
        >
            <p class="font-semibold text-slate-950 dark:text-slate-50">
                Generated Pay Code: {{ resultCode ?? 'operator-safe result received' }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a
                    v-if="cockpitDetailUrl"
                    :href="cockpitDetailUrl"
                    class="rounded-lg bg-slate-950 px-3 py-2 font-semibold text-white dark:bg-slate-100 dark:text-slate-950"
                    data-testid="cockpit-quick-generate-result-link"
                >
                    Open Cockpit detail
                </a>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-2 font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-800 dark:text-slate-200"
                    data-testid="cockpit-quick-generate-refresh-button"
                    :disabled="!canRefreshReadModel"
                    @click="refreshReadModel"
                >
                    Refresh read model
                </button>
            </div>
            <p class="mt-3 leading-5 text-slate-500 dark:text-slate-400">
                No automatic redirect is performed. The operator chooses whether to refresh Cockpit data or open the generated Pay Code detail.
            </p>
        </div>
    </form>
</template>
