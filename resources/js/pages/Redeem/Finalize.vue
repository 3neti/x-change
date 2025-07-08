<!-- resources/js/Pages/Redeem/Finalize.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { useFormatCurrency } from '@/composables/useFormatCurrency'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'

const props = defineProps<{
    voucher: {
        code: string
        cash: {
            amount: number
            currency: string
        }
        meta: Record<string, any>
    }
    mobile: string
    bank_account: string | null
}>()

const formatCurrency = useFormatCurrency()
const confirm = ref(false)
const form = useForm({})

function submit() {
    if (!confirm.value) return
    router.get(route('redeem.success', { voucher: props.voucher.code }))
}
</script>

<template>
    <GuestLayout>
        <Head title="Review & Finalize" />

        <div class="space-y-6 max-w-xl mx-auto">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                Confirmation
            </h2>

            <!-- Voucher Info -->
            <div class="space-y-3">
                <div class="flex items-center gap-4">
                    <Label for="voucher_code" class="w-32 text-right">Cash Code</Label>
                    <input
                        id="voucher_code"
                        type="text"
                        :value="props.voucher.code"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Label for="amount" class="w-32 text-right">Amount</Label>
                    <input
                        id="amount"
                        type="text"
                        :value="formatCurrency(props.voucher.cash.amount)"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div class="flex items-center gap-4">
                    <Label for="mobile" class="w-32 text-right">Mobile</Label>
                    <input
                        id="mobile"
                        type="text"
                        :value="props.mobile || '—'"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div v-if="props.bank_account" class="flex items-center gap-4">
                    <Label for="bank_account" class="w-32 text-right">Bank Account</Label>
                    <input
                        id="bank_account"
                        type="text"
                        :value="props.bank_account"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>
            </div>

            <!-- Personal Details -->
            <div v-if="props.voucher.meta?.inputs" class="space-y-3 pt-4">
                <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200">Personal Details</h3>

                <div v-if="props.voucher.meta.inputs.name" class="flex items-center gap-4">
                    <Label for="name" class="w-32 text-right">Name</Label>
                    <input
                        id="name"
                        type="text"
                        :value="props.voucher.meta.inputs.name"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div v-if="props.voucher.meta.inputs.email" class="flex items-center gap-4">
                    <Label for="email" class="w-32 text-right">Email</Label>
                    <input
                        id="email"
                        type="text"
                        :value="props.voucher.meta.inputs.email"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div v-if="props.voucher.meta.inputs.address" class="flex items-center gap-4">
                    <Label for="address" class="w-32 text-right">Address</Label>
                    <input
                        id="address"
                        type="text"
                        :value="props.voucher.meta.inputs.address"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div v-if="props.voucher.meta.inputs.birth_date" class="flex items-center gap-4">
                    <Label for="birth_date" class="w-32 text-right">Birthdate</Label>
                    <input
                        id="birth_date"
                        type="text"
                        :value="props.voucher.meta.inputs.birth_date"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>

                <div v-if="props.voucher.meta.inputs.gross_monthly_income" class="flex items-center gap-4">
                    <Label for="income" class="w-32 text-right">Income</Label>
                    <input
                        id="income"
                        type="text"
                        :value="formatCurrency(props.voucher.meta.inputs.gross_monthly_income)"
                        readonly
                        tabindex="-1"
                        inert
                        class="flex-1 rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                    />
                </div>
            </div>

            <!-- Signature -->
            <div v-if="props.voucher.meta?.signature" class="flex items-center gap-4 pt-4">
                <Label class="w-32 text-right">Signature</Label>
                <img :src="props.voucher.meta.signature" alt="Signature" class="border w-48 h-auto" />
            </div>

            <!-- Confirmation and Next Button -->
            <div class="flex justify-between items-center pt-6 flex-wrap gap-y-2">
                <div class="flex items-center gap-2">
                    <input
                        id="confirm"
                        type="checkbox"
                        v-model="confirm"
                        class="size-4 rounded border-gray-300 text-primary focus:ring focus:ring-primary/20"
                    />
                    <label for="confirm" class="text-sm text-gray-700 dark:text-gray-300">
                        Cross-checked
                    </label>
                </div>

                <Button :disabled="!confirm" @click="submit">
                    Redeem
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>
