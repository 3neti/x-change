<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import RedeemForm from '@/components/domain/RedeemVoucherForm.vue'

// grab flash messages
const props = usePage().props
const success = props.flash.success as string | undefined
const error   = props.flash.error   as string | undefined
</script>

<template>
    <div class="max-w-md mx-auto p-6">
        <h1 class="text-2xl font-bold mb-4">Redeem Your Voucher</h1>

        <div v-if="success" class="mb-4 p-3 bg-green-100 text-green-800 rounded">
            {{ success }}
        </div>
        <div v-if="error"   class="mb-4 p-3 bg-red-100   text-red-800   rounded">
            {{ error }}
        </div>

        <RedeemForm />
    </div>
</template>
