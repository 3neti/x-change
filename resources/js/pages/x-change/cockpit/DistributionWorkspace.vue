<script setup lang="ts">
import DistributionWorkspace from '../../../cockpit/pages/DistributionWorkspace.vue';
import type { CockpitDistributionWorkspacePageProps } from '../../../cockpit/types';

const props = defineProps<CockpitDistributionWorkspacePageProps>();
</script>

<template>
    <DistributionWorkspace v-bind="props" />
</template>
