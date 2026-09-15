<script setup lang="ts">
import LeadCampaignScenarioRunner from '../../../cockpit/pages/LeadCampaignScenarioRunner.vue';
import type { CockpitHeaderPageProps } from '../../../cockpit/types';

type LeadScenario = {
    schema: string;
    key: string;
    title: string;
    description: string;
    entry_point: string;
    person_type: string;
    pay_code_generation: string;
    claim_surface: string;
    amount: string;
    action_url: string;
    fields: string[];
};

type RecentLeadCampaign = {
    reference: string;
    title: string;
    merchant_display_name: string;
    public_url: string;
    usage_count: number;
    last_started_at: string | null;
    status: string;
};

type LeadCampaignScenarioRunnerPageProps = CockpitHeaderPageProps & {
    scenario: LeadScenario;
    recent_lead_campaigns: RecentLeadCampaign[];
};

const props = defineProps<LeadCampaignScenarioRunnerPageProps>();
</script>

<template>
    <LeadCampaignScenarioRunner v-bind="props" />
</template>
