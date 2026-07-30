<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { login, register } from '@/routes';
import { dashboard } from '@/routes/x-change/cockpit';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="x-change" />

    <main
        class="min-h-screen bg-slate-950 px-5 py-6 text-slate-100 sm:px-8 lg:px-12"
    >
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-6xl flex-col">
            <header class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <img
                        src="/vendor/x-change/images/logo.png"
                        alt=""
                        class="h-9 w-9 rounded-xl"
                    />
                    <div>
                        <p class="text-sm font-semibold tracking-tight">
                            x-change
                        </p>
                        <p
                            class="text-[0.65rem] font-medium tracking-[0.18em] text-slate-400 uppercase"
                        >
                            Settlement Operating System
                        </p>
                    </div>
                </div>

                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200"
                    >
                        Open Cockpit
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"
                        >
                            Sign In
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-slate-200"
                        >
                            Create Account
                        </Link>
                    </template>
                </nav>
            </header>

            <section
                class="grid flex-1 items-center gap-12 py-16 lg:grid-cols-[minmax(0,1.1fr)_minmax(20rem,0.9fr)]"
            >
                <div class="max-w-2xl">
                    <p
                        class="mb-5 text-xs font-semibold tracking-[0.2em] text-amber-300 uppercase"
                    >
                        Money must adapt to people
                    </p>
                    <h1
                        class="text-4xl leading-tight font-semibold tracking-[-0.04em] text-balance sm:text-6xl"
                    >
                        Move value with instructions, evidence, and control.
                    </h1>
                    <p
                        class="mt-6 max-w-xl text-base leading-7 text-slate-300 sm:text-lg"
                    >
                        Fund an Account, issue a Pay Code, or authorize a
                        campaign from one operating Cockpit.
                    </p>

                    <div class="mt-9 flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="rounded-xl bg-amber-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-200"
                        >
                            Continue to Cockpit
                        </Link>
                        <template v-else>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="rounded-xl bg-amber-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-200"
                            >
                                Start with an Account
                            </Link>
                            <Link
                                :href="login()"
                                class="rounded-xl border border-white/15 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10"
                            >
                                I already have an Account
                            </Link>
                        </template>
                    </div>
                </div>

                <div
                    class="rounded-[2rem] border border-white/10 bg-white/[0.06] p-5 shadow-2xl shadow-black/30 backdrop-blur"
                >
                    <div
                        class="rounded-[1.4rem] border border-white/10 bg-slate-900 p-6"
                    >
                        <div class="mb-8 flex items-center justify-between">
                            <div>
                                <p
                                    class="text-[0.65rem] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                                >
                                    Account
                                </p>
                                <p class="mt-1 text-lg font-semibold">
                                    Ready when you are
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300"
                            >
                                Provider verified
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                            <div
                                class="rounded-2xl border border-white/10 bg-white/[0.04] p-4"
                            >
                                <p class="text-xs text-slate-400">1 · Fund</p>
                                <p class="mt-1 text-sm font-medium">
                                    Add verified Client Funds
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-white/10 bg-white/[0.04] p-4"
                            >
                                <p class="text-xs text-slate-400">2 · Issue</p>
                                <p class="mt-1 text-sm font-medium">
                                    Design a Pay Code
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-white/10 bg-white/[0.04] p-4"
                            >
                                <p class="text-xs text-slate-400">3 · Settle</p>
                                <p class="mt-1 text-sm font-medium">
                                    Follow the evidence
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
