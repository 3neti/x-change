<script setup lang="ts">
import { onMounted, ref } from 'vue';

defineOptions({ layout: null });

const props = defineProps<{ contextUrl: string; passwordUrl: string; uploadUrl: string }>();
const token = typeof window === 'undefined' ? '' : window.location.hash.slice(1);
const password = ref('');
const verified = ref(false);
const passwordRequired = ref(false);
const documentTypes = ref<string[]>([]);
const documents = ref<{ id: number; name: string; type: string; status: string; view_url: string }[]>([]);
const documentType = ref('');
const file = ref<File | null>(null);
const working = ref(false);
const error = ref('');

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function context(): Promise<void> {
    const response = await fetch(props.contextUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({ token }),
    });
    if (!response.ok) throw new Error('This invitation is unavailable or has expired.');
    const data = await response.json();
    verified.value = data.verified;
    passwordRequired.value = data.password_required;
    documentTypes.value = data.document_types;
    documents.value = data.documents;
    if (!documentTypes.value.includes(documentType.value)) documentType.value = documentTypes.value[0] ?? '';
}

async function unlock(): Promise<void> {
    working.value = true; error.value = '';
    try {
        const response = await fetch(props.passwordUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ token, password: password.value }),
        });
        if (!response.ok) throw new Error('Unable to open this invitation. Check your password and try again.');
        password.value = '';
        await context();
    } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Please try again.'; }
    finally { working.value = false; }
}

async function upload(): Promise<void> {
    if (!file.value || !documentType.value) return;
    working.value = true; error.value = '';
    try {
        const form = new FormData();
        form.append('token', token); form.append('document_type', documentType.value); form.append('file', file.value);
        const response = await fetch(props.uploadUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: form });
        if (!response.ok) throw new Error('The document could not be uploaded. Check its type and size and try again.');
        file.value = null;
        await context();
    } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Please try again.'; }
    finally { working.value = false; }
}

onMounted(async () => {
    if (!token) { error.value = 'This invitation is unavailable or has expired.'; return; }
    try { await context(); } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Please try again.'; }
});
</script>

<template>
  <main class="mx-auto min-h-screen max-w-xl p-5 text-slate-950 dark:text-slate-100 sm:p-8">
    <h1 class="text-2xl font-semibold">Submit documents</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Upload the documents requested in your invitation.</p>
    <p v-if="error" role="alert" class="mt-4 text-sm text-red-700 dark:text-red-300">{{ error }}</p>
    <form v-if="passwordRequired && !verified" class="mt-6 space-y-3" @submit.prevent="unlock">
      <label class="block text-sm font-medium" for="contribution-password">Invitation password</label>
      <input id="contribution-password" v-model="password" type="password" autocomplete="current-password" class="w-full rounded-lg border p-3 dark:border-slate-700 dark:bg-slate-900" required>
      <button type="submit" :disabled="working" class="rounded-lg bg-slate-900 px-4 py-3 text-white disabled:opacity-50">Open invitation</button>
    </form>
    <div v-if="verified" class="mt-6 space-y-5">
      <form class="space-y-3" @submit.prevent="upload">
        <label class="block text-sm font-medium" for="contribution-type">Document type</label>
        <select id="contribution-type" v-model="documentType" class="w-full rounded-lg border p-3 dark:border-slate-700 dark:bg-slate-900"><option v-for="type in documentTypes" :key="type" :value="type">{{ type }}</option></select>
        <label class="block text-sm font-medium" for="contribution-file">Choose a document</label>
        <input id="contribution-file" type="file" accept=".pdf,.png,.jpg,.jpeg" class="block w-full text-sm" @change="file = ($event.target as HTMLInputElement).files?.[0] ?? null">
        <button type="submit" :disabled="working || !file" class="rounded-lg bg-slate-900 px-4 py-3 text-white disabled:opacity-50">{{ working ? 'Uploading…' : 'Upload document' }}</button>
      </form>
      <section v-if="documents.length" aria-label="Your submissions">
        <h2 class="font-semibold">Your submissions</h2>
        <ul class="mt-2 divide-y"><li v-for="document in documents" :key="document.id" class="flex items-center justify-between gap-3 py-3 text-sm"><span>{{ document.name }} · {{ document.status }}</span><form :action="document.view_url" method="POST" target="_blank"><input type="hidden" name="_token" :value="csrf()"><input type="hidden" name="token" :value="token"><button type="submit" class="font-semibold underline">View</button></form></li></ul>
      </section>
    </div>
  </main>
</template>
