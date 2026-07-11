<script setup lang="ts">
import type { CockpitVoucherEvidenceItem } from '../types';

defineProps<{
    items: CockpitVoucherEvidenceItem[];
    heading?: string;
}>();
</script>

<template>
    <section
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        data-testid="cockpit-voucher-evidence-panel"
    >
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
            Evidence
        </p>
        <h3 class="mt-2 text-lg font-semibold text-slate-950 dark:text-slate-50">
            {{ heading ?? 'Evidence tab placeholder' }}
        </h3>

        <div class="mt-5 grid gap-3">
            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"
                data-testid="cockpit-voucher-evidence-item"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950 dark:text-slate-50">
                        {{ item.label }}
                    </p>
                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ item.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ item.helper }}
                </p>
                <dl
                    v-if="item.source || item.read_only !== undefined"
                    class="mt-3 grid gap-2 text-xs text-slate-500 dark:text-slate-400 sm:grid-cols-2"
                >
                    <div v-if="item.source">
                        <dt class="font-semibold uppercase tracking-wide">
                            Source
                        </dt>
                        <dd>{{ item.source }}</dd>
                    </div>
                    <div v-if="item.read_only !== undefined">
                        <dt class="font-semibold uppercase tracking-wide">
                            Read-only
                        </dt>
                        <dd>{{ item.read_only ? 'yes' : 'no' }}</dd>
                    </div>
                </dl>
            </article>
        </div>
    </section>
</template>
