<!-- resources/js/Pages/Redeem/Success.vue -->
<script setup lang="ts">
import { computed, onMounted } from 'vue'
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
        contact: {
            bank_account?: string
        }
        instructions?: {
            rider?: {
                message?: string
            }
        }
    },
    inputs: {
        name?: string
        email?: string
        address?: string
        birth_date?: string
        gross_monthly_income?: number
        signature?: string
    },
    header?: string
    redirectTimeout?: number
}>()

const formatCurrency = useFormatCurrency()
const { formatDate } = useFormatDate()

const title = computed(() =>
    props.voucher.instructions?.rider?.message?.trim()
    || props.header?.trim()
    || 'Cash Code Redeemed!'
)

onMounted(() => {
    setTimeout(() => {
        router.visit(route('redeem.redirect', { voucher: props.voucher.code }))
    }, props.redirectTimeout ?? 3000)
})
</script>

<template>
    <GuestLayout>
        <Head title="Voucher Redeemed" />

        <div class="space-y-6 text-center max-w-md mx-auto">
            <h2 class="text-2xl font-semibold text-green-600">{{ title }}</h2>

            <div class="space-y-4 text-left">
                <div>
                    <p class="text-sm text-gray-500">Cash Code</p>
                    <div class="font-mono text-base">{{ voucher.code }}</div>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Amount</p>
                    <div class="text-lg font-medium">
                        {{ formatCurrency(voucher.cash.amount) }}
                    </div>
                </div>

                <div v-if="voucher.contact.bank_account">
                    <p class="text-sm text-gray-500">Bank Account</p>
                    <div class="text-base">{{ voucher.contact.bank_account }}</div>
                </div>

                <div v-if="inputs.signature">
                    <p class="text-sm text-gray-500">Signature</p>
                    <img
                        :src="inputs.signature"
                        alt="Signature"
                        class="mx-auto h-24 object-contain border p-2 rounded"
                    />
                </div>
            </div>

            <div class="pt-4 text-xs text-gray-400">
                Redeemed at {{ formatDate(voucher.redeemed_at) }}
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
