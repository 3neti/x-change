<!-- resources/js/components/CutCheck.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import axios, { AxiosResponse } from 'axios'

interface Voucher {
    code: string
    amount: string
    expires_at: string | null
}

const instruction = ref('')
const vouchers   = ref<Voucher[]>([])
const loading    = ref(false)
const error      = ref<string | null>(null)

async function generate() {
    error.value   = null
    vouchers.value = []
    loading.value = true

    try {
        const res: AxiosResponse<{ vouchers: Voucher[] }> =
            await axios.post(route('api.cut-check'), { text: instruction.value })
        vouchers.value = res.data.vouchers
    } catch (e: any) {
        console.error(e)
        error.value = e.response?.data?.message || e.message
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="p-4 space-y-4">
    <textarea
        v-model="instruction"
        rows="3"
        class="w-full rounded border px-2 py-1"
        placeholder="e.g. Cut me 2×₱500 vouchers expiring in 30 days…"
    />

        <button
            @click="generate"
            :disabled="loading || !instruction.trim()"
            class="px-3 py-1 bg-blue-600 text-white rounded disabled:opacity-50"
        >
            {{ loading ? '…generating' : 'Generate Vouchers' }}
        </button>

        <div v-if="error" class="text-red-500 text-sm">{{ error }}</div>

        <table
            v-if="vouchers.length"
            class="w-full table-fixed border-collapse text-sm"
        >
            <thead>
            <tr class="bg-gray-100">
                <th class="p-2 text-left">Code</th>
                <th class="p-2 text-left">Amount</th>
                <th class="p-2 text-left">Expires At</th>
            </tr>
            </thead>
            <tbody>
            <tr
                v-for="v in vouchers"
                :key="v.code"
                class="border-t hover:bg-gray-50"
            >
                <td class="p-2 font-mono text-xs break-all">{{ v.code }}</td>
                <td class="p-2">{{ v.amount }}</td>
                <td class="p-2">{{ v.expires_at ?? '—' }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
