<!-- resources/js/pages/Disburse.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Disburse', href: '/disburse' },
];
</script>

<template>
    <Head title="Disburse" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1 class="text-2xl font-semibold">Disburse</h1>
            <p>This is the disburse page.</p>
        </div>
    </AppLayout>
</template>
