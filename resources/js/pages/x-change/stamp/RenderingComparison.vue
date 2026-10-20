<script setup lang="ts">
import { ref } from 'vue';
import { preview } from '@/routes/x-change/stamps';

const props = defineProps<{ designs: Array<{ id: string; revision: number; name: string }> }>();
const designId = ref('steampunk');
const splash = ref('');
const busy = ref(false);
const results = ref<Array<{ profile: string; image?: string; width?: number; height?: number; rendering_ms?: number; file_bytes?: number; error?: string }>>([]);
async function compare() {
    busy.value = true;
    results.value = [];
    try {
        for (const profile of ['standard', 'browser', 'browser-hd']) {
            try {
                const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
                const response = await fetch(preview.url(), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice(11)) } : {}) },
                    body: JSON.stringify({ instructions: {
                        cash: { amount: 500, currency: 'PHP' }, provider: 'netbank',
                        inputs: { fields: ['signature', 'selfie'] },
                        rider: { ...(splash.value ? { splash: splash.value, splash_format: 'html', og_source: 'splash' } : {}), stamp: { version: 3, design_id: designId.value, design_version: props.designs.find(design => design.id === designId.value)?.revision, claim_marker: 'qr', artwork_source: splash.value ? 'splash' : 'x_change' } },
                        metadata: { custom: { stamp_rendering: { profile } } },
                    } }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Rendering failed');
                results.value.push({ profile, ...result });
            } catch (error) {
                results.value.push({ profile, error: error instanceof Error ? error.message : 'Rendering failed' });
            }
        }
    } finally { busy.value = false; }
}
</script>

<template>
    <main class="min-h-screen bg-white p-6 text-slate-900">
        <h1 class="mb-4 text-2xl font-semibold">Stamp renderer comparison</h1>
        <p class="mb-4 text-sm">Local synthetic preview · || AA317 || · ₱500.00 · No issuance</p>
        <label class="block">Design
            <select v-model="designId" class="m-2 rounded border p-2"><option v-for="design in designs" :key="design.id" :value="design.id">{{ design.name || design.id }}</option></select>
        </label>
        <label class="block">Optional rider splash HTML
            <textarea v-model="splash" class="my-2 block min-h-28 w-full rounded border p-2" />
        </label>
        <button :disabled="busy" class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50" @click="compare">{{ busy ? 'Rendering…' : 'Compare renderers' }}</button>
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <article v-for="result in results" :key="result.profile" class="min-w-0">
                <h2 class="mb-2 font-semibold">{{ result.profile }}</h2>
                <p v-if="result.error" role="alert">{{ result.error }}</p>
                <template v-else>
                    <img :src="result.image" :alt="`${result.profile} Stamp preview`" class="w-full" />
                    <p class="my-2 text-sm">{{ result.width }} × {{ result.height }} · {{ result.rendering_ms }} ms · {{ ((result.file_bytes || 0) / 1024).toFixed(1) }} KB</p>
                    <a :href="result.image" :download="`stamp-${result.profile}.png`" class="underline">Download PNG</a>
                </template>
            </article>
        </div>
    </main>
</template>
