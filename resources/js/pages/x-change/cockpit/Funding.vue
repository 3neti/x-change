<script setup lang="ts">
import Funding from '../../../cockpit/pages/Funding.vue';
import type { CockpitFundingPageProps } from '../../../cockpit/types';

const props = defineProps<CockpitFundingPageProps>();
</script>

<template>
    <Funding v-bind="props" />
</template>
