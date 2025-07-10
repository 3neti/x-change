<!-- resources/js/pages/View.vue -->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { saveAs } from 'file-saver';
// import Modal from '@/Components/Modal.vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { useFormatCurrency } from '@/composables/useFormatCurrency';
import { useFormatDate } from '@/composables/useFormatDate';
import { useBankAlias } from '@/composables/useBankAlias';
import { useFlagEmoji } from '@/composables/useFlagEmoji';

// const props = defineProps({
//     vouchers: Array,
//     pagination: Object,
// });

const props = defineProps<{
    vouchers: {
        code: string;
        instructions: Record<string, any>;
        cash: {
            amount: number;
            currency: string;
            withdrawTransaction?: {
                confirmed: boolean;
                payload: {
                    destination_account: {
                        bank_code: string;
                        account_number: string;
                    };
                };
            };
        };
        metadata: Record<string, any>;
        inputs: {
            name: string;
            value: string;
        }[];
        contact?: {
            mobile: string;
            country?: string;
            bank_code?: string;
            account_number?: string;
            bank_account?: string;
        } | null;
        created_at: string;
        starts_at: string;
        redeemed_at: string;
        disbursed: boolean;
        expired_at: string;
    }[];
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}>();

const formatJson = (jsonData) => {
    try {
        return JSON.stringify(jsonData, null, 2);
    } catch (e) {
        return jsonData;
    }
};

