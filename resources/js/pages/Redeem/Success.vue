<!-- resources/js/Pages/Redeem/Success.vue -->
<script setup lang="ts">
import { useFormatCurrency } from '@/composables/useFormatCurrency';
import { useFormatDate } from '@/composables/useFormatDate';
import GuestLayout from '@/layouts/legacy/GuestLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { MessageData, Voucher } from '@/types';
import { computed, onMounted, ref } from 'vue';
import { Share } from 'lucide-vue-next';

const props = defineProps<{
    voucher: Voucher;
    from: string;
    to: string;
    message: MessageData;
    redirectTimeout?: number;
}>();

const formatCurrency = useFormatCurrency();
const { formatDate } = useFormatDate();
function shareToDevice() {
    if (navigator.share) {
        navigator.share({
            title: props.message.title,
            text: props.message.body,
            url: window.location.href,
        }).catch(err => {
            console.warn('[WebShare] Cancelled or failed:', err);
        });
    } else {
        alert('Sharing is not supported on this device.');
    }
}

const countdown = ref(0)

onMounted(() => {
    console.debug('[Success.vue] Mounted – redirectTimeout=', props.redirectTimeout);
    const timeout = props.redirectTimeout ?? 3000
    countdown.value = Math.ceil(timeout / 1000)

    const interval = setInterval(() => {
        countdown.value--
        if (countdown.value <= 0) clearInterval(interval)
    }, 1000)

    setTimeout(() => {
        router.visit(route('redeem.redirect', { voucher: props.voucher.code }))
    }, timeout)
})
const parsedTitle = computed(() => {
    const title = props.message.title.trim()

    if (title.includes(' - ')) {
        const [main, author] = title.split(' - ')
        return `${main.trim()}\n- ${author.trim()}`
    }

    if (title.includes(' by ')) {
        const [main, author] = title.split(' by ')
        return `${main.trim()}\nby ${author.trim()}`
    }

    return title
})

const parsed = computed(() => {
    const title = props.message.title.trim()
    const delimiters = [' - ', ' by ', ' ~ ', ' | ']

    for (const delimiter of delimiters) {
        if (title.includes(delimiter)) {
            const [main, author] = title.split(delimiter)
            return {
                main: main.trim(),
                author: author.trim(),
            }
        }
    }

    return {
        main: title,
        author: null,
    }
})
</script>

<template>
    <GuestLayout>
        <Head title="Voucher Redeemed" />
        <!-- Voucher Code: top-left, subtle -->
        <div class="absolute top-0 left-0 px-2 py-1 text-[10px] text-zinc-800 select-none dark:text-zinc-700">{{ voucher.code }} => {{ formatCurrency(voucher.cash.amount, {isMinor: true}) }}</div>

        <div class="bg-muted relative flex h-full flex-col p-10 text-white dark:border-r">
            <div class="absolute inset-0 bg-zinc-900" />
            <!-- Less prominent subject line -->
            <div class="relative z-20 flex items-center py-1 text-sm font-light text-gray-300">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="mr-2 h-6 w-6"
                >
                    <path d="M15 6v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3" />
                </svg>
                {{ message.subject }} for {{ to }}
            </div>
            <!-- Main content -->
            <div class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <!-- Centered Title + Author only -->
                    <div class="text-center space-y-1">
                        <!-- Title + Author aligned block -->
                        <div class="inline-block mx-auto text-left">
                            <!-- Title -->
                            <pre
                                class="rounded bg-black/10 p-2 font-mono text-base leading-snug whitespace-pre-line"
                                style="text-indent: 0; margin: 0"
                            >{{ parsed.main }}</pre>

                            <!-- Author -->
                            <div
                                v-if="parsed.author"
                                class="text-xs italic text-muted-foreground -mt-1 text-right"
                            >
                                — {{ parsed.author }}
                            </div>
                        </div>
                    </div>
                    <pre
                        class="max-h-96 max-w-full overflow-auto rounded bg-black/10 p-4 pl-0 font-mono text-sm leading-snug whitespace-pre"
                        style="text-indent: 0; margin: 0"
                        v-text="message.body"
                    />
                    <pre class="rounded bg-black/10 p-4 pl-0 font-mono text-sm leading-snug whitespace-pre" style="text-indent: 0; margin: 0">
— {{ from }}</pre
                    >
                </blockquote>
                <!-- Bottom-right: Done + Share -->
                <div class="absolute bottom-4 right-4 z-30 flex items-center space-x-6">
                    <!-- Countdown Timer -->
                    <span class="text-[0.65rem] text-white/70 font-mono">({{ countdown }}s)</span>

                    <!-- Share Button (Web Share API) -->
                    <button
                        @click="shareToDevice"
                        class="inline-flex items-center justify-center text-white hover:text-gray-300"
                        title="Share"
                    >
                        <Share class="w-4 h-4" />
                    </button>
                    <!-- Done Link -->
                    <TextLink
                        :href="route('redeem.redirect', { voucher: voucher.code })"
                        class="text-[0.7rem] text-white decoration-white hover:text-gray-300"
                    >
                        {{ message.closing }}
                    </TextLink>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>

<style scoped>
.font-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
