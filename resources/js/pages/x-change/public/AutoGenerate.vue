<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { computed } from "vue";
import QuickGenerate from "../../../cockpit/pages/QuickGenerate.vue";
import type { CockpitQuickGeneratePageProps } from "../../../cockpit/types";

defineOptions({ layout: null });

const props = defineProps<CockpitQuickGeneratePageProps>();
const structuredData = computed(() =>
  JSON.stringify(props.service_discovery?.structured_data ?? {}),
);
</script>

<template>
  <div>
    <Head title="Create a Pay Code">
      <meta
        v-if="props.service_discovery"
        head-key="description"
        name="description"
        :content="props.service_discovery.description"
      />
      <link
        v-if="props.service_discovery"
        head-key="canonical"
        rel="canonical"
        :href="props.service_discovery.canonical_url"
      />
      <component :is="'script'" type="application/ld+json">
        {{ structuredData }}
      </component>
    </Head>
    <QuickGenerate v-bind="props" />
  </div>
</template>
