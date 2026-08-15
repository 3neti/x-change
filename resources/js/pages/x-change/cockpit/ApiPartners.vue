<script setup lang="ts">
import ApiPartners from "../../../cockpit/pages/ApiPartners.vue";

type ApiPartnersProps = InstanceType<typeof ApiPartners>["$props"];

defineProps<{
  cockpit_header_read_model?: Record<string, unknown>;
  partner_api: ApiPartnersProps["partnerApi"];
  partner_api_store_url: string;
  csrf_token: string;
}>();
</script>

<template>
  <ApiPartners
    :cockpit-header-read-model="cockpit_header_read_model"
    :partner-api="partner_api"
    :partner-api-store-url="partner_api_store_url"
    :csrf-token="csrf_token"
  />
</template>
