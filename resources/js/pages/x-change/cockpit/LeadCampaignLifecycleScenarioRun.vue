<script setup lang="ts">
import LeadCampaignLifecycleScenarioRun from "../../../cockpit/pages/LeadCampaignLifecycleScenarioRun.vue";
import type { CockpitHeaderPageProps } from "../../../cockpit/types";

type ScenarioRun = {
  schema: string;
  reference: string;
  scenario: string;
  title: string;
  mode: string;
  status: string;
  started_at: string | null;
  updated_at: string | null;
  declarations: Record<string, string>;
  steps: Array<{
    sequence: number;
    label: string;
    status: string;
    description: string;
    occurred_at: string | null;
    facts: Record<string, unknown>;
  }>;
  artifacts: Array<{
    group: string;
    label: string;
    reference: string | null;
    href: string | null;
    evidence: string;
  }>;
};

const props = defineProps<CockpitHeaderPageProps & { run: ScenarioRun }>();
</script>

<template>
  <LeadCampaignLifecycleScenarioRun v-bind="props" />
</template>
