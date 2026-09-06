<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    BadgeCheck,
    BookOpen,
    CheckCircle2,
    CircleDollarSign,
    ClipboardCheck,
    FileStack,
    Landmark,
    ListChecks,
    Megaphone,
    QrCode,
    Radio,
    Route,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import CockpitLayout from '../layouts/CockpitLayout.vue';
import type { CockpitHeaderReadModel } from '../types';

defineOptions({ inheritAttrs: false });

type DocumentationLink = {
    label: string;
    description: string;
    href: string;
    external?: boolean;
    visible?: boolean;
};

type DocumentationHero = {
    eyebrow: string;
    title: string;
    description: string;
    primary_action: DocumentationLink;
    secondary_action: DocumentationLink;
};

type DocumentationCard = {
    key: string;
    title: string;
    description: string;
    href?: string;
};

type DocumentationPlaybook = DocumentationCard & {
    links: DocumentationLink[];
};

type DocumentationLifecycleState = {
    key: string;
    label: string;
    description: string;
};

defineProps<{
    cockpit_header_read_model?: CockpitHeaderReadModel;
    documentation: {
        schema: string;
        hero: DocumentationHero;
        start_here: DocumentationCard[];
        playbooks: DocumentationPlaybook[];
        lifecycle: DocumentationLifecycleState[];
        safety_notes: DocumentationCard[];
        builder_links: DocumentationLink[];
    };
}>();

const startHereIcons = [FileStack, Landmark, BadgeCheck];
const playbookIcons = [CircleDollarSign, Megaphone, ClipboardCheck];
const safetyIcons = [ShieldCheck, BadgeCheck, Radio, ListChecks];

function visibleLinks(links: DocumentationLink[]): DocumentationLink[] {
    return links.filter((link) => link.visible !== false);
}
</script>

