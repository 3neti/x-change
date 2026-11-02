<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Requirement = { type: string; timing: string; requires_acceptance: boolean; status?: string | null };

const props = defineProps<{
    requirements: Requirement[];
    uploadUrl?: string | null;
    previewMode?: boolean;
    reloadOnly?: string;
}>();
const uploading = ref<string | null>(null);
const error = ref('');
const uploaded = ref<Record<string, string>>({});

async function upload(type: string, event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || !props.uploadUrl || props.previewMode) return;
    error.value = '';
    uploading.value = type;
    const data = new FormData();
    data.append('document_type', type);
    data.append('file', file);
    try {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch(props.uploadUrl, {
            method: 'POST', body: data, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        });
        if (!response.ok) throw new Error('Upload failed');
        const result = await response.json();
        uploaded.value = { ...uploaded.value, [type]: result.review_status === 'accepted' ? 'Accepted' : 'Uploaded for review' };
        router.reload({ only: [props.reloadOnly ?? 'payment'] });
    } catch {
        error.value = 'Could not upload the document. Please try again.';
    } finally {
        input.value = '';
        uploading.value = null;
    }
}
</script>

<template>
    <section v-if="requirements.length" class="space-y-3 rounded-xl border p-4" data-testid="participant-documents">
        <div>
            <h3 class="font-semibold">Documents requested</h3>
            <p class="text-sm text-muted-foreground">Add a photo or PDF for the issuer to review.</p>
        </div>
        <div v-for="item in requirements" :key="item.type" class="space-y-2 rounded-lg bg-muted/40 p-3">
            <p class="text-sm font-medium">{{ item.type.replaceAll('_', ' ') }}</p>
            <p class="text-xs text-muted-foreground">
                {{ item.timing === 'before_payment' ? 'Required before payment' : item.timing === 'later_review' ? 'Required for review' : 'Optional' }}
                <span v-if="item.requires_acceptance"> · Approval required</span>
            </p>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" :disabled="!!uploading || previewMode" class="block w-full text-sm" :aria-label="`Upload ${item.type}`" @change="upload(item.type, $event)" />
            <p v-if="uploading === item.type" class="text-xs">Uploading…</p>
            <p v-if="uploaded[item.type] || item.status" class="text-xs" role="status">{{ uploaded[item.type] || (item.status === 'accepted' ? 'Accepted' : item.status === 'rejected' ? 'Please resubmit' : 'Uploaded for review') }}</p>
        </div>
        <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
    </section>
</template>
