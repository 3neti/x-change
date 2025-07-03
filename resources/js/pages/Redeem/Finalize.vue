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
    mobile: string,
    bank_account: string|null
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

        <div class="space-y-6">
            <h2 class="text-xl font-semibold">Review Your Information</h2>

            <div class="space-y-4">
                <div>
                    <Label>Voucher Code</Label>
                    <div class="font-mono text-sm">{{ props.voucher.code }}</div>
                </div>

                <div>
                    <Label>Amount</Label>
                    <div>{{ formatCurrency(props.voucher.cash.amount) }}</div>
                </div>

                <div>
                    <Label>Mobile Number</Label>
                    <div>{{ props.mobile || '—' }}</div>
                </div>

                <div v-if="props.bank_account">
                    <Label>Bank Account</Label>
                    <div>{{ props.bank_account }}</div>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-4">
                <input
                    id="confirm"
                    type="checkbox"
                    v-model="confirm"
                    class="size-4 rounded border-gray-300 text-primary focus:ring focus:ring-primary/20"
                />
                <label for="confirm" class="text-sm text-gray-700">
                    I confirm that the information above is correct.
                </label>
            </div>

            <div class="pt-4">
                <Button :disabled="!confirm" @click="submit">
                    Confirm Redemption
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
