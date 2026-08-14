<script setup lang="ts">
import CockpitRiderLibraryForgetController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitRiderLibraryForgetController';
import CockpitRiderLibraryPinController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitRiderLibraryPinController';
import CockpitRiderLibraryStoreController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitRiderLibraryStoreController';
import { router } from '@inertiajs/vue3';
import {
    Bookmark,
    BookOpen,
    Link2,
    Pin,
    PinOff,
    Search,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import type { CockpitRiderLibraryEntry } from '../types';

const props = withDefaults(
    defineProps<{
        kind: 'url' | 'splash';
        entries?: CockpitRiderLibraryEntry[];
        currentPayload: Record<string, unknown>;
        disabled?: boolean;
    }>(),
    {
        entries: () => [],
        disabled: false,
    },
);

const emit = defineEmits<{
    apply: [payload: Record<string, unknown>];
}>();

const open = ref(false);
const saving = ref(false);
const showSaveForm = ref(false);
const label = ref('');
const search = ref('');
const status = ref('');
const searchInput = ref<HTMLInputElement | null>(null);

const title = computed(() =>
    props.kind === 'url' ? 'Rider Link Library' : 'Rider Splash Library',
);
const matchingEntries = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.entries.filter((entry) => {
        if (entry.kind !== props.kind) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return `${entry.label} ${entrySummary(entry)}`
            .toLocaleLowerCase()
            .includes(query);
    });
});
const savedEntries = computed(() =>
    matchingEntries.value.filter((entry) => entry.saved),
);
const recentEntries = computed(() =>
    matchingEntries.value.filter((entry) => !entry.saved),
);
const currentValueAvailable = computed(() => {
    if (props.kind === 'url') {
        return stringValue(props.currentPayload.url) !== '';
    }

    return stringValue(props.currentPayload.splash) !== '';
});

watch(open, async (visible) => {
    if (!visible) {
        return;
    }

    await nextTick();
    searchInput.value?.focus();
});

function openLibrary(): void {
    status.value = '';
    showSaveForm.value = false;
    open.value = true;
}

function openSave(): void {
    if (!currentValueAvailable.value || props.disabled) {
        return;
    }

    status.value = '';
    showSaveForm.value = true;
    open.value = true;
}

function closeLibrary(): void {
    open.value = false;
    showSaveForm.value = false;
    label.value = '';
    search.value = '';
    status.value = '';
}

