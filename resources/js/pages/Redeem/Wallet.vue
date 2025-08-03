<!-- resources/js/Pages/Redeem/Form.vue -->
<script setup lang="ts">
import GuestLayout from '@/layouts/legacy/GuestLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { onMounted, ref, watch, nextTick } from 'vue'
import InputError from '@/components/InputError.vue'
import AuthBase from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'

interface Bank { code: string; name: string }

const props = defineProps<{
    voucher_code: string
    country?: string
    bank_code?: string
    banks: Bank[],
    hasSecret: boolean
}>()

const GCASH_CODE = 'GXCHPHM2XXX'

// Use refs instead of computed to ensure value access at setup
const defaultCountry = ref(props.country ?? 'PH')
function fallback<T>(value: T | undefined | null, fallback: T): T {
    return value !== undefined && value !== null && value !== '' ? value : fallback
}
const defaultBankCode = ref(fallback(props.bank_code, GCASH_CODE))
const accountNumberInput = ref<HTMLInputElement | null>(null)

const form = useForm({
    mobile: '',
    country: defaultCountry.value,
    secret: '',
    bank_code: defaultBankCode.value,
    account_number: '',
})

function debounce<T extends (...args: any[]) => void>(fn: T, delay: number): T {
    let timer: ReturnType<typeof setTimeout>
    return function (this: any, ...args: any[]) {
        clearTimeout(timer)
        timer = setTimeout(() => fn.apply(this, args), delay)
    } as T
}

// Sync account_number to mobile only if it wasn't manually changed
let manualAccountOverride = false

const updateAccountNumber = debounce((mobile: string) => {
    if (!manualAccountOverride && form.bank_code === GCASH_CODE) {
        form.account_number = mobile
    }
}, 1500) // 300ms delay

watch(() => form.mobile, (mobile) => {
    updateAccountNumber(mobile)
})

// watch(() => form.mobile, (mobile) => {
//     if (!manualAccountOverride && form.bank_code === GCASH_CODE) {
//         form.account_number = mobile
//     }
// })

watch(() => form.account_number, () => {
    manualAccountOverride = true
})

watch(() => form.bank_code, (newVal, oldVal) => {
    if (newVal !== oldVal) {
        form.account_number = ''
        manualAccountOverride = false
    }
})

watch(() => form.bank_code, async (newCode, oldCode) => {
    if (newCode !== oldCode) {
        form.account_number = ''

        // Wait for DOM update, then focus
        await nextTick()
        accountNumberInput.value?.focus()
    }
})

const mobileInput = ref()
const secretInput = ref()

onMounted(() => {
    mobileInput.value?.focus()
})

function submit() {
    form
        .transform(data => ({
            ...data,
            // Only include bank_code + account_number if needed
            ...(form.bank_code !== GCASH_CODE ||
                (form.bank_code === GCASH_CODE && form.account_number !== form.mobile)
                    ? {
                        bank_code: form.bank_code || null,
                        account_number: form.account_number || null,
                    }
                    : {}
            ),
            // Only include secret if props.hasSecret is true
            ...(props.hasSecret && {
                secret: form.secret || null,
            }),
        }))
        .post(route('redeem.wallet', { voucher: props.voucher_code }), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => {
                form.reset('secret')
                secretInput.value?.focus()
                console.warn('Validation errors:', errors)
                console.warn('Reactive form.errors:', form.errors)
            },
        })
}
</script>

<template>
    <AuthBase>
        <Head title="Redeem Voucher" />

        <form @submit.prevent="submit" class="space-y-6 relative">
            <!-- Mobile Number -->
            <div class="flex flex-col gap-1">
                <Label for="mobile">Mobile Number</Label>
                <Input id="mobile" ref="mobileInput" v-model="form.mobile" type="tel" placeholder="e.g. 09171234567" required autofocus />
                <InputError :message="form.errors.mobile" />
            </div>

            <!-- Hidden Country -->
            <input type="hidden" v-model="form.country" />

            <!-- Secret -->
            <div v-if="props.hasSecret" class="flex flex-col gap-1">
                <Label for="secret">Secret</Label>
                <Input id="secret" ref="secretInput" v-model="form.secret" type="password" required />
                <InputError :message="form.errors.secret" />
            </div>

            <hr class="my-4 border-gray-300 dark:border-gray-700" />

            <!-- Bank Account Section -->
            <fieldset class="border border-gray-300 dark:border-gray-600 rounded-lg p-4">
                <legend class="text-sm font-medium text-gray-700 dark:text-gray-300 px-2">Bank Account</legend>

                <!-- Bank Code -->
                <div class="mt-2 flex flex-col gap-1">
                    <Label for="bank_code">Bank</Label>
                    <select id="bank_code" v-model="form.bank_code"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white px-3 py-2 shadow-sm dark:bg-gray-900 dark:text-white">
                        <option value="">None</option>
                        <option v-for="b in props.banks" :key="b.code" :value="b.code">
                            {{ b.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.bank_code" />
                </div>

                <!-- Account Number -->
                <div class="mt-4 flex flex-col gap-1">
                    <Label for="account_number">Account #</Label>
                    <Input id="account_number" ref="accountNumberInput" v-model="form.account_number" />
                    <InputError :message="form.errors.account_number" />
                </div>
            </fieldset>

            <!-- Error Message -->
            <p v-if="form.errors.general" class="text-red-600">
                {{ form.errors.general }}
            </p>

            <!-- Footer Section: Next button and voucher display -->
            <div class="flex justify-between items-center pt-4">
                <div class="text-xs text-right text-gray-500 dark:text-gray-400 italic">
                    Redeeming cash code: <span class="font-semibold">{{ props.voucher_code }}</span>
                </div>

                <Button :disabled="form.processing">
                    {{ form.processing ? 'Checking…' : 'Next' }}
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
