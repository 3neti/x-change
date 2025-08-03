<!-- resources/js/Pages/Redeem/Inputs.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { computed, onMounted, ref, nextTick, watch } from 'vue';
import { useBrowserLocation } from '@/composables/useBrowserLocation';
import GeoPermissionAlert from '@/components/domain/GeoPermissionAlert.vue';
import InputExtra from '@/components/domain/InputExtra.vue';

// ⬅️ Accept key-value input props
const props = defineProps<{
    context: {
        voucherCode: string;
        mobile: string;
    };
    inputs: Record<string, string | null>; // 👈 now an object with field defaults
}>();

// Hydrate form with default values
const form = useForm({
    name: props.inputs.name ?? '',
    address: props.inputs.address ?? '',
    birth_date: props.inputs.birth_date ?? '',
    email: props.inputs.email ?? '',
    reference_code: props.inputs.reference_code ?? '',
    gross_monthly_income: props.inputs.gross_monthly_income ?? '',
    country: props.inputs.country ?? '',
    location: '',
    otp: '',
});

// 👁️ Get only the visible fields from keys
const visibleFields = computed(() => Object.keys(props.inputs));

function submit() {
    form.post(
        route('redeem.inputs', {
            voucher: props.context.voucherCode,
            plugin: 'inputs',
        }),
        {
            preserveScroll: true,
        },
    );
}

const inputGroupRef = ref<HTMLFieldSetElement | null>(null);

onMounted(async () => {
    await nextTick();
    // Focus the first input inside the group box
    const firstInput = inputGroupRef.value?.querySelector('input, select, textarea') as HTMLElement | null;
    firstInput?.focus();
});

const { location, getLocation, loading, error } = useBrowserLocation(import.meta.env.VITE_OPENCAGE_KEY, 3 * 60 * 1000);
const geoAlertRef = ref<InstanceType<typeof GeoPermissionAlert> | null>(null);

async function fetchLocation() {
    const data = await getLocation(false);

    if (error.value === 'PERMISSION_DENIED') {
        geoAlertRef.value?.open();
        return;
    }

    if (data) {
        form.location = JSON.stringify(data);
    }
}

const parsedLocation = computed(() => {
    try {
        return JSON.parse(form.location);
    } catch {
        return null;
    }
});

watch(
    () => form.location,
    (value) => {
        if (value && form.errors && 'location' in form.errors) {
            form.errors.location = '';
        }
    },
);

const cooldown = ref(0);
const cooldownTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const cooldownInterval = ref<ReturnType<typeof setInterval> | null>(null);
const isSending = ref(false);
const resendMessage = ref('');
const resendCount = ref(0);
const MAX_RESENDS = 3;

function resendOtp() {
    if (cooldown.value > 0 || resendCount.value >= MAX_RESENDS) {
        console.debug('[Resend OTP] Not allowed. Cooldown or max resend reached.', {
            cooldown: cooldown.value,
            resendCount: resendCount.value,
        });
        return;
    }

    isSending.value = true;
    resendMessage.value = '';
    console.debug('[Resend OTP] Sending request for:', props.context.mobile);

    router.post(
        route('redeem.verify-mobile', { voucher: props.context.voucherCode }),
        { mobile: props.context.mobile },
        {
            preserveScroll: true,
            onSuccess: () => {
                resendCount.value++;
                console.debug('[Resend OTP] Success response. Count:', resendCount.value);
                resendMessage.value = 'OTP resent successfully.';
                startCooldown();
            },
            onError: (errors) => {
                console.error('[Resend OTP] Failed with errors:', errors);
                resendMessage.value = 'Failed to resend OTP. Try again.';
            },
            onFinish: () => {
                isSending.value = false;
                console.debug('[Resend OTP] Request finished');
            },
        }
    );
}

function startCooldown() {
    // Clear any existing timers
    if (cooldownInterval.value) clearInterval(cooldownInterval.value);
    if (cooldownTimer.value) clearTimeout(cooldownTimer.value);

    cooldown.value = 30;

    // Start countdown
    cooldownInterval.value = setInterval(() => {
        cooldown.value--;
        if (cooldown.value <= 0 && cooldownInterval.value) {
            clearInterval(cooldownInterval.value);
            cooldownInterval.value = null;
        }
    }, 1000);

    // Auto-hide message
    cooldownTimer.value = setTimeout(() => {
        resendMessage.value = '';
        console.debug('[Resend OTP] Message cleared after 5s');
    }, 5000);
}

const hasOtpError = ref(false);
// Watch for OTP validation error once
watch(() => form.errors.otp, (error) => {
    if (error && !hasOtpError.value) {
        hasOtpError.value = true;
    }
});
</script>

