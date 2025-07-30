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
    inputs: {
        name?: string
        email?: string
        address?: string
        birth_date?: string
        reference_code?: string
        gross_monthly_income?: number
        signature?: string
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
function isJsonString(str) {
    try {
        const parsed = JSON.parse(str);
        return typeof parsed === 'object' && parsed !== null;
    } catch (e) {
        return false;
    }
}

function beautifyKey(str) {
    return str
        .replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}
</script>

<template>
    <GuestLayout>
        <Head title="Review & Finalize" />
        <Table>
            <TableCaption>Verify the following details.</TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead class="w-[100px] text-center">
                        Key
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
                <!-- Dynamic Fields from Inputs -->
                <template v-for="(value, key) in props.inputs" :key="key">
                    <!-- Try parsing JSON values (e.g., location) -->
                    <template v-if="isJsonString(value)">
                        <TableRow>
                            <TableCell class="font-medium">{{ beautifyKey(key) }}</TableCell>
                            <TableCell class="text-right whitespace-pre-line">
                    <span v-for="(val, subkey) in JSON.parse(value)" :key="subkey">
                        {{ beautifyKey(subkey) }}:
                        <span v-if="typeof val === 'object'">{{ JSON.stringify(val) }}</span>
                        <span v-else>{{ val }}</span><br />
                    </span>
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell class="font-medium">{{ beautifyKey(key) }}</TableCell>
                            <TableCell class="text-right">{{ value }}</TableCell>
                        </TableRow>
                    </template>
                </template>
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
                    Confirmed and correct.
                </label>
            </div>

            <Button :disabled="!confirm" @click="submit">
                Redeem
            </Button>
        </div>
    </GuestLayout>
</template>
