<script setup lang="ts">
import CommercialOfferings from "../../../cockpit/pages/CommercialOfferings.vue";

type CommercialOfferingsProps = InstanceType<
  typeof CommercialOfferings
>["$props"];

defineProps<{
  cockpit_header_read_model?: Record<string, unknown>;
  commercial_offering: CommercialOfferingsProps["commercialOffering"];
}>();
</script>

<template>
  <CommercialOfferings
    :cockpit-header-read-model="cockpit_header_read_model"
    :commercial-offering="commercial_offering"
  />
</template>
