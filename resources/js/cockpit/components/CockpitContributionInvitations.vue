<script setup lang="ts">
import { ref } from 'vue';

type Invitation = { id: number; expires_at: string; revoked: boolean; types: string[]; revoke_url: string };
const props = defineProps<{ createUrl: string; pageUrl: string; types: string[]; invitations: Invitation[] }>();
const invitations = ref([...props.invitations]);
const selected = ref<string[]>([]);
const days = ref(7);
const password = ref('');
const link = ref('');
const error = ref('');
const working = ref(false);
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

async function create(): Promise<void> {
    working.value = true; error.value = ''; link.value = '';
    try {
        const response = await fetch(props.createUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ document_types: selected.value, expires_days: days.value, password: password.value || null }),
        });
        if (!response.ok) throw new Error('Could not create the invitation. Check the details and try again.');
        const result = await response.json();
        link.value = `${props.pageUrl}#${result.token}`;
        invitations.value.unshift({ id: result.id, expires_at: result.expires_at, revoked: false, types: [...selected.value], revoke_url: result.revoke_url });
        password.value = '';
    } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Please try again.'; }
    finally { working.value = false; }
}

async function revoke(invitation: Invitation): Promise<void> {
    working.value = true; error.value = '';
    try {
        const response = await fetch(invitation.revoke_url, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        if (!response.ok) throw new Error('Could not revoke the invitation.');
        invitation.revoked = true;
    } catch (exception) { error.value = exception instanceof Error ? exception.message : 'Please try again.'; }
    finally { working.value = false; }
}
</script>

<template>
  <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800" data-testid="contribution-invitations">
    <h4 class="text-sm font-semibold">Document invitations</h4>
    <p class="mt-1 text-xs text-slate-500">Choose what the recipient may submit and when the invitation expires.</p>
    <form class="mt-3 space-y-3" @submit.prevent="create">
      <label v-for="type in types" :key="type" class="flex gap-2 text-sm"><input v-model="selected" type="checkbox" :value="type">{{ type }}</label>
      <label class="block text-sm">Expires in days <input v-model.number="days" type="number" min="1" max="90" required class="w-20 rounded border p-2"></label>
      <label class="block text-sm">Password (optional) <input v-model="password" type="password" minlength="8" class="w-full rounded border p-2"></label>
      <button type="submit" :disabled="working || !selected.length" class="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-50">Create invitation</button>
    </form>
    <p v-if="error" role="alert" class="mt-2 text-sm text-red-700">{{ error }}</p>
    <div v-if="link" class="mt-3"><label class="text-sm">Share this link <input :value="link" readonly class="w-full rounded border p-2"></label></div>
    <ul v-if="invitations.length" class="mt-4 divide-y text-sm"><li v-for="invitation in invitations" :key="invitation.id" class="flex items-center justify-between gap-3 py-2"><span>{{ invitation.types.join(', ') }} · {{ invitation.revoked ? 'Revoked' : `Expires ${new Date(invitation.expires_at).toLocaleDateString()}` }}</span><button v-if="!invitation.revoked" type="button" :disabled="working" class="text-red-700" @click="revoke(invitation)">Revoke</button></li></ul>
  </section>
</template>
