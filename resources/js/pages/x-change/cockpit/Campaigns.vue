<script setup lang="ts">
import Campaigns from '../../../cockpit/pages/Campaigns.vue';
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
};

const props = defineProps<CampaignsPageProps>();
</script>

<template>
    <Campaigns v-bind="props" />
</template>
