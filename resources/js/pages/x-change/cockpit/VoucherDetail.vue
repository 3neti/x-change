<script setup lang="ts">
import VoucherDetail from '../../../cockpit/pages/VoucherDetail.vue';
import type { CockpitVoucherDetailPageProps } from '../../../cockpit/types';

const props = defineProps<CockpitVoucherDetailPageProps>();
</script>

<template>
    <VoucherDetail v-bind="props" />
</template>
