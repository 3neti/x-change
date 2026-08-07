<script setup lang="ts">
import CommercialOfferings from '../../../cockpit/pages/CommercialOfferings.vue';

defineProps<{
    cockpit_header_read_model?: Record<string, unknown>;
    commercial_offering: Record<string, unknown>;
}>();
</script>

<template>
    <CommercialOfferings
        :cockpit-header-read-model="cockpit_header_read_model"
        :commercial-offering="commercial_offering"
    />
</template>
