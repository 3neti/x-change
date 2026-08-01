<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, BookOpen, ChevronRight } from 'lucide-vue-next';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderReadModel } from '../types';

defineOptions({ inheritAttrs: false });

type DocumentationLink = {
    label: string;
    description: string;
    href: string;
    external?: boolean;
};

type DocumentationSection = {
    key: string;
    title: string;
    description: string;
    links: DocumentationLink[];
};

defineProps<{
    cockpit_header_read_model?: CockpitHeaderReadModel;
    documentation: {
        schema: string;
        sections: DocumentationSection[];
    };
}>();
</script>

<template>
    <Head title="Documentation" />

    <CockpitLayout
        active-navigation="documentation"
        :cockpit-header-read-model="cockpit_header_read_model"
    >
        <section
            class="mx-auto max-w-6xl space-y-5"
            data-testid="cockpit-documentation"
        >
            <header
                class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-slate-950 text-white dark:bg-white dark:text-slate-950"
                    >
                        <BookOpen class="size-5" />
                    </span>
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            Reference
                        </p>
                        <h1
                            class="text-xl font-semibold text-slate-950 dark:text-white"
                        >
                            Documentation
                        </h1>
                    </div>
                </div>
            </header>

            <div class="grid gap-4 lg:grid-cols-3">
                <section
                    v-for="section in documentation.sections"
                    :key="section.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="documentation-section"
                >
                    <h2
                        class="text-base font-semibold text-slate-950 dark:text-white"
                    >
                        {{ section.title }}
                    </h2>
                    <p
                        class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400"
                    >
                        {{ section.description }}
                    </p>

                    <div
                        class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <a
                            v-for="link in section.links.filter(
                                (item) => item.external,
                            )"
                            :key="link.href"
                            :href="link.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-semibold text-slate-800 group-hover:text-slate-950 dark:text-slate-200 dark:group-hover:text-white"
                                    >{{ link.label }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs leading-4 text-slate-500 dark:text-slate-400"
                                    >{{ link.description }}</span
                                >
                            </span>
                            <ArrowUpRight
                                class="mt-0.5 size-4 shrink-0 text-slate-400"
                            />
                        </a>
                        <Link
                            v-for="link in section.links.filter(
                                (item) => !item.external,
                            )"
                            :key="link.href"
                            :href="link.href"
                            class="group flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-semibold text-slate-800 group-hover:text-slate-950 dark:text-slate-200 dark:group-hover:text-white"
                                    >{{ link.label }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs leading-4 text-slate-500 dark:text-slate-400"
                                    >{{ link.description }}</span
                                >
                            </span>
                            <ChevronRight
                                class="mt-0.5 size-4 shrink-0 text-slate-400"
                            />
                        </Link>
                    </div>
                </section>
            </div>
        </section>
    </CockpitLayout>
</template>
