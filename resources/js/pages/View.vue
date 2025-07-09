<!-- resources/js/pages/View.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'View', href: '/view' },
];
</script>

<template>
    <Head title="View" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1 class="text-2xl font-semibold">View</h1>
            <p>This is the view page.</p>
        </div>
    </AppLayout>
</template>
