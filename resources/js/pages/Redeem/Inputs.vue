<!-- resources/js/Pages/Redeem/Inputs.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'

const props = defineProps<{
    context: {
        voucherCode: string
        mobile: string
    }
    inputs: Record<string, any>
}>()

const form = useForm({
    name: '',
    address: '',
    birthdate: '',
    email: '',
    gross_monthly_income: '',
    country: '',
})

Object.assign(form, props.inputs)

function submit() {
    form.post(route('redeem.inputs', { voucher: props.context.voucherCode, plugin: 'inputs' }), {
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
            <div class="flex flex-col gap-1">
                <Label for="name">Full Name</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <!-- Address -->
            <div class="flex flex-col gap-1">
                <Label for="address">Address</Label>
                <Input id="address" v-model="form.address" required />
                <InputError :message="form.errors.address" />
            </div>

            <!-- Birthdate -->
            <div class="flex flex-col gap-1">
                <Label for="birthdate">Birthdate</Label>
                <Input id="birthdate" v-model="form.birthdate" type="date" required />
                <InputError :message="form.errors.birthdate" />
            </div>

            <!-- Email -->
            <div class="flex flex-col gap-1">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <InputError :message="form.errors.email" />
            </div>

            <!-- Gross Monthly Income -->
            <div class="flex flex-col gap-1">
                <Label for="gross_monthly_income">Gross Monthly Income</Label>
                <Input id="gross_monthly_income" v-model="form.gross_monthly_income" type="number" required />
                <InputError :message="form.errors.gross_monthly_income" />
            </div>

            <!-- Submit -->
            <div class="pt-4">
                <Button :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Next' }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* Field spacing and clean layout consistent with other pages */
</style>
