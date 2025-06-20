<script setup lang="ts">
import { ref } from 'vue'
import axios, { AxiosError } from 'axios'

interface FormFields {
    voucher_code: string
    mobile:        string
    country:      string
    bank_code?:   string
    account_number?: string
}

const form = ref<FormFields>({
    voucher_code: '',
    mobile:        '',
    country:      'PH',
    bank_code:     '',
    account_number:''
})

const errors = ref<Record<string,string[]>>({})
const loading = ref(false)

async function submit() {
    loading.value = true
    errors.value  = {}

    try {
        await axios.post(route('redeem.store'), form.value)
        // Inertia will redirect with flash; no further handling needed here
    } catch (e: unknown) {
        if (e instanceof AxiosError && e.response?.status === 422) {
            errors.value = e.response.data.errors
        } else {
            alert('An unexpected error occurred.')
            console.error(e)
        }
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div>
            <label class="block font-medium">Voucher Code</label>
            <input v-model="form.voucher_code" type="text" class="w-full border rounded p-2" />
            <p v-if="errors.voucher_code" class="text-red-600 text-sm">{{ errors.voucher_code[0] }}</p>
        </div>

        <div>
            <label class="block font-medium">Mobile Number</label>
            <input v-model="form.mobile" type="text" class="w-full border rounded p-2" />
            <p v-if="errors.mobile" class="text-red-600 text-sm">{{ errors.mobile[0] }}</p>
        </div>

        <div>
            <label class="block font-medium">Country Code</label>
            <input v-model="form.country" type="text" maxlength="2" class="w-24 border rounded p-2" />
            <p v-if="errors.country" class="text-red-600 text-sm">{{ errors.country[0] }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block font-medium">Bank Code <small>(optional)</small></label>
                <input v-model="form.bank_code" type="text" class="w-full border rounded p-2" />
                <p v-if="errors.bank_code" class="text-red-600 text-sm">{{ errors.bank_code[0] }}</p>
            </div>
            <div>
                <label class="block font-medium">Account # <small>(optional)</small></label>
                <input v-model="form.account_number" type="text" class="w-full border rounded p-2" />
                <p v-if="errors.account_number" class="text-red-600 text-sm">{{ errors.account_number[0] }}</p>
            </div>
        </div>

        <button
            type="submit"
            :disabled="loading"
            class="w-full py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60"
        >
            {{ loading ? 'Redeeming…' : 'Redeem Voucher' }}
        </button>
    </form>
</template>
