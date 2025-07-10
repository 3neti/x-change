<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue';

// Props from controller
const props = defineProps({
    vouchers: Array,
    pagination: Object,
})

// Reactive state
const vouchers = ref(props.vouchers)
const pagination = ref(props.pagination)

// Computed for paginated rendering
const hasNextPage = computed(() => !!pagination.value.next_page_url)
const hasPrevPage = computed(() => !!pagination.value.prev_page_url)

// Pagination logic
const fetchPage = (page) => {
    router.get(route('vouchers.index', { page }), {
        preserveState: true,
        preserveScroll: true,
        only: ['vouchers', 'pagination'],
    })
}
</script>

<template>
    <AppLayout>
        <Head title="Vouchers" />

        <template #header>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">My Vouchers</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div v-if="!vouchers.length" class="text-gray-500 dark:text-gray-400">No vouchers found.</div>

                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-200">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Starts</th>
                            <th class="px-4 py-2 text-left">Expires</th>
                            <th class="px-4 py-2 text-left">Redeemed</th>
                            <th class="px-4 py-2 text-left">Processed</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="voucher in vouchers" :key="voucher.code">
                            <td class="px-4 py-2 font-medium text-blue-600 dark:text-blue-400">{{ voucher.code }}</td>
                            <td class="px-4 py-2">{{ voucher.starts_at ?? '—' }}</td>
                            <td class="px-4 py-2">{{ voucher.expires_at ?? '—' }}</td>
                            <td class="px-4 py-2">
                  <span :class="voucher.redeemed_at ? 'text-green-600' : 'text-gray-400'">
                    {{ voucher.redeemed_at ? 'Yes' : 'No' }}
                  </span>
                            </td>
                            <td class="px-4 py-2">
                  <span :class="voucher.processed ? 'text-blue-600' : 'text-gray-400'">
                    {{ voucher.processed ? '✔ Processed' : '—' }}
                  </span>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <!-- Pagination controls -->
                    <div class="mt-6 flex justify-between items-center">
                        <button
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded disabled:opacity-50"
                            :disabled="!hasPrevPage"
                            @click="fetchPage(pagination.current_page - 1)"
                        >
                            Previous
                        </button>

                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            Page {{ pagination.current_page }} of {{ pagination.last_page }} —
                            {{ pagination.total }} total
                        </div>

                        <button
                            class="px-4 py-2 bg-blue-500 text-white rounded disabled:opacity-50"
                            :disabled="!hasNextPage"
                            @click="fetchPage(pagination.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
