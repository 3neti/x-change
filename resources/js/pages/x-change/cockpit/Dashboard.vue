<script setup lang="ts">
import Dashboard from '../../../cockpit/pages/Dashboard.vue';
import type { CockpitDashboardPageProps } from '../../../cockpit/types';

const props = defineProps<CockpitDashboardPageProps>();
</script>

<template>
    <Dashboard v-bind="props" />
</template>
