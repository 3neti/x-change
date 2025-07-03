<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'
import { computed } from 'vue'

/**
 * Our page now expects three props:
 *  - country?: string         (e.g. "PH")
 *  - bank_code?: string       (the default selected bank)
 *  - banks: { code: string; name: string }[]
 */
interface Bank {
    code: string
    name: string
}
const props = defineProps<{
    tag: string,
    country?: string
    bank_code?: string
    banks: Bank[]
}>()

const defaultCountry   = computed(() => props.country ?? 'PH')
const defaultBankCode  = computed(() => props.bank_code ?? '')
const banks            = computed(() => props.banks)

/**
 * wire up your form
 */
const form = useForm({
    voucher_code:  '',
    mobile:        '',
    country:       defaultCountry.value,
    location:      '',
    bank_code:     defaultBankCode.value,
    account_number:'',
})

function submit() {
    form.voucher_code = form.voucher_code.trim();
    form.get(route('redeem.pipeline', {
        voucher: form.voucher_code,
    }), {
        preserveState: false,
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Redeem Voucher" />

        <form @submit.prevent="submit" class="space-y-6">

            <!-- Voucher Code -->
            <div class="flex flex-col gap-1">
                <Label for="voucher_code">Voucher Code</Label>
                <Input
                    id="voucher_code"
                    v-model="form.voucher_code"
                    placeholder="Enter voucher code"
                    required autofocus
                />
                <InputError :message="form.errors.voucher_code" class="mt-1" />
            </div>

            <!-- Mobile -->
            <div class="flex flex-col gap-1">
                <Label for="mobile">Mobile Number</Label>
                <Input
                    id="mobile"
                    v-model="form.mobile"
                    type="tel"
                    autocomplete="tel"
                    inputmode="tel"
                    required
                />
                <InputError :message="form.errors.mobile" class="mt-1" />
            </div>

            <!-- Country -->
            <div class="flex flex-col gap-1">
                <Label for="country">Country Code</Label>
                <Input
                    id="country"
                    v-model="form.country"
                    placeholder="e.g. PH"
                    required
                />
                <InputError :message="form.errors.country" class="mt-1" />
            </div>

            <!-- Location (optional) -->
            <div class="flex flex-col gap-1">
                <Label for="location">Location (optional)</Label>
                <Input
                    id="location"
                    v-model="form.location"
                    placeholder="e.g. Manila"
                />
                <InputError :message="form.errors.location" class="mt-1" />
            </div>

            <hr class="bevel-line" />

            <!-- Bank Code (optional) now a select -->
            <div class="flex flex-col gap-1">
                <Label for="bank_code">Bank</Label>
                <select
                    id="bank_code"
                    v-model="form.bank_code"
                    class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">None</option>
                    <option
                        v-for="bank in banks"
                        :key="bank.code"
                        :value="bank.code"
                    >
                        {{ bank.name }}
                    </option>
                </select>
                <InputError :message="form.errors.bank_code" class="mt-1" />
            </div>

            <!-- Account Number (optional) -->
            <div class="flex flex-col gap-1">
                <Label for="account_number">Account Number (optional)</Label>
                <Input
                    id="account_number"
                    v-model="form.account_number"
                    inputmode="numeric"
                    autocomplete="off"
                />
                <InputError :message="form.errors.account_number" class="mt-1" />
            </div>

            <!-- Submit -->
            <div class="pt-4">
                <Button :disabled="form.processing">
                    {{ form.processing ? 'Working…' : 'Redeem Voucher' }}
                </Button>
            </div>

            <!-- General error fallback -->
            <p v-if="form.errors.general" class="text-red-600">
                {{ form.errors.general }}
            </p>
        </form>
    </GuestLayout>
</template>

<style scoped>
.bevel-line {
    border: 0;
    height: 1px;
    margin: 1rem 0;
    background: linear-gradient(to right, #d1d5db, #f9fafb, #d1d5db);
}
/* add a bit more breathing room under each label/field group */
.flex > .flex-col > label + * {
    margin-top: 0.25rem;
}
</style>
