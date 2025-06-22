<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { defineProps } from 'vue'

interface CashData {
    amount: number
    currency: string
}

interface Instructions {
    cash: CashData
    // …other instruction fields if you have them
}

interface RedeemerRelation {
    redeemer: {
        mobile: string
        // …other contact fields if needed
    }
}

interface Voucher {
    code: string
    instructions: Instructions
    expires_at: string | null
    redeemed_at: string | null
    processed_on: string | null
    qr_code: string           // ← newly added
    redeemer?: RedeemerRelation
}

const props = defineProps<{
    voucher: Voucher
}>()
</script>

<template>
    <Head title={{ props.voucher.code }} />

    <div class="space-y-6 p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
            Voucher: <span class="font-mono">{{ props.voucher.code }}</span>
        </h1>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left: Details -->
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Amount
                    </dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ props.voucher.instructions.cash.currency }}
                        {{ props.voucher.instructions.cash.amount.toFixed(2) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Expires At
                    </dt>
                    <dd class="mt-1 text-gray-800 dark:text-gray-200">
            <span v-if="props.voucher.expires_at">
              {{ new Date(props.voucher.expires_at).toLocaleString() }}
            </span>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Redeemed At
                    </dt>
                    <dd class="mt-1 text-gray-800 dark:text-gray-200">
            <span v-if="props.voucher.redeemed_at">
              {{ new Date(props.voucher.redeemed_at).toLocaleString() }}
            </span>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Processed On
                    </dt>
                    <dd class="mt-1 text-gray-800 dark:text-gray-200">
            <span v-if="props.voucher.processed_on">
              {{ new Date(props.voucher.processed_on).toLocaleString() }}
            </span>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Redeemer Mobile
                    </dt>
                    <dd class="mt-1 text-gray-800 dark:text-gray-200">
                        {{ props.voucher.redeemer?.redeemer.mobile ?? '—' }}
                    </dd>
                </div>
            </dl>

            <!-- Right: QR Code -->
            <div class="flex items-center justify-center">
                <div class="bg-white p-4 rounded shadow-md">
                    <img
                        :src="props.voucher.qr_code"
                        alt="Voucher QR Code"
                        class="w-48 h-48 object-contain"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
