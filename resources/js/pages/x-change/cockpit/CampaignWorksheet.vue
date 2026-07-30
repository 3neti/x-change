<script setup lang="ts">
import CampaignWorksheet from '../../../cockpit/pages/CampaignWorksheet.vue';

defineOptions({ inheritAttrs: false });
</script>

<template><CampaignWorksheet v-bind="$attrs" /></template>
