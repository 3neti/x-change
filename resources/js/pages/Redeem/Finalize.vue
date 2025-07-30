<!-- resources/js/Pages/Redeem/Finalize.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { useFormatCurrency } from '@/composables/useFormatCurrency'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'

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

        <Table>
            <TableCaption>Please confirm the info above.</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-[100px] text-center">
                        Item
                    </TableHead>
                    <TableHead class="text-center">Value</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow>
                    <TableCell class="font-medium">Code</TableCell>
                    <TableCell class="text-right">{{ props.voucher.code }}</TableCell>
                </TableRow>
                <TableRow>
                    <TableCell class="font-medium">Amount</TableCell>
                    <TableCell class="text-right">{{ formatCurrency(props.voucher.cash.amount, {isMinor: true}) }}</TableCell>
                </TableRow>
                <TableRow>
                    <TableCell class="font-medium">Mobile</TableCell>
                    <TableCell class="text-right">{{ props.mobile }}</TableCell>
                </TableRow>
                <TableRow>
                    <TableCell class="font-medium">Bank Account</TableCell>
                    <TableCell class="text-right">{{ props.bank_account }}</TableCell>
                </TableRow>
            </TableBody>
        </Table>

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
                    Confirmed
                </label>
            </div>

            <Button :disabled="!confirm" @click="submit">
                Redeem
            </Button>
        </div>
    </GuestLayout>
</template>
