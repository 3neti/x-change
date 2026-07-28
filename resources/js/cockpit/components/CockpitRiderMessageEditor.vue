<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import type { RiderContentFormat } from '../riderContent';
import { renderRiderContent } from '../riderContent';
import { buildSandboxedPreviewDocument } from '../riderStampPreview';
import CockpitRiderPreviewFrame from './CockpitRiderPreviewFrame.vue';

const props = withDefaults(
    defineProps<{
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const message = defineModel<string>('message', { required: true });
const format = defineModel<RiderContentFormat>('format', {
    default: 'plain',
});
const textarea = ref<HTMLTextAreaElement | null>(null);

const previewDocument = computed<string>(() =>
    buildSandboxedPreviewDocument(
        renderRiderContent(message.value, format.value),
    ),
);

async function wrapSelection(
    prefix: string,
    suffix: string,
    placeholder: string,
): Promise<void> {
    if (props.disabled) {
        return;
    }

    const element = textarea.value;
    const start = element?.selectionStart ?? message.value.length;
    const end = element?.selectionEnd ?? message.value.length;
    const selected = message.value.slice(start, end) || placeholder;

    format.value = 'markdown';
    message.value = `${message.value.slice(0, start)}${prefix}${selected}${suffix}${message.value.slice(end)}`;

    await nextTick();

    element?.focus();
    element?.setSelectionRange(
        start + prefix.length,
        start + prefix.length + selected.length,
    );
}

function addList(): void {
    void wrapSelection('- ', '', 'List item');
}
</script>

<template>
    <div class="grid gap-3">
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <label
                class="grid min-w-40 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
            >
                Format
                <select
                    v-model="format"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                    data-testid="cockpit-rider-message-format"
                    :disabled="disabled"
                >
                    <option value="plain">Plain Text</option>
                    <option value="markdown">Rich Text</option>
                </select>
            </label>
            <div
                class="flex flex-wrap gap-1"
                aria-label="Rider Message formatting"
            >
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="disabled"
                    aria-label="Bold"
                    @click="wrapSelection('**', '**', 'bold text')"
                >
                    B
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 italic hover:bg-slate-100 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="disabled"
                    aria-label="Italic"
                    @click="wrapSelection('*', '*', 'italic text')"
                >
                    I
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="disabled"
                    aria-label="Link"
                    @click="
                        wrapSelection(
                            '[',
                            '](https://example.com)',
                            'link text',
                        )
                    "
                >
                    Link
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="disabled"
                    aria-label="List"
                    @click="addList"
                >
                    List
                </button>
            </div>
        </div>

        <label
            class="grid min-w-0 gap-1 text-xs font-medium text-slate-700 dark:text-slate-300"
        >
            Message
            <textarea
                ref="textarea"
                v-model="message"
                rows="4"
                class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-50"
                data-testid="cockpit-quick-generate-submit-purpose"
                :disabled="disabled"
            />
        </label>

        <div
            v-if="message.trim() !== ''"
            class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
        >
            <p
                class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400"
            >
                Message Preview
            </p>
            <CockpitRiderPreviewFrame
                title="Rider Message Preview"
                class="mt-2 border-slate-200 dark:border-slate-800"
                data-testid="cockpit-rider-message-preview"
                :document="previewDocument"
            />
        </div>
    </div>
</template>
