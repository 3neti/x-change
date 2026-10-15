<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import VoucherDetail from '../../../cockpit/pages/VoucherDetail.vue';
import type { CockpitVoucherDetailPageProps } from '../../../cockpit/types';

const props = defineProps<{
    batch_reference: string;
    code: string;
    detail_endpoint: string;
    batch_pay_codes_url: string;
}>();

const detail = ref<CockpitVoucherDetailPageProps | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const actionBusy = ref(false);
const actionMessage = ref<string | null>(null);
const actionError = ref<string | null>(null);
const extensionHours = ref<24 | 168>(24);
const publicActions = computed(() => (detail.value as any)?.public_actions ?? null);
const hasPublicActions = computed(() => publicActions.value !== null && Object.values(publicActions.value).some((value) => value === true));

async function responsePayload(response: Response): Promise<Record<string, any>> {
    if (typeof response.json !== 'function') {
        return {};
    }

    try {
        return await response.json();
    } catch {
        return {};
    }
}

onMounted(async () => {
    const token = window.sessionStorage.getItem(`x-change.batch-token:${props.batch_reference}`);

    try {
        const response = await fetch(props.detail_endpoint, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(token ? { 'X-Issuance-Batch-Token': token } : {}),
            },
        });
        const payload = await responsePayload(response);

        if (!response.ok) {
            throw new Error(response.status === 404
                ? 'This distribution workspace has expired or is not available in this browser. Open the batch workspace to recover access, then view this Pay Code again.'
                : (payload.message ?? 'The Pay Code details could not be loaded.'));
        }

        detail.value = {
            ...payload,
            explorer_url: props.batch_pay_codes_url,
            distribution_url: null,
        } as CockpitVoucherDetailPageProps;
    } catch (exception) {
        error.value = exception instanceof Error
            ? exception.message
            : 'The Pay Code details could not be loaded.';
    } finally {
        loading.value = false;
    }
});

async function applyAction(action: 'extend' | 'cancel' | 'expire' | 'request_refund'): Promise<void> {
    const actions = (detail.value as any)?.public_actions;
    if (!actions || actionBusy.value) return;

    const confirmationMessage = action === 'extend'
        ? `Extend this Pay Code by ${extensionHours.value === 24 ? '24 hours' : '7 days'}?`
        : action === 'cancel'
            ? 'Cancel this Pay Code? A refund request can be opened after cancellation.'
            : action === 'expire'
                ? 'Expire this Pay Code now?'
                : 'Request a refund for this Pay Code?';

    if (!window.confirm(confirmationMessage)) return;

    actionBusy.value = true;
    actionError.value = null;
    try {
        const token = window.sessionStorage.getItem(`x-change.batch-token:${props.batch_reference}`);
        const response = await fetch(actions.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { 'X-Issuance-Batch-Token': token } : {}) },
            body: JSON.stringify({ action, ...(action === 'extend' ? { extension_hours: extensionHours.value } : {}), reason: 'Public batch recovery' }),
        });
        const payload = await responsePayload(response);
        if (!response.ok) throw new Error(payload.message ?? 'The Pay Code action could not be completed.');
        actionMessage.value = payload.message ?? 'Pay Code updated.';
        window.setTimeout(() => window.location.reload(), 500);
    } catch (exception) {
        actionError.value = exception instanceof Error ? exception.message : 'The Pay Code action could not be completed.';
    } finally {
        actionBusy.value = false;
    }
}
</script>

<template>
    <div>
        <div v-if="loading" class="p-6 text-sm text-slate-600" data-testid="batch-pay-code-detail-loading">
            Loading Pay Code details…
        </div>
        <div v-else-if="error" class="mx-auto max-w-2xl p-6" data-testid="batch-pay-code-detail-error">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800" data-testid="batch-pay-code-detail-access-error">
                {{ error }}
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <a :href="batch_pay_codes_url" class="inline-flex rounded-full bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">
                    Open batch workspace
                </a>
                <a :href="batch_pay_codes_url" class="inline-flex items-center rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                    Back to batch Pay Codes
                </a>
            </div>
        </div>
        <template v-else-if="detail">
            <section v-if="hasPublicActions" class="mx-auto max-w-2xl border-b border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
                <p class="text-sm font-bold text-amber-900 dark:text-amber-100">Recovery actions</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <template v-if="publicActions.can_extend">
                        <label class="sr-only" :for="`extension-hours-${props.code}`">Extension duration</label>
                        <select :id="`extension-hours-${props.code}`" v-model.number="extensionHours" :disabled="actionBusy" class="rounded-full border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-900 disabled:opacity-50">
                            <option :value="24">24 hours</option>
                            <option :value="168">7 days</option>
                        </select>
                        <button type="button" :disabled="actionBusy" data-testid="batch-pay-code-extend" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="applyAction('extend')">Extend expiry</button>
                    </template>
                    <button v-if="publicActions.can_cancel" type="button" :disabled="actionBusy" data-testid="batch-pay-code-cancel" class="rounded-full border border-amber-300 px-4 py-2 text-sm font-bold text-amber-900 disabled:opacity-50" @click="applyAction('cancel')">Cancel Pay Code</button>
                    <button v-if="publicActions.can_expire" type="button" :disabled="actionBusy" data-testid="batch-pay-code-expire" class="rounded-full border border-amber-300 px-4 py-2 text-sm font-bold text-amber-900 disabled:opacity-50" @click="applyAction('expire')">Expire Pay Code</button>
                    <button v-if="publicActions.can_request_refund" type="button" :disabled="actionBusy" data-testid="batch-pay-code-refund" class="rounded-full bg-amber-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="applyAction('request_refund')">Request refund</button>
                </div>
                <p v-if="actionMessage" class="mt-3 text-sm font-semibold text-emerald-800">{{ actionMessage }}</p>
                <p v-if="actionError" class="mt-3 text-sm font-semibold text-rose-700">{{ actionError }}</p>
            </section>
            <VoucherDetail v-bind="detail" />
        </template>
    </div>
</template>
