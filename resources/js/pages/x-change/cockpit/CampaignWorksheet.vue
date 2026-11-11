<script setup lang="ts">
import CampaignWorksheet from '../../../cockpit/pages/CampaignWorksheet.vue';
import { useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });
const props = useAttrs() as unknown as InstanceType<typeof CampaignWorksheet>['$props'];
</script>

<template><CampaignWorksheet v-bind="props" /></template>
