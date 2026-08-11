<script setup lang="ts">
import { Link2, Mail, MessageSquareText, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import {
    classifyFeedbackDestination,
    type FeedbackChannel,
    type FeedbackDestinations,
    splitFeedbackDestinationInput,
} from '../feedbackDestinations';

const props = withDefaults(
    defineProps<{
        defaults?: Partial<FeedbackDestinations>;
        unavailable?: Partial<Record<FeedbackChannel, string>>;
        disabled?: boolean;
    }>(),
    {
        defaults: () => ({}),
        unavailable: () => ({}),
        disabled: false,
    },
);

const emit = defineEmits<{
    validation: [errors: string[]];
}>();

const destinations = defineModel<FeedbackDestinations>({ required: true });
const draft = ref('');
const invalidDestinations = ref<string[]>([]);
const statusMessage = ref('');
const input = ref<HTMLInputElement | null>(null);

const channelPresentation: Record<
    FeedbackChannel,
    { label: string; suggestion: string }
> = {
    email: { label: 'Email', suggestion: 'Use My Email' },
    mobile: { label: 'SMS', suggestion: 'Use My Mobile' },
    webhook: { label: 'Webhook', suggestion: 'Use My Webhook' },
};

const configuredChannels = computed<FeedbackChannel[]>(() =>
    (['email', 'mobile', 'webhook'] as FeedbackChannel[]).filter(
        (channel) => destinations.value[channel] !== '',
    ),
);

const suggestedChannels = computed<FeedbackChannel[]>(() =>
    (['email', 'mobile', 'webhook'] as FeedbackChannel[]).filter(
        (channel) =>
            (props.defaults[channel] ?? '') !== '' &&
            destinations.value[channel] === '',
    ),
);

const validationErrors = computed<string[]>(() =>
    invalidDestinations.value.map(
        (value) =>
            `“${value}” is not a valid email, Philippine mobile number, or http(s) webhook URL.`,
    ),
);

watch(
    validationErrors,
    (errors) => {
        emit('validation', errors);
    },
    { immediate: true },
);

function channelIcon(channel: FeedbackChannel): typeof Mail {
    if (channel === 'mobile') {
        return MessageSquareText;
    }

    if (channel === 'webhook') {
        return Link2;
    }

    return Mail;
}

function displayDestination(channel: FeedbackChannel): string {
    const value = destinations.value[channel];

    if (channel === 'webhook' && value.length > 38) {
        return `${value.slice(0, 35)}…`;
    }

    return value;
}

function setDestination(channel: FeedbackChannel, value: string): void {
    const previous = destinations.value[channel];

    destinations.value = {
        ...destinations.value,
        [channel]: value,
    };

    statusMessage.value =
        previous !== '' && previous !== value
            ? `${channelPresentation[channel].label} destination replaced.`
            : `${channelPresentation[channel].label} destination added.`;
}

function removeDestination(channel: FeedbackChannel): void {
    destinations.value = {
        ...destinations.value,
        [channel]: '',
    };
    statusMessage.value = `${channelPresentation[channel].label} destination removed.`;
}

function commit(value: string): void {
    const candidates = splitFeedbackDestinationInput(value);
    const nextDestinations = { ...destinations.value };
    let nextInvalidDestinations = [...invalidDestinations.value];
    let latestStatus = '';

    candidates.forEach((candidate) => {
        const classified = classifyFeedbackDestination(candidate);

        if (classified === null) {
            if (!nextInvalidDestinations.includes(candidate)) {
                nextInvalidDestinations.push(candidate);
            }

            return;
        }

        const unavailableReason = props.unavailable[classified.channel];

        if (unavailableReason !== undefined && unavailableReason !== '') {
            latestStatus = unavailableReason;

            return;
        }

        nextInvalidDestinations = nextInvalidDestinations.filter(
            (invalid) => invalid !== candidate,
        );
        const previous = nextDestinations[classified.channel];
        nextDestinations[classified.channel] = classified.value;
        latestStatus =
            previous !== '' && previous !== classified.value
                ? `${channelPresentation[classified.channel].label} destination replaced.`
                : `${channelPresentation[classified.channel].label} destination added.`;
    });

    destinations.value = nextDestinations;
    invalidDestinations.value = nextInvalidDestinations;
    statusMessage.value = latestStatus;
}

function commitDraft(): void {
    if (draft.value.trim() === '') {
        return;
    }

    commit(draft.value);
    draft.value = '';
}

function handleDelimiter(event: KeyboardEvent): void {
    if (!['Enter', 'Tab', ',', ';'].includes(event.key)) {
        return;
    }

    if (draft.value.trim() === '') {
        return;
    }

    event.preventDefault();
    commitDraft();
}

function handleBackspace(): void {
    if (draft.value !== '' || configuredChannels.value.length === 0) {
        return;
    }

    removeDestination(configuredChannels.value.at(-1) ?? 'email');
}

function handlePaste(event: ClipboardEvent): void {
    const value = event.clipboardData?.getData('text') ?? '';

    if (value === '') {
        return;
    }

    event.preventDefault();
    commit(value);
    draft.value = '';
}

async function editDestination(channel: FeedbackChannel): Promise<void> {
    draft.value = destinations.value[channel];
    removeDestination(channel);
    await nextTick();
    input.value?.focus();
    input.value?.select();
}

async function editInvalidDestination(value: string): Promise<void> {
    invalidDestinations.value = invalidDestinations.value.filter(
        (candidate) => candidate !== value,
    );
    draft.value = value;
    await nextTick();
    input.value?.focus();
    input.value?.select();
}

function removeInvalidDestination(value: string): void {
    invalidDestinations.value = invalidDestinations.value.filter(
        (candidate) => candidate !== value,
    );
}

function useDefault(channel: FeedbackChannel): void {
    const value = props.defaults[channel] ?? '';
    const classified = classifyFeedbackDestination(value);

    if (classified !== null) {
        setDestination(classified.channel, classified.value);
    }
}
</script>

<template>
    <div
        class="grid min-w-0 gap-1"
        data-testid="cockpit-feedback-destination-input"
    >
        <div class="flex min-w-0 items-stretch gap-1.5">
            <div
                class="flex min-h-9 min-w-0 flex-1 flex-wrap items-center gap-1 rounded-lg border bg-white px-2 py-1 shadow-sm transition focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-100 dark:bg-slate-900 dark:focus-within:border-emerald-600 dark:focus-within:ring-emerald-950"
                :class="
                    validationErrors.length > 0
                        ? 'border-rose-300 dark:border-rose-800'
                        : 'border-slate-200 dark:border-slate-800'
                "
            >
                <span
                    v-for="channel in configuredChannels"
                    :key="channel"
                    class="inline-flex max-w-full items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-100"
                    :title="destinations[channel]"
                    :data-testid="`cockpit-feedback-destination-${channel}`"
                >
                    <component
                        :is="channelIcon(channel)"
                        class="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <button
                        type="button"
                        class="min-w-0 truncate text-left focus-visible:rounded focus-visible:outline-2 focus-visible:outline-emerald-600"
                        :disabled="disabled"
                        :aria-label="`Edit ${channelPresentation[channel].label} destination ${destinations[channel]}`"
                        @click="editDestination(channel)"
                    >
                        {{ displayDestination(channel) }}
                    </button>
                    <button
                        type="button"
                        class="rounded p-0.5 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-950 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-emerald-300 dark:hover:bg-emerald-900 dark:hover:text-white"
                        :disabled="disabled"
                        :aria-label="`Remove ${channelPresentation[channel].label} destination`"
                        @click="removeDestination(channel)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>

                <span
                    v-for="value in invalidDestinations"
                    :key="value"
                    class="inline-flex max-w-full items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-800 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-200"
                    :title="value"
                    data-testid="cockpit-feedback-destination-invalid"
                >
                    <button
                        type="button"
                        class="min-w-0 truncate text-left focus-visible:rounded focus-visible:outline-2 focus-visible:outline-rose-600"
                        :disabled="disabled"
                        :aria-label="`Edit invalid destination ${value}`"
                        @click="editInvalidDestination(value)"
                    >
                        {{ value }}
                    </button>
                    <button
                        type="button"
                        class="rounded p-0.5 hover:bg-rose-100 focus-visible:outline-2 focus-visible:outline-rose-600 dark:hover:bg-rose-900"
                        :disabled="disabled"
                        :aria-label="`Remove invalid destination ${value}`"
                        @click="removeInvalidDestination(value)"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </button>
                </span>

                <input
                    ref="input"
                    v-model="draft"
                    type="text"
                    inputmode="text"
                    autocomplete="off"
                    class="min-w-0 flex-1 border-0 bg-transparent px-1 py-1 text-sm text-slate-950 outline-none dark:text-slate-50"
                    aria-label="Email, mobile, or webhook URL"
                    data-testid="cockpit-feedback-destination-editor"
                    :disabled="disabled"
                    @keydown="handleDelimiter"
                    @keydown.backspace="handleBackspace"
                    @blur="commitDraft"
                    @paste="handlePaste"
                />
            </div>

            <div
                v-if="suggestedChannels.length > 0"
                class="flex shrink-0 items-center gap-1"
                data-testid="cockpit-feedback-destination-suggestions"
            >
                <button
                    v-for="channel in suggestedChannels"
                    :key="channel"
                    type="button"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400 dark:hover:border-emerald-800 dark:hover:bg-emerald-950/50 dark:hover:text-emerald-200"
                    :disabled="
                        disabled ||
                        (unavailable[channel] !== undefined &&
                            unavailable[channel] !== '')
                    "
                    :title="
                        unavailable[channel] ||
                        channelPresentation[channel].suggestion
                    "
                    :aria-label="channelPresentation[channel].suggestion"
                    :data-testid="`cockpit-feedback-destination-suggestion-${channel}`"
                    @click="useDefault(channel)"
                >
                    <component
                        :is="channelIcon(channel)"
                        class="size-4"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>

        <p
            v-if="validationErrors.length > 0"
            class="text-[11px] font-medium text-rose-600 dark:text-rose-300"
            role="alert"
            data-testid="cockpit-feedback-destination-errors"
        >
            {{ validationErrors.join(' ') }}
        </p>
        <p class="sr-only" aria-live="polite">{{ statusMessage }}</p>
    </div>
</template>
