<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { store } from '@/routes/x-change/cockpit/display-sessions';
import type { DisplayCampaign, DisplaySession } from '../pairedDisplay';
import CockpitCampaignEndpointStamp from './CockpitCampaignEndpointStamp.vue';
import CockpitQuickGenerateSurfaceSwitch from './CockpitQuickGenerateSurfaceSwitch.vue';
import type { CockpitQuickGenerateSurface } from './CockpitQuickGenerateSurfaceSwitch.vue';

const props = withDefaults(
    defineProps<{
        campaigns?: DisplayCampaign[];
        session?: DisplaySession | null;
        issuanceSurface?: CockpitQuickGenerateSurface;
    }>(),
    { campaigns: () => [], session: null, issuanceSurface: 'qr' },
);
const emit = defineEmits<{
    'update:issuanceSurface': [value: CockpitQuickGenerateSurface];
    'update:session': [value: DisplaySession | null];
}>();

const campaignReference = ref(
    props.session?.campaign.reference ?? props.campaigns[0]?.reference ?? '',
);
const session = ref<DisplaySession | null>(props.session);
const busy = ref(false);
const error = ref<string | null>(null);
const display = ref<HTMLElement | null>(null);
const fullScreen = ref(false);
const now = ref(Date.now());
const selectedCampaign = computed(() =>
    props.campaigns.find(
        (campaign) => campaign.reference === campaignReference.value,
    ),
);
let timer: ReturnType<typeof setInterval> | undefined;
let inFlight = false;
let disposed = false;
let revision = 0;
let pollController: AbortController | null = null;

const expired = computed(() =>
    session.value?.expires_at
        ? Date.parse(session.value.expires_at) <= now.value
        : false,
);
const status = computed(() =>
    expired.value &&
    !['paid', 'completed', 'ended', 'completing'].includes(
        session.value?.status ?? '',
    )
        ? 'expired'
        : session.value?.status,
);
const terminal = computed(() =>
    ['paid', 'completed', 'expired', 'ended'].includes(status.value ?? ''),
);
const qrSource = computed(() => {
    if (status.value !== 'awaiting_payment' || error.value) return null;
    const qr = session.value?.attempt?.qr_code;
    return qr?.mime_type === 'image/png' && qr.base64_payload
        ? `data:image/png;base64,${qr.base64_payload}`
        : null;
});
const amount = computed(() =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: session.value?.attempt?.currency ?? 'PHP',
    }).format((session.value?.attempt?.amount_minor ?? 0) / 100),
);
const stamp = computed(() => ({
    ...session.value!.campaign,
    status: 'ready',
    usage_label: 'Endpoint',
    qr_data_uri: session.value!.entry_qr_data_uri,
    public_url: session.value!.public_url,
}));

function accept(value: DisplaySession): void {
    now.value = Date.now();
    session.value = value;
    emit('update:session', value);
    const url = new URL(window.location.href);
    url.searchParams.set('display_session', value.reference);
    url.searchParams.set('surface', 'qr');
    window.history.replaceState(window.history.state, '', url);
}

async function request(
    url: string,
    method = 'GET',
    body?: Record<string, string>,
    signal?: AbortSignal,
): Promise<DisplaySession> {
    const token = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    )?.content;
    const xsrfCookie = document.cookie
        .split(';')
        .map((cookie) => cookie.trim())
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const csrfHeaders: Record<string, string> = xsrfCookie
        ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie) }
        : token
          ? { 'X-CSRF-TOKEN': token }
          : {};
    const response = await fetch(url, {
        method,
        cache: 'no-store',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders,
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
        signal,
    });
    if (response.status === 419) {
        throw new Error(
            'Your session has expired. Reload this page before trying again.',
        );
    }
    if (!response.ok)
        throw new Error(
            'The display could not be updated. Check your connection and try again.',
        );
    const result = await response.json();
    if (!result.session?.reference || !result.session?.links)
        throw new Error('The server returned an incomplete display session.');
    return result.session;
}

