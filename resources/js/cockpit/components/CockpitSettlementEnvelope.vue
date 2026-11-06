<script setup lang="ts">
import { computed } from 'vue';
import CockpitContributionInvitations from './CockpitContributionInvitations.vue';
import CockpitDocumentReview from './CockpitDocumentReview.vue';

type Item = Record<string, unknown>;
const props = defineProps<{ envelope: Item }>();
const record = (value: unknown): Item => typeof value === 'object' && value !== null && !Array.isArray(value) ? value as Item : {};
const list = (value: unknown): Item[] => Array.isArray(value) ? value.filter((item): item is Item => typeof item === 'object' && item !== null && !Array.isArray(item)) : [];
const text = (value: unknown): string => typeof value === 'string' || typeof value === 'number' ? String(value) : '';
const title = (value: unknown): string => text(value).replace(/[._-]+/g, ' ').replace(/\b\w/g, character => character.toUpperCase());
const contribution = computed(() => record(props.envelope.contribution));
</script>

<template>
  <section class="space-y-4" data-testid="settlement-envelope-workspace">
    <div v-if="envelope.available !== true" class="rounded-2xl border border-slate-200 p-5 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">No settlement envelope is attached to this Pay Code.</div>
    <template v-else>
      <header class="rounded-2xl bg-slate-950 p-5 text-white">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Settlement envelope</p>
        <h3 class="mt-2 font-mono text-xl font-bold">{{ text(envelope.reference) }}</h3>
        <p class="mt-1 text-sm text-slate-300">{{ title(envelope.status) }} · {{ text(envelope.driver) }}@{{ text(envelope.driver_version) }}</p>
      </header>
      <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" data-testid="pay-code-settlement-gates">
        <h4 class="font-semibold">Readiness</h4>
        <p v-if="!list(envelope.gates).length" class="mt-2 text-sm text-slate-600 dark:text-slate-300">No envelope readiness gates configured.</p>
        <ul v-else class="mt-2 space-y-1 text-sm"><li v-for="gate in list(envelope.gates)" :key="text(gate.key)">{{ text(gate.label) }}: {{ gate.satisfied === true ? 'Ready' : 'Waiting' }}</li></ul>
      </section>
      <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" data-testid="pay-code-settlement-checklist">
        <h4 class="font-semibold">Checklist</h4>
        <p v-if="!list(envelope.checklist_items).length" class="mt-2 text-sm text-slate-600 dark:text-slate-300">No checklist items configured.</p>
        <ul v-else class="mt-2 space-y-2 text-sm"><li v-for="item in list(envelope.checklist_items)" :key="text(item.key)">{{ text(item.label) || title(item.key) }} · {{ title(item.status) }}<span v-if="item.required"> · Required</span></li></ul>
      </section>
      <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" data-testid="pay-code-settlement-signals">
        <h4 class="font-semibold">Signals</h4>
        <p v-if="!list(envelope.signals).length" class="mt-2 text-sm text-slate-600 dark:text-slate-300">No signals recorded.</p>
        <ul v-else class="mt-2 space-y-2 text-sm"><li v-for="signal in list(envelope.signals)" :key="text(signal.key)">{{ text(signal.label) }} · {{ !signal.present ? 'Not recorded' : signal.value === true ? 'Yes' : signal.value === false ? 'No' : 'Recorded' }}</li></ul>
      </section>
      <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" data-testid="pay-code-settlement-documents">
        <h4 class="font-semibold">Documents</h4>
        <p v-if="!list(envelope.attachments).length" class="mt-2 text-sm text-slate-600 dark:text-slate-300">No documents uploaded yet.</p>
        <div v-for="attachment in list(envelope.attachments)" :key="text(attachment.id)" class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
          <a :href="text(attachment.reveal_href)" target="_blank" rel="noopener" class="text-sm font-medium underline">{{ text(attachment.label) }}</a>
          <span class="ml-2 text-xs text-slate-500">{{ title(attachment.review_status) }}</span>
          <p v-if="text(attachment.rejection_reason)" class="mt-1 text-sm text-rose-700 dark:text-rose-300">{{ text(attachment.rejection_reason) }}</p>
          <CockpitDocumentReview v-if="text(attachment.review_href) && text(attachment.review_status) === 'pending'" :review-url="text(attachment.review_href)" />
        </div>
      </section>
      <CockpitContributionInvitations v-if="text(contribution.create_url)" :create-url="text(contribution.create_url)" :page-url="text(contribution.page_url)" :types="(contribution.types as string[]) ?? []" :invitations="(contribution.invitations as any[]) ?? []" />
      <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700" data-testid="pay-code-settlement-activity">
        <h4 class="font-semibold">Activity</h4>
        <p v-if="!list(envelope.activity).length" class="mt-2 text-sm text-slate-600 dark:text-slate-300">No activity available.</p>
        <ul v-else class="mt-2 space-y-2 text-sm"><li v-for="(entry, index) in list(envelope.activity)" :key="index">{{ title(entry.action) }} · {{ text(entry.occurred_at) }}</li></ul>
      </section>
    </template>
  </section>
</template>
