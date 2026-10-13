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
            throw new Error(payload.message ?? 'The Pay Code details could not be loaded.');
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
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
                {{ error }}
            </div>
            <a :href="batch_pay_codes_url" class="mt-4 inline-flex text-sm font-semibold text-indigo-700">
                Back to batch Pay Codes
            </a>
        </div>
        <VoucherDetail v-else-if="detail" v-bind="detail" />
    </div>
</template>
