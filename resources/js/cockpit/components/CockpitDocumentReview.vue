<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ reviewUrl: string }>();
const reason = ref('');
const rejecting = ref(false);
const working = ref(false);
const error = ref('');

async function review(decision: 'accepted' | 'rejected'): Promise<void> {
    if (decision === 'rejected' && !reason.value.trim()) {
        error.value = 'Enter a reason so the contributor knows what to change.';
        return;
    }
    working.value = true;
    error.value = '';
    try {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const response = await fetch(props.reviewUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ decision, ...(decision === 'rejected' ? { reason: reason.value.trim() } : {}) }),
        });
        if (!response.ok) throw new Error('Review failed');
        router.reload({ preserveScroll: true });
    } catch {
        error.value = 'Could not save this review. Refresh and try again.';
    } finally {
        working.value = false;
    }
}
</script>

<template>
    <div class="grid gap-2 border-t border-slate-200 p-3 dark:border-slate-800" data-testid="document-review">
        <div class="flex flex-wrap gap-2">
            <button type="button" :disabled="working" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50" @click="review('accepted')">Accept</button>
            <button type="button" :disabled="working" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-700" @click="rejecting = !rejecting">Request resubmission</button>
        </div>
        <div v-if="rejecting" class="grid gap-2">
            <label class="grid gap-1 text-xs font-medium">Reason for resubmission
                <textarea v-model="reason" maxlength="1000" class="rounded-lg border border-slate-300 bg-transparent p-2 dark:border-slate-700" />
            </label>
            <button type="button" :disabled="working" class="w-fit rounded-lg bg-slate-800 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50" @click="review('rejected')">Send request</button>
        </div>
        <p v-if="error" role="alert" class="text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
    </div>
</template>
