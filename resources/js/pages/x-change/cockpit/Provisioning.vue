<script setup lang="ts">
import Provisioning from "../../../cockpit/pages/Provisioning.vue";

type ProvisioningProps = InstanceType<typeof Provisioning>["$props"];

defineProps<{
  cockpit_header_read_model?: ProvisioningProps["cockpitHeaderReadModel"];
  provisioning: ProvisioningProps["provisioning"];
  provisioning_store_url: string;
  csrf_token: string;
}>();
</script>

<template>
  <Provisioning
    :cockpit-header-read-model="cockpit_header_read_model"
    :provisioning="provisioning"
    :provisioning-store-url="provisioning_store_url"
    :csrf-token="csrf_token"
  />
</template>
