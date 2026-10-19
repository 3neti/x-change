<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FormFlowStatusIcon from '@/pages/form-flow/core/components/FormFlowStatusIcon.vue';
import type { ClaimIconRole, FormFlowPresentation } from '@/pages/form-flow/core/components/formFlowPresentation';
const props = defineProps<{role: ClaimIconRole; profile?: FormFlowPresentation | null}>();
const page=usePage();
const profile=computed(()=>(props.profile ?? page.props?.claim_ui ?? null) as FormFlowPresentation | null);
</script>
<template><FormFlowStatusIcon :role="role" :presentation="profile" /></template>
