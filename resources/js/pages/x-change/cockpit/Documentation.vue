<script setup lang="ts">
import Documentation from '../../../cockpit/pages/Documentation.vue';
import { useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });
const props = useAttrs() as unknown as InstanceType<typeof Documentation>['$props'];
</script>

<template><Documentation v-bind="props" /></template>
