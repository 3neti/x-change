<script setup lang="ts">
import CampaignWorksheet from '../../../cockpit/pages/CampaignWorksheet.vue';

defineProps<{
    worksheet: Record<string, unknown>;
    imports: Array<Record<string, unknown>>;
    fulfillment_summary: Record<string, number>;
    authorization: Record<string, unknown>;
    fulfillments: Array<Record<string, unknown>>;
    direct_bank_transfer_enabled: boolean;
    delivery: Record<string, unknown>;
    cockpit_header_read_model?: Record<string, unknown>;
}>();
</script>

<template><CampaignWorksheet v-bind="$props" /></template>
