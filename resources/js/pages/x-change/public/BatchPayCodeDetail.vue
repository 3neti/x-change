<script setup lang="ts">
import { onMounted, ref } from 'vue';
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
        const payload = await response.json();

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
        <VoucherDetail v-else-if="detail" v-bind="detail" />
    </div>
</template>
