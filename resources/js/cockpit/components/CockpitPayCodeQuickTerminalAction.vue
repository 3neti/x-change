<script setup lang="ts">
import CockpitPayCodeTerminalStateController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeTerminalStateController';
import { Form } from '@inertiajs/vue3';
import { Ban, ClockAlert } from 'lucide-vue-next';

const props = defineProps<{
    code: string;
    action: 'expire' | 'cancel';
    enabled: boolean;
    blockedReason: string | null;
}>();
</script>

<template>
    <button
        v-if="!enabled"
        type="button"
        disabled
        :aria-label="`${action} ${code} unavailable`"
        :title="blockedReason ?? 'Terminal action unavailable'"
        class="inline-flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-400 disabled:cursor-not-allowed dark:bg-slate-800 dark:text-slate-500"
        data-testid="cockpit-pay-code-terminal-action-disabled"
    >
        <ClockAlert v-if="action === 'expire'" aria-hidden="true" class="size-3.5" />
        <Ban v-else aria-hidden="true" class="size-3.5" />
    </button>
    <details v-else class="group relative">
        <summary
            :aria-label="`${action} Pay Code ${code}`"
            :title="action === 'expire' ? 'Expire Pay Code' : 'Cancel Pay Code'"
            class="flex size-8 cursor-pointer list-none items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-700 marker:hidden transition hover:bg-amber-100 group-open:ring-2 group-open:ring-amber-300 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            data-testid="cockpit-pay-code-terminal-action-toggle"
        >
            <ClockAlert v-if="action === 'expire'" aria-hidden="true" class="size-3.5" />
            <Ban v-else aria-hidden="true" class="size-3.5" />
        </summary>
        <div class="absolute right-0 z-30 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-semibold text-slate-950 dark:text-white">
                {{ action === 'expire' ? 'Expire Pay Code' : 'Cancel Pay Code' }}
            </p>
            <Form
                v-bind="CockpitPayCodeTerminalStateController.form({ code })"
                #default="{ errors, processing }"
                class="mt-2 grid gap-2"
                data-testid="cockpit-pay-code-quick-terminal-form"
            >
                <input type="hidden" name="action" :value="action" />
                <textarea
                    name="reason"
                    required
                    minlength="3"
                    maxlength="500"
                    rows="2"
                    class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    :placeholder="`Reason to ${action} this Pay Code`"
                />
                <span v-if="errors.reason" class="text-[0.7rem] text-rose-700">{{ errors.reason }}</span>
                <label class="flex items-start gap-2 text-[0.7rem] leading-4 text-slate-600 dark:text-slate-300">
                    <input name="confirmed" type="checkbox" value="1" required class="mt-0.5" />
                    I understand this governed action is immediate.
                </label>
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-60 dark:bg-white dark:text-slate-950"
                >
                    {{ processing ? 'Applying…' : `Confirm ${action}` }}
                </button>
            </Form>
        </div>
    </details>
</template>