<template>
    <AuthBase>
        <Head title="Additional Information" />

        <form @submit.prevent="submit" class="relative space-y-6">
            <!-- Mobile (Read-only) -->
            <div class="flex flex-col gap-1">
                <Label for="mobile">Mobile Handle</Label>
                <input
                    id="mobile"
                    type="text"
                    :value="props.context.mobile"
                    readonly
                    tabindex="-1"
                    inert
                    class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 px-3 py-2 text-gray-700 shadow-sm"
                />
            </div>

            <!-- Inputs Group -->
            <fieldset ref="inputGroupRef" class="rounded-lg border border-gray-300 p-4 dark:border-gray-600">
                <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-300">Requested Inputs</legend>

                <div v-if="visibleFields.includes('name')" class="mt-2 flex flex-col gap-1">
                    <Label for="name">Full Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div v-if="visibleFields.includes('address')" class="mt-4 flex flex-col gap-1">
                    <Label for="address">Address</Label>
                    <Input id="address" v-model="form.address" />
                    <InputError :message="form.errors.address" />
                </div>

                <div v-if="visibleFields.includes('birth_date')" class="mt-4 flex flex-col gap-1">
                    <Label for="birthdate">Birth Date</Label>
                    <Input id="birthdate" v-model="form.birth_date" type="date" />
                    <InputError :message="form.errors.birth_date" />
                </div>

                <div v-if="visibleFields.includes('email')" class="mt-4 flex flex-col gap-1">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>

                <div v-if="visibleFields.includes('reference_code')" class="mt-4 flex flex-col gap-1">
                    <Label for="reference_code">Reference Code</Label>
                    <Input id="reference_code" v-model="form.reference_code" />
                    <InputError :message="form.errors.reference_code" />
                </div>

                <div v-if="visibleFields.includes('gross_monthly_income')" class="mt-4 flex flex-col gap-1">
                    <Label for="gross_monthly_income">Gross Monthly Income</Label>
                    <Input id="gross_monthly_income" v-model="form.gross_monthly_income" type="number" step="any" min="0" required />
                    <InputError :message="form.errors.gross_monthly_income" />
                </div>

                <div v-if="visibleFields.includes('country')" class="mt-4 flex flex-col gap-1">
                    <Label for="country">Country</Label>
                    <Input id="country" v-model="form.country" />
                    <InputError :message="form.errors.country" />
                </div>

                <div v-if="visibleFields.includes('location')" class="mt-4 flex flex-col gap-1">
                    <Label for="location">Location</Label>
                    <div class="flex items-center gap-2">
                        <Input id="location" :value="parsedLocation?.address.formatted ?? ''" class="flex-1" required readonly />
                        <Button type="button" @click.prevent="fetchLocation">Get</Button>
                    </div>
                    <GeoPermissionAlert ref="geoAlertRef" />
                    <InputError :message="form.errors.location" />
                </div>

                <div v-if="visibleFields.includes('otp')" class="mt-4 flex flex-col gap-1">
                    <Label for="otp">OTP</Label>
                    <Input id="otp" v-model="form.otp" />

                    <div class="flex items-center justify-between text-sm min-h-[1.25rem]">
                        <!-- Always show InputError on the left -->
                        <div class="text-red-600">
                            <InputError :message="form.errors.otp" />
                        </div>

                        <!-- Show InputExtra initially; hide permanently after first error -->
                        <div v-if="!hasOtpError" class="text-gray-500">
                            <InputExtra message="OTP has been sent." />
                        </div>

                        <!-- Show Resend OTP only after error -->
                        <div
                            v-else-if="resendCount < MAX_RESENDS"
                            class="text-blue-600 hover:underline cursor-pointer text-right"
                            @click="resendOtp"
                            :class="{ 'opacity-50 pointer-events-none': isSending || cooldown > 0 }"
                        >
                            <template v-if="cooldown > 0">
                                Resend in {{ cooldown }}s
                            </template>
                            <template v-else>
                                Resend OTP
                            </template>
                        </div>
                    </div>

                    <!-- Optional resend confirmation -->
                    <div v-if="resendMessage" class="mt-1 text-sm text-gray-500 text-right">
                        {{ resendMessage }}
                    </div>

                    <!-- Max resend reached -->
                    <div v-if="resendCount >= MAX_RESENDS" class="mt-1 text-sm text-red-500">
                        You have reached the maximum number of resends.
                    </div>
                </div>
            </fieldset>

            <!-- Footer Section -->
            <div class="flex items-center justify-between pt-4">
                <div class="text-right text-xs text-gray-500 italic dark:text-gray-400">
                    Redeeming cash code: <span class="font-semibold">{{ props.context.voucherCode }}</span>
                </div>

                <Button :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Next' }}
                </Button>
            </div>
        </form>
    </AuthBase>
</template>

<style scoped>
/* Clean layout, consistent spacing */
</style>
