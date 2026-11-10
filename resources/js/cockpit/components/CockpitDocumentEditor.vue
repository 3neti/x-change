<script setup lang="ts">
type DocumentDefinition = { key: string; label: string; accept: string[]; required: boolean };
type Format = { label: string; types: string[] };
const props = withDefaults(defineProps<{
    title: string;
    mode: 'requirements' | 'types';
    documents?: DocumentDefinition[];
    formats?: Format[];
    types?: string[];
    selectedTypes?: string[];
    valid?: boolean;
    disabled?: boolean;
}>(), { documents: () => [], formats: () => [], types: () => [], selectedTypes: () => [], valid: true, disabled: false });
const emit = defineEmits<{
    'update:documents': [documents: DocumentDefinition[]];
    'update:selectedTypes': [types: string[]];
    add: [];
}>();
function update(index: number, changes: Partial<DocumentDefinition>): void {
    emit('update:documents', props.documents.map((document, i) => i === index ? { ...document, ...changes } : document));
}
function toggleFormat(index: number, types: string[], enabled: boolean): void {
    const current = props.documents[index].accept;
    update(index, { accept: [...new Set(enabled ? [...current, ...types] : current.filter(type => !types.includes(type)))] });
}
function toggleType(type: string, enabled: boolean): void {
    emit('update:selectedTypes', enabled ? [...props.selectedTypes, type] : props.selectedTypes.filter(item => item !== type));
}
</script>

<template>
  <div class="grid gap-3" data-testid="document-editor">
    <h3 class="text-sm font-semibold">{{ title }}</h3>
    <template v-if="mode === 'requirements'">
      <p class="text-xs text-slate-500 dark:text-slate-400">Choose up to five documents and the file formats you accept. The current charge is shown in the Order estimate.</p>
      <div v-for="(document, index) in documents" :key="document.key" class="grid gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
        <label class="grid gap-1 text-xs font-medium">Document name
          <input :value="document.label" :data-testid="`claim-document-label-${index}`" maxlength="100" :disabled="disabled" placeholder="e.g. Proof of identity" class="rounded-lg border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-950" @input="update(index, { label: ($event.target as HTMLInputElement).value })" />
        </label>
        <fieldset class="flex flex-wrap gap-3 text-xs" :disabled="disabled"><legend class="mb-1 font-medium">Accepted formats</legend>
          <label v-for="format in formats" :key="format.label" class="flex items-center gap-1"><input type="checkbox" :checked="format.types.every(type => document.accept.includes(type))" @change="toggleFormat(index, format.types, ($event.target as HTMLInputElement).checked)" /> {{ format.label }}</label>
        </fieldset>
        <label class="flex items-center gap-2 text-xs"><input type="checkbox" :checked="document.required" :disabled="disabled" @change="update(index, { required: ($event.target as HTMLInputElement).checked })" /> Required before completing the claim</label>
        <button type="button" class="justify-self-start text-xs font-medium text-rose-600" :disabled="disabled" @click="emit('update:documents', documents.filter((_, i) => i !== index))">Remove document</button>
      </div>
      <button type="button" class="justify-self-start rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-700" :disabled="disabled || documents.length >= 5" @click="emit('add')">Add document</button>
      <p v-if="!valid" class="text-xs text-rose-600" role="alert">Add a name and at least one accepted format for each document.</p>
    </template>
    <fieldset v-else :disabled="disabled" class="space-y-2"><legend class="sr-only">Allowed document types</legend>
      <label v-for="type in types" :key="type" class="flex gap-2 text-sm"><input type="checkbox" :checked="selectedTypes.includes(type)" @change="toggleType(type, ($event.target as HTMLInputElement).checked)">{{ type }}</label>
    </fieldset>
  </div>
</template>
