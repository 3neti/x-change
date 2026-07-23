<script setup lang="ts">
import Accounts from '../../../cockpit/pages/Accounts.vue';
import type { CockpitAccountsPageProps } from '../../../cockpit/types';

const props = defineProps<CockpitAccountsPageProps>();
</script>

<template>
    <Accounts v-bind="props" />
</template>
