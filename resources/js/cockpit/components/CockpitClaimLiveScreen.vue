<script setup lang="ts">
import {
    Camera,
    Check,
    CheckCircle2,
    ExternalLink,
    KeyRound,
    LoaderCircle,
    LocateFixed,
    MapPin,
    PenTool,
    ShieldCheck,
    Smartphone,
    UserRound,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, type Component } from 'vue';
import PayCodeLogo from '../../components/x-change/PayCodeLogo.vue';

type ClaimPreviewField = {
    key: string;
    label: string;
    value: string;
};

type ClaimPreviewScreen = {
    kind: string;
    code?: string;
    amount?: string;
    title?: string;
    description?: string;
    fields?: ClaimPreviewField[];
    message?: string | null;
    artwork_url?: string | null;
    handoff?: {
        title?: string | null;
        description?: string | null;
        public_image_url?: string | null;
    } | null;
    component?: string | null;
    props?: Record<string, unknown> | null;
};

const props = defineProps<{
    screen: ClaimPreviewScreen;
    presentation?: 'framed' | 'viewport';
}>();

const isValidation = computed(() =>
    props.screen.kind.startsWith('validation_'),
);
const validationKind = computed(() =>
    props.screen.kind.replace('validation_', ''),
);
const handoffTitle = computed(
    () => props.screen.handoff?.title || props.screen.title,
);
const handoffDescription = computed(
    () => props.screen.handoff?.description || props.screen.description,
);
const formFlowPages = import.meta.glob(
    '/resources/js/pages/form-flow/**/*.vue',
);
const actualScreenComponent = computed<Component | null>(() => {
    if (!props.screen.component) return null;

    const loader =
        formFlowPages[`/resources/js/pages/${props.screen.component}.vue`];

    return loader ? defineAsyncComponent(loader) : null;
});
</script>

