<!-- resources/js/Pages/Redeem/Signature.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { VueSignaturePad } from "@selemondev/vue3-signature-pad"
import { useForm } from "@inertiajs/vue3"
import { ref } from "vue"
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'

const props = defineProps<{
    context: Record<string, any>
}>()

const form = useForm({
    voucher_code: props.context.voucherCode,
    mobile:       props.context.mobile,
    country:      '',
    signature:    '',
})

const state = ref({
    options: {
        penColor: 'rgb(0, 0, 0)',
        backgroundColor: 'rgb(255, 255, 255)',
    },
    disabled: false,
})

const signaturePad = ref<InstanceType<typeof VueSignaturePad>>()

function handleSave() {
    form.signature = signaturePad.value?.saveSignature() || ''
    if (!form.signature) {
        alert("Please provide a signature.")
        return
    }
    submit()
}

function handleClear() {
    signaturePad.value?.clearCanvas()
}

function handleUndo() {
    signaturePad.value?.undo()
}

function handleDisabled() {
    state.value.disabled = !state.value.disabled
}

function submit() {
    form.post(route('redeem.signature', { voucher: props.context.voucherCode, plugin: 'signature' }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <GuestLayout>
        <form @submit.prevent="handleSave" class="space-y-6 max-w-lg mx-auto">
            <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                Signature
            </h2>

            <!-- Signature Pad -->
            <div class="relative aspect-square border border-gray-300 dark:border-gray-600 rounded-md overflow-hidden bg-white">
                <VueSignaturePad
                    ref="signaturePad"
                    class="w-full h-full"
                    :maxWidth="2"
                    :minWidth="2"
                    :disabled="state.disabled"
                    :options="state.options"
                />
                <p
                    v-if="!form.signature"
                    class="absolute inset-0 flex items-center justify-center text-gray-400 text-lg pointer-events-none"
                >
                    Tap and sign here
                </p>
            </div>

            <!-- Error -->
            <InputError :message="form.errors.signature" />

            <div class="mt-2 mb-1 text-xs text-left text-gray-500 dark:text-gray-400 italic">
                Redeeming cash code: <span class="font-semibold">{{ props.context.voucherCode }}</span>
            </div>

            <!-- Buttons -->
            <div class="flex justify-between items-center pt-2 flex-wrap gap-y-2">
                <!-- Left buttons -->
                <div class="flex gap-2">
                    <Button type="button" variant="outline" @click="handleUndo">Undo</Button>
                    <Button type="button" variant="secondary" @click="handleClear">Clear</Button>
                    <Button type="button" variant="ghost" @click="handleDisabled">
                        {{ state.disabled ? 'Enable' : 'Disable' }}
                    </Button>
                </div>

                <!-- Right button -->
                <div>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Submitting…' : 'Next' }}
                    </Button>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
