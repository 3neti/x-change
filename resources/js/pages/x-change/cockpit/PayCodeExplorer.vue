<script setup lang="ts">
import PayCodeExplorer from '../../../cockpit/pages/PayCodeExplorer.vue';
import type { CockpitPayCodeExplorerPageProps } from '../../../cockpit/types';

const props = defineProps<CockpitPayCodeExplorerPageProps>();
</script>

<template>
    <PayCodeExplorer v-bind="props" />
</template>
