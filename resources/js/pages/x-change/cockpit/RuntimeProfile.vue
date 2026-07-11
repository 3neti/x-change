<script setup lang="ts">
import RuntimeProfile from '../../../cockpit/pages/RuntimeProfile.vue';
import type { CockpitRuntimeProfilePageProps } from '../../../cockpit/types';

const props = defineProps<CockpitRuntimeProfilePageProps>();
</script>

<template>
    <RuntimeProfile v-bind="props" />
</template>
