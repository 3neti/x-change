<!-- resources/js/Pages/Redeem/Signature.vue -->
<script setup lang="ts">
import { VueSignaturePad } from "@selemondev/vue3-signature-pad";
import { useForm } from "@inertiajs/vue3";
import { ref } from "vue";

const props = defineProps<{
    context: Record<string, any>
}>();

const form = useForm({
    voucher_code: props.context.voucherCode,
    mobile:       props.context.mobile,
    country:      '',
    signature:    '',
});

const state = ref({
    options: {
        penColor: 'rgb(0, 0, 0)',
        backgroundColor: 'rgb(255, 255, 255)',
    },
    disabled: false,
});

const signaturePad = ref<InstanceType<typeof VueSignaturePad>>();

function handleSave() {
    form.signature = signaturePad.value?.saveSignature() || '';
    if (!form.signature) {
        alert("Please provide a signature.");
        return;
    }
    submit();
}

function handleClear() {
    signaturePad.value?.clearCanvas();
}

function handleUndo() {
    signaturePad.value?.undo();
}

function handleDisabled() {
    state.value.disabled = ! state.value.disabled;
}

function submit() {
    form.post(route('redeem.signature', { voucher: props.context.voucherCode, plugin: 'signature' }), {
        preserveScroll: true,
    })
}
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div class="w-full max-w-[600px] bg-white p-4 rounded-lg shadow-md flex flex-col items-center space-y-4">
            <!-- "Sign Here" Label -->
            <p class="text-gray-700 font-semibold text-lg self-start">
                ✍️ Sign Here:
            </p>
            <!-- Signature Pad Container -->
            <div class="relative w-full aspect-square bg-gray-50 border border-gray-300 rounded-md overflow-hidden">
                <VueSignaturePad
                    ref="signaturePad"
                    class="w-full h-full"
                    :maxWidth="2"
                    :minWidth="2"
                    :disabled="state.disabled"
                    :options="{
                        penColor: state.options.penColor,
                        backgroundColor: state.options.backgroundColor
                    }"
                />
                <!-- Faded Placeholder Text (if no signature) -->
                <p v-if="!form.signature_data" class="absolute inset-0 flex items-center justify-center text-gray-400 text-lg pointer-events-none">
                    Tap and sign here
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap justify-center gap-2">
                <button
                    type="button"
                    @click="handleSave"
                    class="px-4 py-2 bg-green-500 text-white rounded-md w-full sm:w-auto"
                >
                    Save & Submit
                </button>
                <button
                    type="button"
                    @click="handleClear"
                    class="px-4 py-2 bg-red-500 text-white rounded-md w-full sm:w-auto"
                >
                    Clear
                </button>
                <button
                    type="button"
                    @click="handleUndo"
                    class="px-4 py-2 bg-blue-500 text-white rounded-md w-full sm:w-auto"
                >
                    Undo
                </button>
                <button
                    type="button"
                    @click="handleDisabled"
                    class="px-4 py-2 bg-gray-500 text-white rounded-md w-full sm:w-auto"
                >
                    Toggle Disabled
                </button>
            </div>

            <InputError :message="form.errors.signature_data" class="mt-2" />
        </div>
    </div>
</template>
