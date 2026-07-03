<script setup lang="ts">
import QuickGenerate from '../../../cockpit/pages/QuickGenerate.vue';
import type { CockpitQuickGeneratePageProps } from '../../../cockpit/types';

const props = defineProps<CockpitQuickGeneratePageProps>();
</script>

<template>
    <QuickGenerate v-bind="props" />
</template>
