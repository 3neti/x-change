<script setup lang="ts">
import type { RiderContentFormat } from '../riderContent';
import CockpitRiderMessageEditor from './CockpitRiderMessageEditor.vue';
import CockpitRiderPreviewFrame from './CockpitRiderPreviewFrame.vue';
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from 'reka-ui';
import { MessageSquareText, Link2, Sparkles } from 'lucide-vue-next';

withDefaults(defineProps<{ title?: string; disabled?: boolean; urlPreviewDocument?: string; splashPreviewDocument?: string; urlPreviewMessage?: string; urlPreviewLoading?: boolean }>(), { title: 'Rider', disabled: false, urlPreviewDocument: '', splashPreviewDocument: '', urlPreviewMessage: '', urlPreviewLoading: false });
const message = defineModel<string>('message', { required: true });
const messageFormat = defineModel<RiderContentFormat>('messageFormat', { required: true });
const url = defineModel<string>('url', { required: true });
const redirectTimeout = defineModel<string>('redirectTimeout', { required: true });
const splash = defineModel<string>('splash', { required: true });
const splashFormat = defineModel<RiderContentFormat>('splashFormat', { required: true });
const splashTimeout = defineModel<string>('splashTimeout', { required: true });
const splashHeadline = defineModel<string>('splashHeadline', { required: true });
const splashCtaText = defineModel<string>('splashCtaText', { required: true });
</script>

<template>
  <TabsRoot default-value="message" class="grid gap-4" data-testid="rider-editor">
    <TabsList aria-label="Rider editor" class="sticky top-0 z-10 grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-white/95 p-1 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
      <TabsTrigger v-for="item in [{ value: 'message', label: 'Message', icon: MessageSquareText }, { value: 'link', label: 'Link', icon: Link2 }, { value: 'splash', label: 'Splash', icon: Sparkles }]" :key="item.value" :value="item.value" class="inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold text-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 data-[state=active]:bg-slate-950 data-[state=active]:text-white dark:text-slate-400 dark:data-[state=active]:bg-white dark:data-[state=active]:text-slate-950"><component :is="item.icon" class="size-3.5" aria-hidden="true" />{{ item.label }}</TabsTrigger>
    </TabsList>
    <TabsContent value="message"><section class="grid gap-2 rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><h3 class="text-sm font-semibold">{{ title }} message</h3><p class="text-xs text-slate-500 dark:text-slate-400">Shown to the recipient as the purpose.</p><CockpitRiderMessageEditor v-model:message="message" v-model:format="messageFormat" :disabled="disabled" /></section></TabsContent>
    <TabsContent value="link">
    <section class="grid gap-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><div><h3 class="text-sm font-semibold">After-claim destination</h3><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">An optional page to visit after the claim.</p></div>
      <div class="grid gap-4 md:grid-cols-2"><div class="grid content-start gap-3">
      <label class="grid gap-1 text-xs font-medium">URL<input v-model="url" type="url" placeholder="https://your-page.example" :disabled="disabled" data-testid="rider-editor-url" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" /></label>
      <label class="grid gap-1 text-xs font-medium">Redirect delay (seconds)<input v-model="redirectTimeout" type="number" min="0" max="300" :disabled="disabled" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" /></label>
      </div><div class="min-w-0 rounded-xl bg-slate-50 p-3 dark:bg-slate-900"><p class="text-xs font-semibold">Link preview</p><p v-if="!url.trim()" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Add a URL to see its preview.</p><template v-else><p v-if="urlPreviewLoading || urlPreviewMessage" class="mt-1 text-xs text-slate-500 dark:text-slate-400" role="status">{{ urlPreviewLoading ? 'Loading artwork…' : urlPreviewMessage }}</p><CockpitRiderPreviewFrame v-if="urlPreviewDocument" class="mt-2" title="Rider URL Preview" surface="stamp" :document="urlPreviewDocument" data-testid="rider-editor-url-preview" /></template></div></div>
    </section>
    </TabsContent>
    <TabsContent value="splash">
    <section class="grid gap-4 rounded-2xl border border-slate-200 p-4 dark:border-slate-800"><div><h3 class="text-sm font-semibold">Splash</h3><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">An optional introduction before the claim.</p></div><div class="grid gap-4 md:grid-cols-2"><div class="grid content-start gap-3">
      <label class="grid gap-1 text-xs font-medium">Format<select v-model="splashFormat" :disabled="disabled" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="plain">Text</option><option value="markdown">Markdown</option><option value="html">HTML</option></select></label>
      <label class="grid gap-1 text-xs font-medium">Splash message<textarea v-model="splash" rows="4" :disabled="disabled" data-testid="rider-editor-splash" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" /></label>
      <label class="grid gap-1 text-xs font-medium">Headline<input v-model="splashHeadline" :disabled="disabled" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" /></label>
      <label class="grid gap-1 text-xs font-medium">Button label<input v-model="splashCtaText" :disabled="disabled" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" /></label>
      <label class="grid gap-1 text-xs font-medium">Splash duration (seconds)<input v-model="splashTimeout" type="number" min="0" max="60" :disabled="disabled" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950" /></label>
      </div><div class="min-w-0 rounded-xl bg-slate-50 p-3 dark:bg-slate-900"><p class="text-xs font-semibold">Splash preview</p><p v-if="!splash.trim() && !splashHeadline.trim() && !splashCtaText.trim()" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Add splash content to see its preview.</p><CockpitRiderPreviewFrame v-else-if="splashPreviewDocument" class="mt-2" title="Claim Splash Preview" surface="splash" :document="splashPreviewDocument" data-testid="rider-editor-splash-preview" /></div></div>
    </section>
    </TabsContent>
  </TabsRoot>
</template>