<template>
    <div
        class="bg-background text-foreground flex h-full min-h-0 flex-col overflow-hidden"
        :class="
            presentation === 'viewport'
                ? 'w-full'
                : 'aspect-[1/2] max-h-full w-auto max-w-full rounded-[1.15rem] border-[5px] border-slate-700 shadow-2xl'
        "
        data-testid="cockpit-claim-live-screen"
        :data-screen-kind="screen.kind"
        :aria-label="screen.title"
    >
        <div
            v-if="actualScreenComponent"
            class="bg-background h-full min-h-0 w-full overflow-y-auto"
            data-testid="cockpit-claim-actual-screen"
        >
            <component
                :is="actualScreenComponent"
                v-bind="screen.props ?? {}"
            />
        </div>
        <template v-else-if="screen.kind === 'claim_entry'">
            <div
                class="from-primary/5 via-background to-background min-h-0 flex-1 overflow-y-auto bg-gradient-to-b px-5 py-8"
            >
                <div
                    class="mx-auto flex min-h-full w-full max-w-md flex-col justify-center"
                >
                    <section
                        class="border-border/60 bg-card/85 grid w-full gap-6 rounded-lg border p-6 shadow-sm backdrop-blur-sm"
                    >
                        <div
                            class="grid justify-items-center gap-2 text-center"
                        >
                            <PayCodeLogo
                                variant="logo"
                                class-name="!h-24 !max-h-24 !max-w-24"
                            />
                            <h3 class="text-xl font-medium">Claim Pay Code</h3>
                        </div>
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Pay Code</label>
                            <div
                                class="bg-background grid min-h-10 place-items-center rounded-md border px-3 text-center text-lg font-medium tracking-wider"
                            >
                                {{ screen.code }}
                            </div>
                        </div>
                        <button
                            type="button"
                            tabindex="-1"
                            class="bg-primary text-primary-foreground min-h-10 rounded-full px-4 text-sm font-medium"
                        >
                            Continue
                        </button>
                    </section>
                </div>
            </div>
        </template>

        <template v-else-if="screen.kind === 'rider_splash'">
            <div class="relative flex min-h-0 flex-1 flex-col bg-slate-950">
                <img
                    v-if="screen.artwork_url"
                    :src="screen.artwork_url"
                    alt="Rider introduction artwork"
                    class="absolute inset-0 size-full object-cover"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black via-black/45 to-black/15"
                />
                <div
                    class="relative mt-auto grid gap-3 px-5 pb-6 pt-20 text-white"
                >
                    <p class="text-xl font-semibold tracking-tight">
                        {{ screen.message || screen.title }}
                    </p>
                    <p class="text-xs leading-5 text-white/75">
                        {{ screen.description }}
                    </p>
                    <button
                        type="button"
                        tabindex="-1"
                        class="min-h-10 rounded-full bg-white px-4 text-xs font-bold text-slate-950"
                    >
                        Continue
                    </button>
                </div>
            </div>
        </template>

        <template v-else-if="screen.kind === 'claim_details'">
            <div
                class="from-primary/5 via-background to-background flex min-h-0 flex-1 flex-col bg-gradient-to-b px-4 py-5"
            >
                <div class="grid gap-1">
                    <h3 class="text-lg font-semibold">{{ screen.title }}</h3>
                    <p class="text-muted-foreground text-[11px] leading-4">
                        {{ screen.description }}
                    </p>
                </div>
                <div class="bg-muted mt-4 rounded-lg px-3 py-2.5 text-center">
                    <p class="text-muted-foreground text-[10px]">Pay Code</p>
                    <p class="text-xl font-bold">{{ screen.amount }}</p>
                </div>
                <div class="mt-4 grid min-h-0 flex-1 gap-2 overflow-y-auto">
                    <label
                        v-for="field in screen.fields"
                        :key="field.key"
                        class="grid gap-1"
                    >
                        <span class="text-[10px] font-medium">
                            {{ field.label }}
                        </span>
                        <span
                            class="bg-background text-muted-foreground flex min-h-9 items-center rounded-md border px-3 text-[11px]"
                        >
                            {{ field.value }}
                        </span>
                    </label>
                </div>
                <button
                    type="button"
                    tabindex="-1"
                    class="bg-primary text-primary-foreground mt-3 min-h-10 rounded-full px-4 text-xs font-medium"
                >
                    Continue
                </button>
            </div>
        </template>

        <template v-else-if="screen.kind === 'slice_selection'">
            <div class="flex min-h-0 flex-1 flex-col px-5 py-6">
                <h3 class="text-lg font-semibold">{{ screen.title }}</h3>
                <p class="text-muted-foreground mt-1 text-xs leading-5">
                    {{ screen.description }}
                </p>
                <div class="mt-5 grid gap-2">
                    <div
                        v-for="index in 3"
                        :key="index"
                        class="flex items-center gap-3 rounded-xl border p-3"
                    >
                        <span
                            class="bg-primary text-primary-foreground grid size-5 place-items-center rounded border"
                        >
                            <Check class="size-3" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-semibold">
                                Portion {{ index }}
                            </span>
                            <span
                                class="text-muted-foreground block text-[10px]"
                            >
                                Available to claim
                            </span>
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    tabindex="-1"
                    class="bg-primary text-primary-foreground mt-auto min-h-10 rounded-full px-4 text-xs font-medium"
                >
                    Continue
                </button>
            </div>
        </template>

        <template v-else-if="isValidation">
            <div
                class="from-primary/5 via-background to-background flex min-h-0 flex-1 flex-col bg-gradient-to-b px-5 py-6"
            >
                <div class="grid gap-1">
                    <div class="flex items-center gap-2">
                        <MapPin
                            v-if="validationKind === 'location'"
                            class="size-5"
                        />
                        <Camera
                            v-else-if="validationKind === 'selfie'"
                            class="size-5"
                        />
                        <PenTool
                            v-else-if="validationKind === 'signature'"
                            class="size-5"
                        />
                        <Smartphone
                            v-else-if="validationKind === 'otp'"
                            class="size-5"
                        />
                        <ShieldCheck
                            v-else-if="validationKind === 'kyc'"
                            class="size-5"
                        />
                        <KeyRound v-else class="size-5" />
                        <h3 class="text-lg font-semibold">
                            {{ screen.title }}
                        </h3>
                    </div>
                    <p class="text-muted-foreground text-[11px] leading-4">
                        {{ screen.description }}
                    </p>
                </div>

                <div
                    v-if="validationKind === 'location'"
                    class="relative mt-5 grid min-h-0 flex-1 place-items-center overflow-hidden rounded-xl border bg-[linear-gradient(90deg,transparent_24%,hsl(var(--border))_25%,hsl(var(--border))_26%,transparent_27%,transparent_74%,hsl(var(--border))_75%,hsl(var(--border))_76%,transparent_77%),linear-gradient(0deg,transparent_24%,hsl(var(--border))_25%,hsl(var(--border))_26%,transparent_27%,transparent_74%,hsl(var(--border))_75%,hsl(var(--border))_76%,transparent_77%)] bg-[length:48px_48px]"
                >
                    <span
                        class="bg-primary text-primary-foreground grid size-12 place-items-center rounded-full shadow-lg"
                    >
                        <LocateFixed class="size-6" />
                    </span>
                </div>

                <div
                    v-else-if="validationKind === 'selfie'"
                    class="relative mt-5 grid min-h-0 flex-1 place-items-center overflow-hidden rounded-xl border bg-slate-950"
                >
                    <UserRound class="size-24 text-white/20" />
                    <div
                        class="absolute h-40 w-28 rounded-[50%] border-2 border-dashed border-white/60"
                    />
                    <p class="absolute bottom-4 text-[10px] text-white/70">
                        Position your face in the oval guide
                    </p>
                </div>

                <div
                    v-else-if="validationKind === 'signature'"
                    class="relative mt-5 min-h-0 flex-1 rounded-xl border bg-white"
                >
                    <svg
                        viewBox="0 0 240 120"
                        class="absolute inset-0 size-full text-slate-700"
                        aria-hidden="true"
                    >
                        <path
                            d="M30 82c28-52 22 25 49-10 18-23 4 35 30 1 17-22 18 27 42 2 14-14 30 9 57-13"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                        />
                    </svg>
                    <span
                        class="absolute inset-x-4 bottom-5 border-b border-slate-300"
                    />
                </div>

                <div
                    v-else-if="validationKind === 'otp'"
                    class="mt-6 grid gap-4"
                >
                    <div class="grid gap-1">
                        <span class="text-[10px] font-medium"
                            >Mobile Number</span
                        >
                        <span
                            class="bg-muted rounded-md border px-3 py-2 text-xs"
                        >
                            09••• ••• •••
                        </span>
                    </div>
                    <div class="grid grid-cols-6 gap-1.5">
                        <span
                            v-for="index in 6"
                            :key="index"
                            class="bg-background grid aspect-square place-items-center rounded-md border text-sm font-bold"
                        >
                            •
                        </span>
                    </div>
                </div>

                <div
                    v-else-if="validationKind === 'kyc'"
                    class="grid min-h-0 flex-1 place-items-center py-8 text-center"
                >
                    <div class="grid justify-items-center gap-3">
                        <LoaderCircle
                            class="text-primary size-12 animate-spin"
                        />
                        <p class="text-sm font-semibold">
                            Verifying your identity
                        </p>
                        <p
                            class="text-muted-foreground max-w-48 text-[10px] leading-4"
                        >
                            Keep this page open while the identity provider
                            processes the result.
                        </p>
                    </div>
                </div>

                <div v-else class="mt-6 grid gap-2">
                    <label class="text-[10px] font-medium">
                        Verification value
                    </label>
                    <div class="bg-background min-h-10 rounded-md border" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        tabindex="-1"
                        class="bg-background min-h-10 rounded-full border px-3 text-xs font-medium"
                    >
                        Back
                    </button>
                    <button
                        type="button"
                        tabindex="-1"
                        class="bg-primary text-primary-foreground min-h-10 rounded-full px-3 text-xs font-medium"
                    >
                        Continue
                    </button>
                </div>
            </div>
        </template>

        <template v-else-if="screen.kind === 'confirmation'">
            <div
                class="from-primary/5 via-background to-background flex min-h-0 flex-1 flex-col bg-gradient-to-b px-5 py-6"
            >
                <CheckCircle2 class="text-primary mx-auto size-11" />
                <div class="mt-3 text-center">
                    <h3 class="text-lg font-semibold">{{ screen.title }}</h3>
                    <p class="text-muted-foreground mt-1 text-[11px]">
                        {{ screen.description }}
                    </p>
                </div>
                <div class="bg-muted mt-4 rounded-lg p-3 text-center">
                    <p class="text-muted-foreground text-[10px]">Amount</p>
                    <p class="text-2xl font-bold">{{ screen.amount }}</p>
                    <span
                        class="mt-1 inline-flex rounded-full border px-2 py-0.5 text-[9px] font-semibold"
                    >
                        {{ screen.code }}
                    </span>
                </div>
                <dl class="mt-4 grid gap-2">
                    <div
                        v-for="field in screen.fields"
                        :key="field.key"
                        class="flex justify-between gap-3 text-[10px]"
                    >
                        <dt class="text-muted-foreground">{{ field.label }}</dt>
                        <dd class="font-medium">{{ field.value }}</dd>
                    </div>
                </dl>
                <button
                    type="button"
                    tabindex="-1"
                    class="bg-primary text-primary-foreground mt-auto min-h-10 rounded-full px-4 text-xs font-medium"
                >
                    Confirm &amp; Claim
                </button>
            </div>
        </template>

        <template v-else-if="screen.kind === 'success'">
            <div
                class="via-background to-background flex min-h-0 flex-1 flex-col justify-center gap-5 bg-gradient-to-b from-emerald-500/10 px-5 py-6 text-center"
            >
                <CheckCircle2 class="mx-auto size-14 text-emerald-500" />
                <div class="grid gap-2">
                    <p class="text-2xl font-bold">{{ screen.amount }}</p>
                    <h3 class="text-lg font-semibold">{{ screen.title }}</h3>
                    <p class="text-muted-foreground text-[11px] leading-4">
                        {{ screen.description }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-amber-500/25 bg-amber-500/10 p-3 text-left"
                >
                    <p class="text-[11px] font-semibold">
                        Claim accepted · payout pending
                    </p>
                    <p class="text-muted-foreground mt-1 text-[10px] leading-4">
                        Funds are released after the payment provider confirms
                        the transfer.
                    </p>
                </div>
                <p
                    v-if="screen.message"
                    class="bg-card rounded-xl border p-3 text-xs font-medium"
                >
                    {{ screen.message }}
                </p>
            </div>
        </template>

        <template
            v-else-if="
                screen.kind === 'redirect_countdown' ||
                screen.kind === 'rider_handoff'
            "
        >
            <div
                class="relative flex min-h-0 flex-1 flex-col bg-slate-950 text-white"
            >
                <img
                    v-if="screen.artwork_url"
                    :src="screen.artwork_url"
                    alt="Rider link artwork"
                    class="absolute inset-0 size-full object-cover opacity-60"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black via-black/45 to-black/10"
                />
                <div class="relative mt-auto grid gap-3 px-5 pb-6 pt-20">
                    <ExternalLink class="size-7 text-cyan-300" />
                    <h3 class="text-xl font-semibold tracking-tight">
                        {{ handoffTitle }}
                    </h3>
                    <p class="text-xs leading-5 text-white/70">
                        {{ handoffDescription }}
                    </p>
                    <div
                        v-if="screen.kind === 'redirect_countdown'"
                        class="rounded-full bg-white/10 px-3 py-2 text-center text-xs"
                    >
                        Opening in 5 seconds…
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="grid min-h-0 flex-1 place-items-center p-6 text-center">
                <div class="grid justify-items-center gap-3">
                    <ShieldCheck class="text-primary size-12" />
                    <h3 class="text-lg font-semibold">{{ screen.title }}</h3>
                    <p class="text-muted-foreground text-xs leading-5">
                        {{ screen.description }}
                    </p>
                </div>
            </div>
        </template>
    </div>
</template>