const downloadCsv = () => {
    if (!props.vouchers.length) return;

    const csvHeaders = ['Voucher Code', 'Mobile', 'Metadata', 'Cash Data', 'Signature', 'Redeemed', 'Expired', 'Disbursed', 'Created At'];

    const csvContent = [
        csvHeaders.join(','),
        ...props.vouchers.map((voucher) => {
            return [
                voucher.code,
                voucher.mobile ?? 'N/A',
                formatJson({ ...voucher.metadata, signature: undefined }).replace(/"/g, '""'),
                formatJson(voucher.cash).replace(/"/g, '""'),
                voucher.metadata.signature ? 'Has Signature' : 'No Signature',
                voucher.redeemed ? 'Yes' : 'No',
                voucher.expired ? 'Yes' : 'No',
                voucher.disbursed ? 'Yes' : 'No',
                voucher.created_at,
            ]
                .map((field) => `"${field}"`)
                .join(',');
        }),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    saveAs(blob, 'vouchers.csv');
};

const vouchers = ref(props.vouchers);
const pagination = ref(props.pagination);
const redeemedFilter = ref('');
const disbursedFilter = ref('');
const fieldFilter = ref('');
const fieldFilterValue = ref('');

const filteredVouchers = computed(() => {
    return props.vouchers.filter((voucher) => {
        const matchesRedeemed = !redeemedFilter.value || String(voucher.redeemed) === redeemedFilter.value;
        const matchesDisbursed = !disbursedFilter.value || String(voucher.disbursed) === disbursedFilter.value;
        const matchesFieldFilter = (() => {
            const value = fieldFilterValue.value.toLowerCase();
            if (!fieldFilter.value || !value) return true;

            switch (fieldFilter.value) {
                case 'voucher_code':
                    return value.length >= 4 && voucher.code.toLowerCase().includes(value);
                case 'mobile':
                    return value.length >= 11 && (voucher.mobile ?? '').toLowerCase().includes(value);
                case 'name':
                    return value.length >= 1 && (voucher.metadata.name ?? '').toLowerCase().includes(value);
                default:
                    return true;
            }
        })();

        return matchesRedeemed && matchesDisbursed && matchesFieldFilter;
    });
});

watch(fieldFilter, () => (fieldFilterValue.value = ''));

// const showModal = ref(false);
const selectedVoucher = ref(null);

const openModal = (voucher) => {
    if (voucher.redeemed && !voucher.disbursed) {
        selectedVoucher.value = voucher;
        showModal.value = true;
    }
};

const confirmReDisbursement = () => {
    if (!selectedVoucher.value) return;
    router.post(
        route('vouchers.re-disburse'),
        {
            voucher_code: selectedVoucher.value.code,
        },
        {
            onSuccess: () => (showModal.value = false),
            onError: (error) => console.error('Re-disbursement error:', error),
        },
    );
};

const closeModal = () => {
    showModal.value = false;
    selectedVoucher.value = null;
};

const fetchPage = (page) => {
    router.get(route('view', { page }), {
        preserveState: true,
        preserveScroll: true,
        only: ['vouchers', 'pagination'],
    });
};

const formatCurrency = useFormatCurrency();
const { formatDate } = useFormatDate();

watch(
    () => props.vouchers,
    (newVouchers) => (vouchers.value = newVouchers),
);
watch(
    () => props.pagination,
    (newPagination) => (pagination.value = newPagination),
);

const { getFlagEmoji } = useFlagEmoji();
const { getBankAlias } = useBankAlias();
</script>

<template>
    <AppLayout>
        <template #header>
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Checks List</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Checks List</h3>
                        <Button @click="downloadCsv">Download CSV</Button>
                    </div>

                    <div class="mb-4 flex items-center gap-4">
                        <div>
                            <label for="redeemedFilter" class="block text-sm font-medium text-gray-700">Redeemed</label>
                            <select id="redeemedFilter" v-model="redeemedFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">All</option>
                                <option value="true">Yes</option>
                                <option value="false">No</option>
                            </select>
                        </div>

                        <div>
                            <label for="disbursedFilter" class="block text-sm font-medium text-gray-700">Disbursed</label>
                            <select id="disbursedFilter" v-model="disbursedFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">All</option>
                                <option value="true">Yes</option>
                                <option value="false">No</option>
                            </select>
                        </div>

                        <div>
                            <label for="fieldFilter" class="block text-sm font-medium text-gray-700">Filter By</label>
                            <select id="fieldFilter" v-model="fieldFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select Field</option>
                                <option value="voucher_code">Voucher Code</option>
                                <option value="mobile">Mobile</option>
                                <option value="name">Name</option>
                            </select>
                        </div>

                        <div class="flex-1">
                            <label for="fieldFilterValue" class="block text-sm font-medium text-gray-700">Filter Value</label>
                            <input
                                id="fieldFilterValue"
                                type="text"
                                v-model="fieldFilterValue"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                placeholder="Enter filter value"
                            />
                        </div>
                    </div>

                    <table class="min-w-full border bg-white">
                        <thead>
                            <!-- Group Header Row -->
                            <tr class="bg-gray-100 text-sm">
                                <!--                            <th class="px-4 py-2 border" rowspan="2">Cash Code</th>-->
                                <!--                            <th class="px-4 py-2 border" rowspan="2">Amount</th>-->
                                <th class="border px-4 py-2 text-center" colspan="3">x-Check</th>
                                <th class="border px-4 py-2 text-center" colspan="1">Payable</th>
                                <th class="border px-4 py-2 text-center" colspan="3">Encashed</th>
                            </tr>

                            <!-- Sub Header Row -->
                            <tr class="bg-gray-200 text-sm">
                                <th class="border px-4 py-2">date</th>
                                <th class="border px-4 py-2">#</th>
                                <th class="border px-4 py-2">amount</th>
                                <th class="border px-4 py-2">to</th>
                                <th class="border px-4 py-2">using</th>
                                <th class="border px-4 py-2">credited to</th>
                                <th class="border px-4 py-2">since</th>
                            </tr>
                        </thead>
                        <tbody>
                        <tr
                            v-for="voucher in filteredVouchers"
                            :key="voucher.code"
                            class="cursor-pointer border-t hover:bg-gray-100"
                            @click="openModal(voucher)"
                        >
                            <!-- Created At -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">
                                <div class="font-semibold text-center truncate">
                                    {{ formatDate(voucher.created_at, { fallbackFormat: 'DD MMM YYYY', showRelative: false }) }}
                                </div>
                            </td>

                            <!-- Voucher Code -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">
                                <div class="font-semibold text-center truncate">{{ voucher.code }}</div>
                            </td>

                            <!-- Amount -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">
                                <div class="flex justify-between items-center w-full truncate">
            <span class="text-left">
                {{ formatCurrency(voucher.cash.amount, { isMinor: true, detailed: true }).symbol }}
            </span>
                                    <span class="text-right font-semibold">
                {{ formatCurrency(voucher.cash.amount, { isMinor: true, detailed: true }).amount }}
            </span>
                                </div>
                            </td>

                            <!-- Cash Type -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">

                                <div v-if="voucher.instructions.cash.validation.mobile" class="font-semibold text-center truncate">
                                    {{ getFlagEmoji(voucher.instructions.cash.validation.country ?? 'PH') }} {{ voucher.instructions.cash.validation.mobile }}
                                </div>
                                <div v-else class="font-semibold text-center truncate">CASH</div>
                            </td>

                            <!-- Redeemer Contact -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">
                                <div v-if="voucher.contact" class="font-semibold text-center truncate">
                                    {{ getFlagEmoji(voucher.contact?.country ?? 'PH') }} {{ voucher.contact.mobile }}
                                </div>
                                <div v-else class="text-center text-gray-500"></div>
                            </td>

                            <!-- Bank Account -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">
                                <div
                                    v-if="voucher.cash.withdrawTransaction"
                                    class="flex justify-between items-center gap-x-2 w-full"
                                >
            <span class="text-left truncate">
                {{ getBankAlias(voucher.cash.withdrawTransaction.payload.destination_account.bank_code ?? '') }}
            </span>
                                    <span class="text-center font-semibold truncate">
                {{ voucher.cash.withdrawTransaction.payload.destination_account.account_number }}
            </span>
                                </div>
                                <div v-else class="text-center text-gray-400 italic">
                                    Not Available
                                </div>
                            </td>

                            <!-- Redeemed At -->
                            <td class="border px-2 py-1 align-middle text-sm whitespace-nowrap">
                                <div class="font-semibold text-center truncate">{{ formatDate(voucher.redeemed_at) }}</div>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <div class="mt-4 flex items-center justify-between">
                        <button
                            v-if="pagination.prev_page_url"
                            @click="fetchPage(pagination.current_page - 1)"
                            class="rounded-md bg-gray-500 px-4 py-2 text-white"
                        >
                            Previous
                        </button>
                        <span class="text-gray-700">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                        <button
                            v-if="pagination.next_page_url"
                            @click="fetchPage(pagination.current_page + 1)"
                            class="rounded-md bg-blue-500 px-4 py-2 text-white"
                        >
                            Next
                        </button>
                    </div>

                    <div v-if="!vouchers.length" class="mt-4 text-gray-500">No vouchers available.</div>
                </div>
            </div>
        </div>

        <!--        <Modal :show="showModal" @close="closeModal">-->
        <!--            <div class="p-6">-->
        <!--                <h2 class="text-xl font-semibold text-gray-800">Confirm Re-Disbursement</h2>-->
        <!--                <p class="mt-2 text-gray-600">Are you sure you want to re-disburse voucher <strong>{{ selectedVoucher?.code }}</strong>?</p>-->
        <!--                <div class="mt-6 flex justify-end space-x-4">-->
        <!--                    <button class="px-4 py-2 bg-gray-500 text-white rounded-md" @click="closeModal">Cancel</button>-->
        <!--                    <button class="px-4 py-2 bg-blue-500 text-white rounded-md" @click="confirmReDisbursement">Confirm</button>-->
        <!--                </div>-->
        <!--            </div>-->
        <!--        </Modal>-->
    </AppLayout>
</template>

<style scoped>
pre {
    max-height: 150px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.signature-box img {
    max-height: 80px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 2px;
}
</style>
