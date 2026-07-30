<script setup lang="ts">
import CampaignWorksheet from '../../../cockpit/pages/CampaignWorksheet.vue';

defineProps<InstanceType<typeof CampaignWorksheet>['$props']>();
</script>

<template><CampaignWorksheet v-bind="$props" /></template>
