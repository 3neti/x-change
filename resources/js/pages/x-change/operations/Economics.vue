<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import OperationsEconomicsDashboard from "../../../components/x-change/OperationsEconomicsDashboard.vue";

defineOptions({ layout: [] });
defineProps<{ title: string; surface: string; operations_url: string; filters: { period: string; currency: string }; reports: any }>();
</script>

<template>
  <Head :title="title" />
  <OperationsEconomicsDashboard :title="title" :filters="filters" :reports="reports" :operations-url="operations_url" />
</template>
