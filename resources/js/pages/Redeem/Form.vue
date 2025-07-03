<!-- resources/js/Pages/Redeem/Form.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'
import { computed } from 'vue'

interface Bank { code: string; name: string }
const props = defineProps<{ voucher_code: string; country?: string; bank_code?: string; banks: Bank[] }>()
const defaultCountry  = computed(() => props.country ?? 'PH')
const defaultBankCode = computed(() => props.bank_code ?? '')

const form = useForm({
    mobile:         '',
    country:        defaultCountry.value,
    bank_code:      defaultBankCode.value,
    account_number: '',
})

function submit() {
    form.post(route('redeem.mobile', { voucher: props.voucher_code }), {
            preserveScroll: true,
        }
    )
}
</script>

<template>
    <GuestLayout>
        <Head title="Redeem Voucher" />

        <form @submit.prevent="submit" class="space-y-6">

            <!-- Display the voucher code (readonly text) -->
            <div class="text-sm text-gray-700 dark:text-gray-300">
                You are redeeming voucher: <span class="font-semibold">{{ props.voucher_code }}</span>
            </div>

            <!-- Mobile Number -->
            <div class="flex flex-col gap-1">
                <Label for="mobile">Mobile Number</Label>
                <Input id="mobile" v-model="form.mobile" type="tel"
                       placeholder="e.g. 09171234567" required />
                <InputError :message="form.errors.mobile" />
            </div>

            <!-- Country Code -->
            <div class="flex flex-col gap-1">
                <Label for="country">Country Code</Label>
                <Input id="country" v-model="form.country" placeholder="PH" required />
                <InputError :message="form.errors.country" />
            </div>

            <!-- Bank Code -->
            <div class="flex flex-col gap-1">
                <Label for="bank_code">Bank (optional)</Label>
                <select id="bank_code" v-model="form.bank_code"
                        class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 shadow-sm">
                    <option value="">None</option>
                    <option v-for="b in props.banks" :key="b.code" :value="b.code">
                        {{ b.name }}
                    </option>
                </select>
                <InputError :message="form.errors.bank_code" />
            </div>

            <!-- Account Number -->
            <div class="flex flex-col gap-1">
                <Label for="account_number">Account # (optional)</Label>
                <Input id="account_number" v-model="form.account_number" />
                <InputError :message="form.errors.account_number" />
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <Button :disabled="form.processing">
                    {{ form.processing ? 'Checking…' : 'Next' }}
                </Button>
            </div>

            <!-- General Error -->
            <p v-if="form.errors.general" class="text-red-600">{{ form.errors.general }}</p>
        </form>
    </GuestLayout>
</template>
