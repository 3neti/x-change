<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { login } from '@/routes';
import { start as startClaim } from '@/routes/x-change/claim';
import { dashboard } from '@/routes/x-change/cockpit';
import CockpitLandingClaimExperiencePresentation from '@/cockpit/components/CockpitLandingClaimExperiencePresentation.vue';
import CockpitQuickGenerateOrderPresentation from '@/cockpit/components/CockpitQuickGenerateOrderPresentation.vue';
import XChangeLogo from '@/components/x-change/XChangeLogo.vue';
import { gClefPulleyBrandAssets } from '@/components/x-change/brandAssets';

const page = usePage<{
    xchange: {
        version: string;
    };
}>();

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
    <Head title="x-change">
        <meta
            name="description"
            content="Receive a Pay Code. Send to the account you choose. Claim when you’re ready with a participating bank or supported wallet."
        />
    </Head>

    <main
        class="relative isolate min-h-screen overflow-x-hidden bg-[#f7f5f2] text-slate-950 lg:grid lg:grid-rows-[6rem_minmax(0,1fr)_auto]"
    >
        <div
            aria-hidden="true"
            class="bg-top-left pointer-events-none absolute inset-y-0 left-0 h-screen w-full bg-contain bg-no-repeat opacity-[0.1] lg:bg-[length:auto_100%] lg:bg-left"
            :style="{
                backgroundImage: `url('${gClefPulleyBrandAssets.logo}')`,
            }"
        />

        <header
            class="relative z-10 mx-auto flex h-24 w-full max-w-[88rem] items-center justify-between gap-4 px-5 sm:px-8 lg:px-10"
        >
            <div class="flex items-center gap-3">
                <XChangeLogo class-name="h-12 shrink-0 sm:h-14" />
                <span class="shrink-0 border-l border-slate-300 pl-3">
                    <span
                        class="block max-w-28 truncate text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-slate-700 sm:max-w-44 sm:text-xs"
                    >
                        {{ $page.props.name }}
                    </span>
                    <span
                        class="mt-1 block whitespace-nowrap text-[0.5rem] font-semibold uppercase tracking-[0.12em] text-slate-500 sm:text-[0.58rem]"
                    >
                        Powered by x-change
                    </span>
                </span>
            </div>

            <nav aria-label="Account" class="flex items-center gap-1 sm:gap-2">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="whitespace-nowrap rounded-full px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-white hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-950 sm:px-4"
                >
                    Open Cockpit
                </Link>
                <Link
                    v-else
                    :href="login()"
                    class="whitespace-nowrap rounded-full px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-white hover:text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-950 sm:px-4"
                >
                    Sign in
                </Link>
            </nav>
        </header>

        <section
            class="relative z-10 mx-auto grid w-full max-w-[88rem] gap-10 px-5 py-8 sm:px-8 sm:py-10 lg:min-h-0 lg:grid-cols-[minmax(22rem,0.76fr)_minmax(38rem,1.24fr)] lg:items-center lg:gap-10 lg:px-10 lg:py-5"
        >
            <div class="max-w-xl">
                <p
                    class="text-xs font-semibold uppercase tracking-[0.2em] text-[#d85f15]"
                >
                    Cashless disbursements
                </p>
                <h1
                    class="mt-5 text-balance text-[clamp(3rem,5.2vw,5.4rem)] font-semibold leading-[0.94] tracking-[-0.065em]"
                >
                    Money should adapt to people.
                    <span class="text-slate-400"
                        >Not the other way around.</span
                    >
                </h1>
                <p
                    class="mt-5 max-w-lg text-base leading-7 text-slate-600 sm:text-lg"
                >
                    <span class="block font-semibold text-slate-800">
                        Receive a Pay Code. Send to the account you choose.
                    </span>
                    <span class="mt-2 block">
                        Claim when you’re ready—with a participating bank or
                        supported wallet.
                    </span>
                </p>

                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <Link
                        :href="startClaim()"
                        class="rounded-full bg-[#ef6a1a] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#d85f15] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ef6a1a]"
                    >
                        Claim Pay Code
                    </Link>
                </div>
            </div>

            <div class="min-w-0">
                <ol
                    aria-label="Pay Code journey"
                    class="mb-4 grid grid-cols-3 gap-2 rounded-2xl border border-slate-200 bg-white/65 p-2 shadow-sm"
                >
                    <li
                        class="flex items-center gap-2 rounded-xl bg-white px-3 py-2"
                    >
                        <span
                            class="grid size-6 shrink-0 place-items-center rounded-full bg-slate-950 text-[0.65rem] font-bold text-white"
                        >
                            1
                        </span>
                        <span class="leading-tight">
                            <strong
                                class="block text-[0.58rem] font-extrabold tracking-[0.14em] text-slate-950 sm:text-[0.66rem]"
                            >
                                DRAFT
                            </strong>
                            <span
                                class="mt-0.5 block text-[0.62rem] font-semibold text-slate-600 sm:text-xs"
                            >
                                the instruction
                            </span>
                        </span>
                    </li>
                    <li class="flex items-center gap-2 rounded-xl px-3 py-2">
                        <span
                            class="grid size-6 shrink-0 place-items-center rounded-full bg-[#ef6a1a] text-[0.65rem] font-bold text-white"
                        >
                            2
                        </span>
                        <span class="leading-tight">
                            <strong
                                class="block text-[0.58rem] font-extrabold tracking-[0.14em] text-[#d85f15] sm:text-[0.66rem]"
                            >
                                ISSUE
                            </strong>
                            <span
                                class="mt-0.5 block text-[0.62rem] font-semibold text-slate-600 sm:text-xs"
                            >
                                the Pay Code
                            </span>
                        </span>
                    </li>
                    <li class="flex items-center gap-2 rounded-xl px-3 py-2">
                        <span
                            class="grid size-6 shrink-0 place-items-center rounded-full bg-emerald-600 text-[0.65rem] font-bold text-white"
                        >
                            3
                        </span>
                        <span class="leading-tight">
                            <strong
                                class="block text-[0.58rem] font-extrabold tracking-[0.14em] text-emerald-700 sm:text-[0.66rem]"
                            >
                                CLAIM
                            </strong>
                            <span
                                class="mt-0.5 block text-[0.62rem] font-semibold text-slate-600 sm:text-xs"
                            >
                                the payout
                            </span>
                        </span>
                    </li>
                </ol>

                <div
                    class="grid items-center gap-4 xl:grid-cols-[minmax(20rem,1fr)_17rem]"
                >
                    <CockpitQuickGenerateOrderPresentation
                        amount="₱537.00"
                        recipient="Lester Hurtado · 0917 301 1987"
                        pay-code-type="Redeemable"
                        estimated-cost="₱543.90"
                        purpose="Field allowance"
                    />

                    <div
                        class="mx-auto h-[32rem] w-full max-w-[19rem] overflow-hidden rounded-[1.2rem] shadow-[0_28px_65px_-28px_rgba(15,23,42,0.75)] xl:-ml-5"
                    >
                        <CockpitLandingClaimExperiencePresentation />
                    </div>
                </div>
            </div>
        </section>

        <footer
            class="relative z-10 mx-auto flex w-full max-w-[88rem] flex-col items-end gap-1 px-5 pb-5 text-right text-[0.65rem] font-semibold uppercase tracking-[0.08em] text-slate-500 sm:flex-row sm:items-center sm:justify-end sm:gap-2 sm:px-8 lg:px-10 lg:pb-4"
        >
            <span>3neti/x-change {{ page.props.xchange.version }}</span>
            <span aria-hidden="true" class="hidden text-slate-300 sm:inline"
                >·</span
            >
            <span>© 2026 3neti R&amp;D OPC</span>
        </footer>
    </main>
</template>
