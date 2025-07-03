<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'

const form = useForm({
    voucher_code: '',
})

function submit() {
    form.voucher_code = form.voucher_code.trim()

    form.get(route('redeem.mobile', {
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
                    required
                    autofocus
                />
                <InputError :message="form.errors.voucher_code" class="mt-1" />
            </div>

            <!-- Submit -->
            <div class="pt-4">
                <Button :disabled="form.processing">
                    {{ form.processing ? 'Checking…' : 'Start Redemption' }}
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
.flex > .flex-col > label + * {
    margin-top: 0.25rem;
}
</style>
