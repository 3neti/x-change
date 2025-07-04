<!-- resources/js/Pages/Redeem/Success.vue -->
<script setup lang="ts">
import { onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { useFormatCurrency } from '@/composables/useFormatCurrency'
import { useFormatDate } from '@/composables/useFormatDate'

const props = defineProps<{
    voucher: {
        code: string
        cash: {
            amount: number
            currency: string
        }
        redeemed_at: string
    },
    signature: string,
    redirectTimeout?: number
}>()

const formatCurrency = useFormatCurrency()
const { formatDate } = useFormatDate()

onMounted(() => {
    setTimeout(() => {
        router.visit(route('redeem.redirect', { voucher: props.voucher.code }))
    }, props.redirectTimeout ?? 3000)
})
</script>

<template>
    <GuestLayout>
        <Head title="Voucher Redeemed" />

        <div class="space-y-6 text-center">
            <h2 class="text-2xl font-semibold text-green-600">Voucher Successfully Redeemed!</h2>

            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500">Voucher Code</p>
                    <div class="font-mono text-lg">{{ props.voucher.code }}</div>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Amount</p>
                    <div class="text-lg font-medium">
                        {{ formatCurrency(props.voucher.cash.amount) }}
                    </div>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Redeemed At</p>
                    <div class="text-lg">{{ formatDate(props.voucher.redeemed_at) }}</div>
                </div>

                <div v-if="props.signature">
                    <p class="text-sm text-gray-500">Signature</p>
                    <img :src="props.signature" alt="Signature" class="mx-auto h-24 object-contain border p-2" />
                </div>
            </div>

            <p class="text-sm text-gray-400">Redirecting shortly…</p>
        </div>
    </GuestLayout>
</template>

<style scoped>
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
