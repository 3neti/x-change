<script setup>
import ApplicationLogo from '@/components/legacy/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-muted pt-6 sm:justify-center sm:pt-0">
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-primary text-primary" />
            </Link>
        </div>

        <div
            class="mt-6 w-full rounded-xl border border-border bg-background p-6 shadow-lg sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
