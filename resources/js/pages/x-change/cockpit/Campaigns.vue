<script setup lang="ts">
import Campaigns from '../../../cockpit/pages/Campaigns.vue';
import type { CampaignWorkflowDraftCatalog } from '../../../cockpit/components/CockpitCampaignWorkflowDraftEditor.vue';
import type { CockpitHeaderPageProps } from '../../../cockpit/types';

type CampaignWorksheet = {
    reference: string;
    profile: 'payroll' | 'assistance';
    name: string;
    currency: string;
    status: string;
    fulfillment_mode: string;
    delivery_plan: string[];
    beneficiary_count: number;
    principal_minor: number;
    updated_at: string | null;
};

type CampaignsPageProps = CockpitHeaderPageProps & {
    worksheets: CampaignWorksheet[];
    active_intake?: Record<string, unknown>;
    campaign_usage_profiles?: Record<string, unknown>[];
    endpoint_capabilities?: Record<string, unknown>[];
    pay_code_templates?: Record<string, unknown>[];
    endpoint_campaigns?: Record<string, unknown>[];
    endpoint_campaign_form?: Record<string, unknown>;
    workflow_drafts?: CampaignWorkflowDraftCatalog;
};

const props = defineProps<CampaignsPageProps>();
</script>

<template>
    <Campaigns v-bind="props" />
</template>