<template>
    <Head title="Guides" />

    <CockpitLayout
        active-navigation="documentation"
        :cockpit-header-read-model="cockpit_header_read_model"
    >
        <section
            class="mx-auto max-w-7xl space-y-5"
            data-testid="cockpit-documentation"
        >
            <header
                class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="documentation-hero"
            >
                <div
                    class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)] lg:items-end"
                >
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-2xl bg-slate-950 text-white dark:bg-white dark:text-slate-950"
                            >
                                <BookOpen class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400"
                                >
                                    {{ documentation.hero.eyebrow }}
                                </p>
                                <h1
                                    class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-3xl"
                                >
                                    {{ documentation.hero.title }}
                                </h1>
                            </div>
                        </div>
                        <p
                            class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300 sm:text-base"
                        >
                            {{ documentation.hero.description }}
                        </p>
                    </div>

                    <div
                        class="grid gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2 lg:grid-cols-1"
                    >
                        <Link
                            :href="documentation.hero.primary_action.href"
                            class="group inline-flex items-center justify-between gap-3 rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        >
                            <span>{{ documentation.hero.primary_action.label }}</span>
                            <ArrowRight
                                class="size-4 transition group-hover:translate-x-0.5"
                            />
                        </Link>
                        <Link
                            :href="documentation.hero.secondary_action.href"
                            class="group inline-flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:border-slate-300 hover:text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:text-white"
                        >
                            <span>{{
                                documentation.hero.secondary_action.label
                            }}</span>
                            <ArrowRight
                                class="size-4 text-slate-400 transition group-hover:translate-x-0.5"
                            />
                        </Link>
                    </div>
                </div>
            </header>

            <section
                class="grid gap-3 md:grid-cols-3"
                aria-label="Start here"
                data-testid="documentation-start-here"
            >
                <Link
                    v-for="(item, index) in documentation.start_here"
                    :key="item.key"
                    :href="item.href ?? '#'"
                    class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                    data-testid="documentation-start-card"
                >
                    <component
                        :is="startHereIcons[index] ?? Sparkles"
                        class="size-5 text-slate-500 dark:text-slate-400"
                    />
                    <h2
                        class="mt-3 text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        {{ item.title }}
                    </h2>
                    <p
                        class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                    >
                        {{ item.description }}
                    </p>
                </Link>
            </section>

            <section
                class="grid gap-4 lg:grid-cols-3"
                aria-label="Operator playbooks"
                data-testid="documentation-playbooks"
            >
                <article
                    v-for="(playbook, index) in documentation.playbooks"
                    :key="playbook.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="documentation-playbook"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <component
                                :is="playbookIcons[index] ?? Route"
                                class="size-5"
                            />
                        </span>
                        <div class="min-w-0">
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                {{ playbook.title }}
                            </h2>
                            <p
                                class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{ playbook.description }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <template
                            v-for="link in visibleLinks(playbook.links)"
                            :key="link.href + link.label"
                        >
                            <a
                                v-if="link.external"
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
                                v-else
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
                                <ArrowRight
                                    class="mt-0.5 size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5"
                                />
                            </Link>
                        </template>
                    </div>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="documentation-lifecycle"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <QrCode class="size-5" />
                        </span>
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                Lifecycle
                            </p>
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Pay Code status language
                            </h2>
                        </div>
                    </div>

                    <ol class="mt-4 space-y-3">
                        <li
                            v-for="(state, index) in documentation.lifecycle"
                            :key="state.key"
                            class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-[auto_minmax(0,1fr)]"
                            data-testid="documentation-lifecycle-state"
                        >
                            <span
                                class="grid size-8 place-items-center rounded-full bg-white text-xs font-bold text-slate-600 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700"
                            >
                                {{ index + 1 }}
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-semibold text-slate-900 dark:text-white"
                                    >{{ state.label }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >{{ state.description }}</span
                                >
                            </span>
                        </li>
                    </ol>
                </article>

                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    data-testid="documentation-safety"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            <ShieldCheck class="size-5" />
                        </span>
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                            >
                                Beta safety
                            </p>
                            <h2
                                class="text-base font-semibold text-slate-950 dark:text-white"
                            >
                                Non-negotiables before testing
                            </h2>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <div
                            v-for="(note, index) in documentation.safety_notes"
                            :key="note.key"
                            class="flex gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/40"
                            data-testid="documentation-safety-note"
                        >
                            <component
                                :is="safetyIcons[index] ?? CheckCircle2"
                                class="mt-0.5 size-4 shrink-0 text-slate-500 dark:text-slate-400"
                            />
                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-semibold text-slate-900 dark:text-white"
                                    >{{ note.title }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                                    >{{ note.description }}</span
                                >
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <section
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                data-testid="documentation-builder-links"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
                        >
                            Builder reference
                        </p>
                        <h2
                            class="text-base font-semibold text-slate-950 dark:text-white"
                        >
                            Technical links for implementation partners
                        </h2>
                    </div>
                    <p class="max-w-xl text-xs leading-5 text-slate-500">
                        Kept deliberately below the operator playbook so beta
                        users start with workflows, not package internals.
                    </p>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <template
                        v-for="link in visibleLinks(documentation.builder_links)"
                        :key="link.href + link.label"
                    >
                        <a
                            v-if="link.external"
                            :href="link.href"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group rounded-2xl border border-slate-100 bg-slate-50 p-3 transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/40 dark:hover:border-slate-700"
                            data-testid="documentation-builder-link"
                        >
                            <span
                                class="flex items-center justify-between gap-3 text-sm font-semibold text-slate-800 group-hover:text-slate-950 dark:text-slate-200 dark:group-hover:text-white"
                            >
                                {{ link.label }}
                                <ArrowUpRight
                                    class="size-4 shrink-0 text-slate-400"
                                />
                            </span>
                            <span
                                class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >{{ link.description }}</span
                            >
                        </a>
                        <Link
                            v-else
                            :href="link.href"
                            class="group rounded-2xl border border-slate-100 bg-slate-50 p-3 transition hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950/40 dark:hover:border-slate-700"
                            data-testid="documentation-builder-link"
                        >
                            <span
                                class="flex items-center justify-between gap-3 text-sm font-semibold text-slate-800 group-hover:text-slate-950 dark:text-slate-200 dark:group-hover:text-white"
                            >
                                {{ link.label }}
                                <ArrowRight
                                    class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5"
                                />
                            </span>
                            <span
                                class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >{{ link.description }}</span
                            >
                        </Link>
                    </template>
                </div>
            </section>
        </section>
    </CockpitLayout>
</template>