async function mutate(action: 'start' | 'end' | 'reset'): Promise<void> {
    if (
        busy.value ||
        (action === 'start' &&
            (!campaignReference.value ||
                selectedCampaign.value?.available === false))
    )
        return;
    busy.value = true;
    error.value = null;
    revision++;
    pollController?.abort();
    const currentRevision = revision;
    try {
        const url =
            action === 'start' ? store.url() : session.value?.links[action];
        if (!url) throw new Error('Choose an endpoint campaign first.');
        const value = await request(
            url,
            'POST',
            action === 'start'
                ? { campaign_reference: campaignReference.value }
                : {},
        );
        if (!disposed && currentRevision === revision) accept(value);
    } catch (failure) {
        if (!disposed)
            error.value =
                failure instanceof Error
                    ? failure.message
                    : 'Unable to update display.';
    } finally {
        busy.value = false;
    }
}

async function refresh(): Promise<void> {
    now.value = Date.now();
    if (!session.value || terminal.value || busy.value || inFlight || disposed)
        return;
    inFlight = true;
    const currentRevision = revision;
    pollController = new AbortController();
    try {
        const value = await request(
            session.value.links.show,
            'GET',
            undefined,
            pollController.signal,
        );
        if (!disposed && currentRevision === revision) {
            error.value = null;
            accept(value);
        }
    } catch (failure) {
        if (!disposed && currentRevision === revision)
            error.value =
                failure instanceof Error
                    ? failure.message
                    : 'Connection interrupted.';
    } finally {
        inFlight = false;
    }
}

async function toggleFullScreen(): Promise<void> {
    try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else if (display.value?.requestFullscreen)
            await display.value.requestFullscreen();
        else fullScreen.value = !fullScreen.value;
    } catch {
        fullScreen.value = !fullScreen.value;
    }
}
function syncFullScreen(): void {
    fullScreen.value = Boolean(document.fullscreenElement);
}
function escapeFullScreen(event: KeyboardEvent): void {
    if (event.key === 'Escape') fullScreen.value = false;
}

onMounted(() => {
    timer = setInterval(() => {
        void refresh();
    }, 5000);
    document.addEventListener('fullscreenchange', syncFullScreen);
    document.addEventListener('keydown', escapeFullScreen);
    if (session.value) void refresh();
});
onBeforeUnmount(() => {
    disposed = true;
    pollController?.abort();
    clearInterval(timer);
    document.removeEventListener('fullscreenchange', syncFullScreen);
    document.removeEventListener('keydown', escapeFullScreen);
});
</script>

