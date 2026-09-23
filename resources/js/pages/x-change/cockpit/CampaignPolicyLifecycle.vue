<script setup lang="ts">
import CampaignPolicyLifecycle from "../../../cockpit/pages/CampaignPolicyLifecycle.vue";
import type { CampaignPolicyLifecycle as CampaignPolicyLifecycleRecord } from "../../../cockpit/campaignPolicyLifecycle";
import type { CockpitHeaderPageProps } from "../../../cockpit/types";

type Props = CockpitHeaderPageProps & {
  lifecycles: CampaignPolicyLifecycleRecord[];
};
const props = defineProps<Props>();
</script>

<template>
  <CampaignPolicyLifecycle v-bind="props" />
</template>
