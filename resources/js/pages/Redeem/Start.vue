<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'

const form = useForm({
    voucher_code: '',
})

const voucherInput = ref<HTMLInputElement | null>(null)

function submit() {
    form.voucher_code = form.voucher_code.trim()

    form.get(route('redeem.wallet', {
        voucher: form.voucher_code,
    }), {
        preserveState: true,
    })
}

// Auto-select if there's a voucher_code error
watch(() => form.errors.voucher_code, (error) => {
    if (error && voucherInput.value) {
        voucherInput.value.select()
    }
})

onMounted(() => {
    // Optional: Select on load if there's already an error
    if (form.errors.voucher_code && voucherInput.value) {
        voucherInput.value.select()
    }
})
</script>

<template>
    <GuestLayout>
        <Head title="Redeem Voucher" />

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Voucher Code -->
            <div class="flex flex-col gap-1">
                <Label for="voucher_code">Cash Code</Label>
                <Input
                    id="voucher_code"
                    v-model="form.voucher_code"
                    placeholder="Enter cash code"
                    required
                    autofocus
                    ref="voucherInput"
                />
                <InputError :message="form.errors.voucher_code" class="mt-1" />
            </div>

            <!-- Footer row -->
            <div class="mt-6 flex justify-between items-center">
                <!-- Help text slot with reserved space -->
                <div class="min-h-[1.5rem] text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="Object.keys(form.errors).length">
                        Need help?
                        <a
                            href="https://help.disburse.cash"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-blue-600 hover:underline"
                        >
                            https://help.disburse.cash
                        </a>
                    </template>
                </div>

                <Button :disabled="form.processing">
                    {{ form.processing ? 'Checking…' : 'Start' }}
                </Button>
            </div>

            <!-- General error fallback -->
            <p v-if="form.errors.general" class="text-red-600">
                {{ form.errors.general }}
            </p>
        </form>
    </GuestLayout>
</template>