<template>
    <section
        ref="display"
        :class="[
            'space-y-4 rounded-2xl border border-slate-200 bg-white p-4 text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-white',
            fullScreen ? 'fixed inset-0 z-50 overflow-auto sm:p-8' : '',
        ]"
        data-testid="paired-display-panel"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <CockpitQuickGenerateSurfaceSwitch
                :model-value="issuanceSurface"
                :disabled="busy"
                @update:model-value="emit('update:issuanceSurface', $event)"
            />
            <button
                type="button"
                class="rounded-lg border px-3 py-2 text-sm"
                @click="toggleFullScreen"
            >
                {{ fullScreen ? 'Exit full screen' : 'Full screen' }}
            </button>
        </div>
        <div class="mx-auto grid max-w-3xl gap-4">
            <div>
                <h3 class="text-lg font-semibold">Paired campaign display</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    One customer at a time. Start on their phone, then pay by
                    scanning this screen.
                </p>
            </div>
            <div
                v-if="error"
                role="alert"
                class="rounded-lg border border-amber-400 bg-amber-50 p-4 text-sm text-amber-950"
            >
                {{ error }}
                <button
                    type="button"
                    class="font-semibold underline"
                    :disabled="busy"
                    @click="refresh"
                >
                    Retry status
                </button>
            </div>
            <form
                v-if="!session || status === 'ended'"
                class="grid gap-3"
                @submit.prevent="mutate('start')"
            >
                <label for="display-campaign" class="text-sm font-semibold"
                    >Endpoint campaign</label
                >
                <select
                    id="display-campaign"
                    v-model="campaignReference"
                    class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    :disabled="busy"
                >
                    <option value="" disabled>Choose a campaign</option>
                    <option
                        v-for="campaign in campaigns"
                        :key="campaign.reference"
                        :value="campaign.reference"
                    >
                        {{ campaign.title }}
                    </option>
                </select>
                <p v-if="campaigns.length === 0" class="text-sm">
                    No eligible endpoint campaigns are available.
                </p>
                <p v-if="selectedCampaign?.availability" class="text-sm">
                    {{ selectedCampaign.availability }}
                </p>
                <button
                    type="submit"
                    :disabled="
                        busy ||
                        !campaignReference ||
                        selectedCampaign?.available === false
                    "
                    class="min-h-11 rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white disabled:opacity-50"
                    data-testid="display-start"
                >
                    {{ busy ? 'Starting…' : 'Start display' }}
                </button>
            </form>
            <template v-if="session && status !== 'ended'">
                <div aria-live="polite" data-testid="display-status">
                    <template v-if="status === 'ready' && !error"
                        ><CockpitCampaignEndpointStamp :campaign="stamp" />
                        <p class="mt-3 text-center text-sm">
                            Scan to begin this campaign.
                        </p></template
                    >
                    <div
                        v-else-if="status === 'awaiting_payment'"
                        class="grid justify-items-center gap-4 rounded-2xl bg-slate-50 p-6 text-center dark:bg-slate-900"
                    >
                        <h4 class="text-xl font-semibold">
                            Scan with your bank or wallet app
                        </h4>
                        <p class="text-3xl font-bold">{{ amount }}</p>
                        <img
                            v-if="qrSource"
                            :src="qrSource"
                            alt="QR Ph payment code"
                            class="size-72 max-w-full rounded-xl bg-white p-4"
                            data-testid="display-payment-qr"
                        />
                        <p v-else>
                            Payment QR unavailable. Refresh the display status
                            before scanning.
                        </p>
                        <p class="text-sm">
                            Waiting for verified payment. Creating a QR does not
                            confirm payment.
                        </p>
                    </div>
                    <div
                        v-else
                        class="rounded-2xl bg-slate-50 p-8 text-center dark:bg-slate-900"
                    >
                        <h4 class="text-xl font-semibold">
                            {{
                                status === 'paid'
                                    ? 'Payment confirmed'
                                    : status === 'completing'
                                      ? 'Payment received — completion pending'
                                      : status === 'completed'
                                        ? 'Claim completed'
                                        : status === 'expired'
                                          ? 'Display expired'
                                          : status === 'review'
                                            ? 'Payment under review'
                                            : status === 'claimed'
                                              ? 'Customer connected'
                                              : 'Reconnecting display'
                            }}
                        </h4>
                        <p class="mt-2 text-sm">
                            {{
                                status === 'paid' || status === 'completed'
                                    ? 'Ready for the next customer.'
                                    : status === 'completing'
                                      ? 'The bank payment is verified. Do not pay again. We are completing the collection record.'
                                      : status === 'expired'
                                        ? 'Start a fresh display before inviting another customer.'
                                        : status === 'review'
                                          ? 'Do not pay again. Payment confirmation requires review.'
                                          : 'Continue on your phone. Your payment QR will appear here.'
                            }}
                        </p>
                    </div>
                </div>
                <p
                    v-if="session.pay_code"
                    class="text-center text-xs text-slate-500"
                >
                    Pay Code {{ session.pay_code }}
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <button
                        type="button"
                        class="min-h-11 rounded-lg border px-4 py-2 text-sm"
                        :disabled="busy"
                        data-testid="display-end"
                        @click="mutate('end')"
                    >
                        End display
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="busy"
                        data-testid="display-reset"
                        @click="mutate('reset')"
                    >
                        {{
                            status === 'paid' || status === 'completed'
                                ? 'Next customer'
                                : 'Reset display'
                        }}
                    </button>
                </div>
                <p class="text-center text-xs text-slate-500">
                    Reset ends this pairing. It does not cancel a payment
                    already in progress.
                </p>
            </template>
        </div>
    </section>
</template>
