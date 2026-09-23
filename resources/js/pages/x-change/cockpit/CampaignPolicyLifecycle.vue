<script setup lang="ts">
import CampaignPolicyLifecycle from "../../../cockpit/pages/CampaignPolicyLifecycle.vue";
import type { CockpitHeaderPageProps } from "../../../cockpit/types";

type Props = CockpitHeaderPageProps & { lifecycles: Record<string, unknown>[] };
const props = defineProps<Props>();
</script>

<template>
  <CampaignPolicyLifecycle v-bind="props" />
</template>
