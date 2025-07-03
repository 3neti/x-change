<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps<{
    message: string
}>()
</script>

<template>
    <Head title="Voucher Cannot Be Redeemed" />

    <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4">
        <div class="max-w-lg w-full p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                Oops — Cannot Redeem Voucher
            </h1>
            <p class="text-gray-700 dark:text-gray-300 mb-6">
                {{ message }}
            </p>
            <div class="flex gap-4">
                <Link
                    href="/redeem"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Try Another Code
                </Link>
                <Link
                    href="/public"
                    class="px-4 py-2 border border-blue-600 text-blue-600 rounded hover:bg-blue-50"
                >
                    Go Home
                </Link>
            </div>
        </div>
    </div>
</template>
