<!-- resources/js/pages/Disburse.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import { useFlashEventWatcher } from '@/composables/useFlashEventWatcher';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose,
    DialogOverlay,
} from '@/components/ui/dialog'

import {
    Collapsible, CollapsibleTrigger, CollapsibleContent
} from '@/components/ui/collapsible'

import { computed, nextTick, ref, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'
import CurrencyDropdown from '@/components/domain/CurrencyDropdown.vue'
import CountryDropdown from '@/components/domain/CountryDropdown.vue'
import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';
import { useWalletBalance } from '@/composables/useWalletBalance'

const breadcrumbs: BreadcrumbItemType[] = [{ title: 'Disburse', href: '/disburse' }];

const props = defineProps<{
    data: {
        cash: {
            amount: number;
            currency: string;
            validation: {
                secret: string;
                mobile: string;
                country: string;
                location: string;
                radius: string;
            };
        };
        inputs: {
            fields: string[];
        };
        feedback: {
            mobile: string;
            email: string;
            webhook: string;
        };
        rider: {
            message: string;
            url: string;
        };
        count: number;
        prefix: string;
        mask: string;
        ttl: string;
    };
}>();

const form = useForm({
    amount: props.data.cash?.amount ?? 1500,
    currency: props.data.cash?.currency ?? 'PHP',

    secret: props.data.cash?.validation?.secret ?? '',
    mobile: props.data.cash?.validation?.mobile ?? '',
    country: props.data.cash?.validation?.country ?? 'PH',
    location: props.data.cash?.validation?.location ?? '',
    radius: props.data.cash?.validation?.radius ?? '',

    email: props.data.feedback?.email ?? '',
    webhook: props.data.feedback?.webhook ?? '',

    message: props.data.rider?.message ?? '',
    url: props.data.rider?.url ?? '',

    count: props.data.count ?? 1,
    prefix: props.data.prefix ?? '',
    mask: props.data.mask ?? '****',

    starts_at: '',
    expires_at: '',
    payeeMode: 'prefix', // or 'mobile'
});

function submit() {
    form.post(route('disburse.store'), {
        onSuccess: () => {
            // fetchBalance(); // 👈 refresh balance after success
        },
    });
}

const showDialog = ref(false)
const voucherCodes = ref<string[]>([])
const voucherInput = ref<HTMLInputElement | null>(null)

const formattedVoucherCodes = computed(() => voucherCodes.value.join(', '));

useFlashEventWatcher<{ vouchers: string[] }>('vouchers_generated', (data) => {
    voucherCodes.value = data.vouchers ?? [];
    showDialog.value = true;
});

watch(showDialog, async (open) => {
    if (open) {
        await nextTick()
        voucherInput.value?.focus()
        voucherInput.value?.select()
    }
})

const displayPrefix = computed({
    get() {
        return form.prefix === props.data.prefix ? 'CASH' : form.prefix
    },
    set(value: string) {
        form.prefix = value === 'CASH' ? props.data.prefix : value
    }
})

watch(() => form.prefix, (newPrefix) => {
    if (newPrefix && form.mobile) {
        form.mobile = ''
    }
})

watch(() => form.mobile, (newMobile) => {
    if (newMobile && form.prefix) {
        form.prefix = ''
    }
})

const prefixInput = ref<HTMLInputElement | null>(null)
const mobileInput = ref<HTMLInputElement | null>(null)

watch(() => form.payeeMode, (mode) => {
    nextTick(() => {
        if (mode === 'prefix') prefixInput.value?.focus()
        else if (mode === 'mobile') mobileInput.value?.focus()
    })
})

const showSecret = ref(false)
const activeTab = ref<'basic' | 'advanced'>('basic')
const { formattedBalance, status } = useWalletBalance()

</script>

<template>
    <Head title="Create Voucher" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-1 rounded bg-white p-6 shadow">
            <h1 class="text-2xl font-bold text-gray-700">Cash Code Generation</h1>
            <p class="text-sm text-gray-500 mt-1">Escrow Fund Transfer</p>

            <div class="mb-4 flex border-b">
                <button
                    @click="activeTab = 'basic'"
                    :class="[
            'px-4 py-2 font-semibold',
            activeTab === 'basic' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'
        ]"
                >
                    Basic
                </button>
                <button
                    @click="activeTab = 'advanced'"
                    :class="[
            'px-4 py-2 font-semibold',
            activeTab === 'advanced' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'
        ]"
                >
                    Advanced
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- BASIC TAB CONTENT -->
                <div v-if="activeTab === 'basic'" class="space-y-6">
                    <!-- CASH -->
                    <Collapsible :defaultOpen="true" class="rounded border border-gray-300">
                        <CollapsibleTrigger as="legend" class="px-4 py-2 text-sm font-semibold text-gray-600 w-full text-left">
                            Cash
                        </CollapsibleTrigger>
                        <CollapsibleContent class="p-4 border-t border-gray-300">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2 col-span-1">
                                    <Label>Amount</Label>
                                    <Input type="number" v-model="form.amount" autofocus />
                                    <InputError :message="form.errors.amount" />
                                </div>
                                <div class="space-y-2 col-span-1">
                                    <Label>Quantity</Label>
                                    <Input type="number" v-model="form.count" />
                                    <InputError :message="form.errors.count" />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- PAYEE -->
                    <Collapsible :defaultOpen="false" class="rounded border border-gray-300">
                        <CollapsibleTrigger as="legend" class="px-4 py-2 text-sm font-semibold text-gray-600 w-full text-left">
                            Payee
                        </CollapsibleTrigger>
                        <CollapsibleContent class="p-4 border-t border-gray-300">
                            <div class="mb-4 flex gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="radio" value="mobile" v-model="form.payeeMode" />
                                    <span>Mobile</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" value="prefix" v-model="form.payeeMode" />
                                    <span>Handle</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <!-- Prefix + Secret -->
                                <template v-if="form.payeeMode === 'prefix'">
                                    <div class="space-y-2 col-span-1">
                                        <Label>Handle</Label>
                                        <Input
                                            type="text"
                                            v-model="displayPrefix"
                                            ref="prefixInput"
                                            @focus="(e: FocusEvent) => (e.target as HTMLInputElement).select()"
                                        />
                                        <InputError :message="form.errors.prefix" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Secret</Label>
                                        <div class="relative">
                                            <Input
                                                :type="showSecret ? 'text' : 'password'"
                                                v-model="form.secret"
                                                class="pr-16"
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                @click="showSecret = !showSecret"
                                                class="absolute right-2 top-1/2 -translate-y-1/2"
                                            >
                                                {{ showSecret ? 'Hide' : 'Show' }}
                                            </Button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Mobile + Secret -->
                                <template v-if="form.payeeMode === 'mobile'">
                                    <div class="space-y-2 col-span-1">
                                        <Label>Mobile</Label>
                                        <Input
                                            type="text"
                                            v-model="form.mobile"
                                            ref="mobileInput"
                                            @focus="(e: FocusEvent) => (e.target as HTMLInputElement).select()"
                                        />
                                        <InputError :message="form.errors.mobile" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Secret</Label>
                                        <div class="relative">
                                            <Input
                                                :type="showSecret ? 'text' : 'password'"
                                                v-model="form.secret"
                                                class="pr-16"
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                @click="showSecret = !showSecret"
                                                class="absolute right-2 top-1/2 -translate-y-1/2"
                                            >
                                                {{ showSecret ? 'Hide' : 'Show' }}
                                            </Button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Message -->
                                <div class="col-span-2 space-y-2">
                                    <Label>Message</Label>
                                    <Input
                                        type="text"
                                        v-model="form.message"
                                        class="w-full"
                                    />
                                    <InputError :message="form.errors.message" />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <!-- ADVANCED TAB CONTENT -->
                <div v-if="activeTab === 'advanced'" class="space-y-6">
                    <!-- FEEDBACK -->
                    <Collapsible :defaultOpen="true" class="rounded border border-gray-300">
                        <CollapsibleTrigger class="w-full rounded border border-gray-300 bg-gray-50 px-4 py-2 text-left font-semibold text-gray-600">
                            Feedback
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <fieldset class="rounded border border-gray-300 p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label>Email Address</Label>
                                        <Input type="email" v-model="form.email" class="w-full" />
                                        <InputError :message="form.errors.email" />
                                    </div>
                                    <!-- Empty column for spacing if needed -->
                                    <div></div>
                                    <div class="col-span-2 space-y-2">
                                        <Label>Webhook URL</Label>
                                        <Input type="url" v-model="form.webhook" class="w-full" />
                                        <InputError :message="form.errors.webhook" />
                                    </div>
                                </div>
                            </fieldset>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- RIDER -->
                    <Collapsible :defaultOpen="true" class="rounded border border-gray-300">
                        <CollapsibleTrigger class="w-full rounded border border-gray-300 bg-gray-50 px-4 py-2 text-left font-semibold text-gray-600">
                            Rider
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <fieldset class="rounded border border-gray-300 p-4">
                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <Label>Landing Page URL</Label>
                                        <Input type="url" v-model="form.url" class="w-full" />
                                        <InputError :message="form.errors.url" />
                                    </div>
                                </div>
                            </fieldset>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- VALIDATION -->
                    <Collapsible :defaultOpen="false" class="rounded border border-gray-300">
                        <CollapsibleTrigger class="w-full rounded border border-gray-300 bg-gray-50 px-4 py-2 text-left font-semibold text-gray-600">
                            Validation
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <fieldset class="rounded border border-gray-300 p-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label>Location</Label>
                                        <Input type="text" v-model="form.location" />
                                        <InputError :message="form.errors.location" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Radius</Label>
                                        <Input type="text" v-model="form.radius" />
                                        <InputError :message="form.errors.radius" />
                                    </div>
                                </div>
                            </fieldset>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- OPTIONS -->
                    <Collapsible :defaultOpen="false" class="rounded border border-gray-300">
                        <CollapsibleTrigger class="w-full rounded border border-gray-300 bg-gray-50 px-4 py-2 text-left font-semibold text-gray-600">
                            Options
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <fieldset class="rounded border border-gray-300 p-4">
                                <div class="mt-4 grid grid-cols-2 gap-4">
                                    <div class="space-y-2 col-span-1">
                                        <Label>Starts At</Label>
                                        <Input type="datetime-local" v-model="form.starts_at" />
                                        <InputError :message="form.errors.starts_at" />
                                    </div>
                                    <div class="space-y-2 col-span-1">
                                        <Label>Expires At</Label>
                                        <Input type="datetime-local" v-model="form.expires_at" />
                                        <InputError :message="form.errors.expires_at" />
                                    </div>
                                    <div class="space-y-2 col-span-1">
                                        <Label class="mb-1 block font-medium">Mask</Label>
                                        <Input type="text" v-model="form.mask"/>
                                        <InputError :message="form.errors.mask" />
                                    </div>
                                </div>
                            </fieldset>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <!-- FORM ACTION BUTTONS WITH BALANCE -->
                <div class="flex items-center justify-between pt-4">
                    <!-- Left: Buttons -->
                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                        >
                            Generate
                        </button>

                        <button
                            v-if="voucherCodes.length"
                            type="button"
                            class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700"
                            @click="showDialog = true"
                        >
                            Show
                        </button>
                    </div>

                    <!-- Right: Balance -->
                    <div class="text-sm text-gray-600 italic">
                        Current Balance:
                        <span class="font-semibold text-gray-800">{{ formattedBalance }}</span>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>

    <Dialog v-model:open="showDialog">
        <DialogOverlay />

        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Cash Codes Generated</DialogTitle>
                <DialogDescription class="text-sm text-gray-500">
                    Copy and share the cash codes below:
                </DialogDescription>
            </DialogHeader>

            <input
                type="text"
                readonly
                :value="formattedVoucherCodes"
                class="w-full border rounded px-3 py-2"
                ref="voucherInput"
            />

            <DialogFooter class="mt-4">
                <DialogClose class="px-4 py-2 text-white bg-blue-600 rounded hover:bg-blue-700">
                    Close
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
