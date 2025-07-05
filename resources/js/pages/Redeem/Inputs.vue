<!-- resources/js/Pages/Redeem/Inputs.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'
import { computed } from 'vue'

// ⬅️ Accept key-value input props
const props = defineProps<{
    context: {
        voucherCode: string
        mobile: string
    }
    inputs: Record<string, string | null> // 👈 now an object with field defaults
}>()

// Hydrate form with default values
const form = useForm({
    name: props.inputs.name ?? '',
    address: props.inputs.address ?? '',
    birth_date: props.inputs.birth_date ?? '',
    email: props.inputs.email ?? '',
    gross_monthly_income: props.inputs.gross_monthly_income ?? '',
    country: props.inputs.country ?? '',
})

// 👁️ Get only the visible fields from keys
const visibleFields = computed(() => Object.keys(props.inputs))

function submit() {
    form.post(route('redeem.inputs', {
        voucher: props.context.voucherCode,
        plugin: 'inputs',
    }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Additional Information" />

        <form @submit.prevent="submit" class="space-y-6">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                You are redeeming voucher: <span class="font-semibold">{{ props.context.voucherCode }}</span>
            </div>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Mobile number: <span class="font-semibold">{{ props.context.mobile }}</span>
            </div>

            <!-- Name -->
            <div v-if="visibleFields.includes('name')" class="flex flex-col gap-1">
                <Label for="name">Full Name</Label>
                <Input id="name" v-model="form.name"/>
                <InputError :message="form.errors.name" />
            </div>

            <!-- Address -->
            <div v-if="visibleFields.includes('address')" class="flex flex-col gap-1">
                <Label for="address">Address</Label>
                <Input id="address" v-model="form.address"/>
                <InputError :message="form.errors.address" />
            </div>

            <!-- Birthdate -->
            <div v-if="visibleFields.includes('birth_date')" class="flex flex-col gap-1">
                <Label for="birthdate">Birth Date</Label>
                <Input id="birthdate" v-model="form.birth_date" type="date"/>
                <InputError :message="form.errors.birth_date" />
            </div>

            <!-- Email -->
            <div v-if="visibleFields.includes('email')" class="flex flex-col gap-1">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email"/>
                <InputError :message="form.errors.email" />
            </div>

            <!-- Gross Monthly Income -->
            <div v-if="visibleFields.includes('gross_monthly_income')" class="flex flex-col gap-1">
                <Label for="gross_monthly_income">Gross Monthly Income</Label>
                <Input
                    id="gross_monthly_income"
                    v-model="form.gross_monthly_income"
                    type="number"
                    step="any"
                    min="0"
                    required
                />
                <InputError :message="form.errors.gross_monthly_income" />
            </div>

            <!-- Country (optional for future) -->
            <div v-if="visibleFields.includes('country')" class="flex flex-col gap-1">
                <Label for="country">Country</Label>
                <Input id="country" v-model="form.country"/>
                <InputError :message="form.errors.country" />
            </div>

            <div class="pt-4">
                <Button :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Next' }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* Clean layout, consistent spacing */
</style>