function saveCurrent(): void {
    if (!currentValueAvailable.value || saving.value) {
        return;
    }

    saving.value = true;
    status.value = '';

    router.post(
        CockpitRiderLibraryStoreController(),
        {
            kind: props.kind,
            label: label.value.trim() || null,
            payload: props.currentPayload,
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['rider_library'],
            onSuccess: () => {
                showSaveForm.value = false;
                label.value = '';
                status.value = 'Saved to your Rider Library.';
            },
            onError: (errors: Record<string, string>) => {
                status.value =
                    String(Object.values(errors)[0] ?? '') ||
                    'This Rider could not be saved.';
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function useEntry(entry: CockpitRiderLibraryEntry): void {
    emit('apply', { ...entry.payload });
    closeLibrary();
}

function togglePin(entry: CockpitRiderLibraryEntry): void {
    router.patch(
        CockpitRiderLibraryPinController(entry.reference),
        { pinned: !entry.pinned },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['rider_library'],
        },
    );
}

function forgetEntry(entry: CockpitRiderLibraryEntry): void {
    router.delete(CockpitRiderLibraryForgetController(entry.reference), {
        preserveScroll: true,
        preserveState: true,
        only: ['rider_library'],
    });
}

function entrySummary(entry: CockpitRiderLibraryEntry): string {
    if (entry.kind === 'url') {
        return stringValue(entry.payload.url);
    }

    return stringValue(entry.payload.splash)
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}
</script>

<template>
    <div
        class="flex min-w-0 flex-wrap items-center gap-1.5"
        data-testid="cockpit-rider-library-actions"
    >
        <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
            :disabled="disabled"
            data-testid="cockpit-rider-library-open"
            @click="openLibrary"
        >
            <BookOpen class="size-3.5" aria-hidden="true" />
            Library
            <span
                v-if="entries.filter((entry) => entry.kind === kind).length > 0"
                class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] tabular-nums dark:bg-slate-800"
            >
                {{ entries.filter((entry) => entry.kind === kind).length }}
            </span>
        </button>
        <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
            :disabled="disabled || !currentValueAvailable"
            data-testid="cockpit-rider-library-save-open"
            @click="openSave"
        >
            <Bookmark class="size-3.5" aria-hidden="true" />
            Save
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[90] flex items-end justify-center bg-slate-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-4"
                data-testid="cockpit-rider-library-overlay"
                @click.self="closeLibrary"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    tabindex="-1"
                    class="grid max-h-[88dvh] w-full min-w-0 gap-3 overflow-hidden rounded-t-3xl border border-slate-200 bg-white p-4 shadow-2xl sm:max-w-2xl sm:rounded-3xl dark:border-slate-800 dark:bg-slate-950"
                    data-testid="cockpit-rider-library-dialog"
                    @keydown.esc.stop="closeLibrary"
                >
                    <header
                        class="flex min-w-0 items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-sm font-semibold text-slate-950 dark:text-white"
                            >
                                {{ title }}
                            </p>
                            <p
                                class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Saved choices and Riders used in successful Pay
                                Codes.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:text-slate-900 dark:border-slate-800 dark:text-slate-400 dark:hover:text-white"
                            aria-label="Close Rider Library"
                            @click="closeLibrary"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </header>

                    <div class="relative min-w-0">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                            aria-hidden="true"
                        />
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="search"
                            class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-950 shadow-sm focus:border-emerald-500 focus:outline-none dark:border-slate-800 dark:bg-slate-900 dark:text-white"
                            placeholder="Search your Library"
                            data-testid="cockpit-rider-library-search"
                        />
                    </div>

                    <div
                        v-if="showSaveForm"
                        class="grid gap-2 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/20"
                        data-testid="cockpit-rider-library-save-form"
                    >
                        <label
                            class="grid gap-1 text-xs font-medium text-emerald-950 dark:text-emerald-100"
                        >
                            Name
                            <span
                                class="font-normal text-emerald-700 dark:text-emerald-300"
                                >(optional)</span
                            >
                            <input
                                v-model="label"
                                type="text"
                                maxlength="80"
                                class="h-9 min-w-0 rounded-xl border border-emerald-200 bg-white px-3 text-sm text-slate-950 focus:border-emerald-500 focus:outline-none dark:border-emerald-900/60 dark:bg-slate-900 dark:text-white"
                                data-testid="cockpit-rider-library-label"
                            />
                        </label>
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="h-9 rounded-xl px-3 text-xs font-semibold text-slate-600 dark:text-slate-300"
                                @click="showSaveForm = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="h-9 rounded-xl bg-emerald-600 px-3 text-xs font-semibold text-white shadow-sm disabled:opacity-60"
                                :disabled="saving"
                                data-testid="cockpit-rider-library-save"
                                @click="saveCurrent"
                            >
                                {{ saving ? 'Saving…' : 'Save to Library' }}
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="status !== ''"
                        role="status"
                        aria-live="polite"
                        class="text-xs text-slate-600 dark:text-slate-300"
                    >
                        {{ status }}
                    </p>

                    <div class="min-h-0 overflow-y-auto pr-1">
                        <div
                            v-if="
                                savedEntries.length === 0 &&
                                recentEntries.length === 0
                            "
                            class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center dark:border-slate-700"
                            data-testid="cockpit-rider-library-empty"
                        >
                            <component
                                :is="kind === 'url' ? Link2 : Sparkles"
                                class="mx-auto size-5 text-slate-400"
                                aria-hidden="true"
                            />
                            <p
                                class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200"
                            >
                                Nothing here yet
                            </p>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Save the current Rider or issue a Pay Code to
                                remember it.
                            </p>
                        </div>

                        <div v-if="savedEntries.length > 0" class="grid gap-2">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            >
                                Saved
                            </p>
                            <article
                                v-for="entry in savedEntries"
                                :key="entry.reference"
                                class="grid min-w-0 gap-2 rounded-2xl border border-slate-200 p-3 dark:border-slate-800"
                                data-testid="cockpit-rider-library-entry"
                            >
                                <div class="flex min-w-0 items-start gap-2">
                                    <component
                                        :is="kind === 'url' ? Link2 : Sparkles"
                                        class="mt-0.5 size-4 shrink-0 text-emerald-600"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ entry.label }}
                                        </p>
                                        <p
                                            class="mt-0.5 line-clamp-2 break-words text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ entrySummary(entry) }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="entry.pinned"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200"
                                        >Pinned</span
                                    >
                                </div>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="h-8 rounded-lg px-2.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-900"
                                        :aria-label="
                                            entry.pinned
                                                ? `Unpin ${entry.label}`
                                                : `Pin ${entry.label}`
                                        "
                                        @click="togglePin(entry)"
                                    >
                                        <component
                                            :is="entry.pinned ? PinOff : Pin"
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-700 dark:text-slate-400 dark:hover:bg-rose-950/30 dark:hover:text-rose-200"
                                        @click="forgetEntry(entry)"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Forget
                                    </button>
                                    <button
                                        type="button"
                                        class="h-8 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white dark:bg-white dark:text-slate-950"
                                        data-testid="cockpit-rider-library-use"
                                        @click="useEntry(entry)"
                                    >
                                        Use
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div
                            v-if="recentEntries.length > 0"
                            class="mt-4 grid gap-2"
                        >
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                            >
                                Recently Used
                            </p>
                            <article
                                v-for="entry in recentEntries"
                                :key="entry.reference"
                                class="grid min-w-0 gap-2 rounded-2xl border border-slate-200 p-3 dark:border-slate-800"
                                data-testid="cockpit-rider-library-entry"
                            >
                                <div class="flex min-w-0 items-start gap-2">
                                    <component
                                        :is="kind === 'url' ? Link2 : Sparkles"
                                        class="mt-0.5 size-4 shrink-0 text-slate-400"
                                        aria-hidden="true"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ entry.label }}
                                        </p>
                                        <p
                                            class="mt-0.5 line-clamp-2 break-words text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ entrySummary(entry) }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-[10px] tabular-nums text-slate-400"
                                        >{{ entry.use_count }}×</span
                                    >
                                </div>
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-slate-500 hover:bg-amber-50 hover:text-amber-700 dark:text-slate-400 dark:hover:bg-amber-950/30 dark:hover:text-amber-200"
                                        @click="togglePin(entry)"
                                    >
                                        <Pin
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Save
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-lg px-2.5 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-700 dark:text-slate-400 dark:hover:bg-rose-950/30 dark:hover:text-rose-200"
                                        @click="forgetEntry(entry)"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Forget
                                    </button>
                                    <button
                                        type="button"
                                        class="h-8 rounded-lg bg-slate-950 px-3 text-xs font-semibold text-white dark:bg-white dark:text-slate-950"
                                        data-testid="cockpit-rider-library-use"
                                        @click="useEntry(entry)"
                                    >
                                        Use
                                    </button>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>
