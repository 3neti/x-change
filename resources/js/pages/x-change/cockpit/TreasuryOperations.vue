<script setup lang="ts">
import TreasuryOperations from "../../../cockpit/pages/TreasuryOperations.vue";

type TreasuryOperationsProps = InstanceType<
  typeof TreasuryOperations
>["$props"];

defineProps<{
  cockpit_header_read_model?: Record<string, unknown>;
  treasury_account_grants: TreasuryOperationsProps["treasuryAccountGrants"];
  treasury_account_grant_store_url: string;
  treasury_institution_funds: TreasuryOperationsProps["treasuryInstitutionFunds"];
  treasury_institution_fund_store_url: string;
  treasury_reconciliation: TreasuryOperationsProps["treasuryReconciliation"];
  treasury_reconciliation_store_url: string;
}>();
</script>

<template>
  <TreasuryOperations
    :cockpit-header-read-model="cockpit_header_read_model"
    :treasury-account-grants="treasury_account_grants"
    :treasury-account-grant-store-url="treasury_account_grant_store_url"
    :treasury-institution-funds="treasury_institution_funds"
    :treasury-institution-fund-store-url="treasury_institution_fund_store_url"
    :treasury-reconciliation="treasury_reconciliation"
    :treasury-reconciliation-store-url="treasury_reconciliation_store_url"
  />
</template>
