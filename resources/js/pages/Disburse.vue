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
} from '@/components/ui/dialog';

import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';

import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import CurrencyDropdown from '@/components/domain/CurrencyDropdown.vue';
import CountryDropdown from '@/components/domain/CountryDropdown.vue';
import WalletBalanceDisplay from '@/components/domain/WalletBalanceDisplay.vue';
import { useWalletBalance } from '@/composables/useWalletBalance';
import { useLabelFormatter } from '@/composables/useLabelFormatter';
import { useCleanForm } from '@/composables/useCleanForm'

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
    availableInputs: string;
    labelMap?: Record<string, string>;
}>();

const form = useForm({
    cash: {
        amount: props.data.cash?.amount ?? 0.0,
        currency: props.data.cash?.currency ?? 'PHP',
        validation: {
            secret: props.data.cash?.validation?.secret ?? '',
            mobile: props.data.cash?.validation?.mobile ?? '',
            country: props.data.cash?.validation?.country ?? '',
            location: props.data.cash?.validation?.location ?? '',
            radius: props.data.cash?.validation?.radius ?? '',
        },
    },
    inputs: {
        fields: props.data.inputs?.fields ?? [],
    },
    feedback: {
        mobile: props.data.feedback?.mobile ?? '',
        email: props.data.feedback?.email ?? '',
        webhook: props.data.feedback?.webhook ?? '',
    },
    rider: {
        message: props.data.rider?.message ?? '',
        url: props.data.rider?.url ?? '',
    },
    count: props.data.count ?? 1,
    prefix: props.data.prefix ?? '',
    mask: props.data.mask ?? '****',
    ttl: props.data.ttl ?? '',
    starts_at: '',
    expires_at: '',
    payeeMode: 'prefix',
});

function submit() {
    form.post(route('disburse.store'), {
        onSuccess: () => {
            // form.reset();
        },
    });
}

const showDialog = ref(false);
const confirmedInstructions = ref<any | null>(null)

const voucherCodes = ref<string[]>([]);
const voucherInput = ref<HTMLInputElement | null>(null);

const formattedVoucherCodes = computed(() => voucherCodes.value.join(', '));

useFlashEventWatcher<{ vouchers: string[] }>('vouchers_generated', (data) => {
    voucherCodes.value = data.vouchers ?? [];
    showDialog.value = true;
});

watch(showDialog, async (open) => {
    if (open) {
        await nextTick();
        voucherInput.value?.focus();
        voucherInput.value?.select();
    }
});

const displayPrefix = computed({
    get() {
        return form.prefix === props.data.prefix ? 'CASH' : form.prefix;
    },
    set(value: string) {
        form.prefix = value === 'CASH' ? props.data.prefix : value;
    },
});

watch(
    () => form.prefix,
    (newPrefix) => {
        if (newPrefix && form.cash.validation.mobile) {
            form.cash.validation.mobile = '';
        }
    },
);

watch(
    () => form.cash.validation.mobile,
    (newMobile) => {
        if (newMobile && form.prefix) {
            form.prefix = '';
        }
    },
);

const prefixInput = ref<HTMLInputElement | null>(null);
const mobileInput = ref<HTMLInputElement | null>(null);

watch(
    () => form.payeeMode,
    (mode) => {
        nextTick(() => {
            if (mode === 'prefix') prefixInput.value?.focus();
            else if (mode === 'mobile') mobileInput.value?.focus();
        });
    },
);

const showSecret = ref(false);
const activeTab = ref<'basic' | 'advanced'>('basic');
const { formattedBalance, status } = useWalletBalance();
function toggleInputField(input: string) {
    const index = form.inputs.fields.indexOf(input);
    if (index > -1) {
        form.inputs.fields.splice(index, 1); // remove if already selected
    } else {
        form.inputs.fields.push(input); // add if not selected
    }
}
const { formatLabel } = useLabelFormatter(props.labelMap ?? {});

function resetForm() {
    form.cash = {
        amount: 0.0,
        currency: 'PHP',
        validation: {
            secret: '',
            mobile: '',
            country: '',
            location: '',
            radius: '',
        },
    };
    form.inputs = {
        fields: [],
    };
    form.feedback = {
        mobile: '',
        email: '',
        webhook: '',
    };
    form.rider = {
        message: '',
        url: '',
    };
    form.count = 1;
    form.prefix = '';
    form.mask = '****';
    form.ttl = '';
    form.starts_at = '';
    form.expires_at = '';
    form.payeeMode = 'prefix';
}

const excluded = ['cash.currency', 'cash.validation.country', 'payeeMode']
const { clean } = useCleanForm()

const showConfirmation = ref(false)

function confirmAndSubmit() {
    showConfirmation.value = true
}

function submitConfirmed() {
    confirmedInstructions.value = clean(form, [], excluded)

    form.post(route('disburse.store'), {
        onSuccess: () => {
            showDialog.value = true
            showConfirmation.value = false
        },
    })
}

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Voucher codes copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy voucher codes.');
    });
}

onMounted(() => {
    const mobile = form?.cash?.validation?.mobile ?? ''
    if (mobile.trim() !== '') {
        form.payeeMode = 'mobile'
    }
})
</script>

<template>
    <Head title="Create Voucher" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-1 rounded bg-white p-6 shadow">
            <h1 class="text-2xl font-bold text-gray-700">Cash Code Generation</h1>
            <p class="mt-1 text-sm text-gray-500">Escrow Fund Transfer</p>

            <div class="mb-4 flex border-b">
                <button
                    @click="activeTab = 'basic'"
                    :class="['px-4 py-2 font-semibold', activeTab === 'basic' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500']"
                >
                    Basic
                </button>
                <button
                    @click="activeTab = 'advanced'"
                    :class="['px-4 py-2 font-semibold', activeTab === 'advanced' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500']"
                >
                    Advanced
                </button>
            </div>

<!--            <form @submit.prevent="submit" class="space-y-6">-->
            <form @submit.prevent="confirmAndSubmit" class="space-y-6">
                <!-- BASIC TAB CONTENT -->
                <div v-if="activeTab === 'basic'" class="space-y-6">
                    <!-- CASH -->
                    <Collapsible :defaultOpen="true" class="rounded border border-gray-300">
                        <CollapsibleTrigger as="legend" class="w-full px-4 py-2 text-left text-sm font-semibold text-gray-600">
                            Cash
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t border-gray-300 p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-1 space-y-2">
                                    <Label>Amount</Label>
                                    <Input type="number" v-model="form.cash.amount" autofocus />
                                    <InputError :message="form.errors['cash.amount']" />
                                </div>
                                <div class="col-span-1 space-y-2">
                                    <Label>Quantity</Label>
                                    <Input type="number" v-model="form.count" />
                                    <InputError :message="form.errors.count" />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- PAYEE -->
                    <Collapsible :defaultOpen="true" class="rounded border border-gray-300">
                        <CollapsibleTrigger as="legend" class="w-full px-4 py-2 text-left text-sm font-semibold text-gray-600">
                            Payee
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t border-gray-300 p-4">
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
                                    <div class="col-span-1 space-y-2">
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
                                            <Input :type="showSecret ? 'text' : 'password'" v-model="form.cash.validation.secret" class="pr-16" />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                @click="showSecret = !showSecret"
                                                class="absolute top-1/2 right-2 -translate-y-1/2"
                                            >
                                                {{ showSecret ? 'Hide' : 'Show' }}
                                            </Button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Mobile + Secret -->
                                <template v-if="form.payeeMode === 'mobile'">
                                    <div class="col-span-1 space-y-2">
                                        <Label>Mobile</Label>
                                        <Input
                                            type="text"
                                            v-model="form.cash.validation.mobile"
                                            ref="mobileInput"
                                            @focus="(e: FocusEvent) => (e.target as HTMLInputElement).select()"
                                        />
                                        <InputError :message="form.errors['cash.validation.mobile']" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Secret</Label>
                                        <div class="relative">
                                            <Input :type="showSecret ? 'text' : 'password'" v-model="form.cash.validation.secret" class="pr-16" />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                @click="showSecret = !showSecret"
                                                class="absolute top-1/2 right-2 -translate-y-1/2"
                                            >
                                                {{ showSecret ? 'Hide' : 'Show' }}
                                            </Button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Message -->
                                <div class="col-span-2 space-y-2">
                                    <Label>Message</Label>
                                    <Input type="text" v-model="form.rider.message" class="w-full" />
                                    <InputError :message="form.errors['rider.message']" />
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
                                        <Label>Mobile Number</Label>
                                        <Input type="text" v-model="form.feedback.mobile" autocomplete="mobile" placeholder="e.g., 09171234567" />
                                        <InputError :message="form.errors['feedback.mobile']" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Email Address</Label>
                                        <Input type="email" v-model="form.feedback.email" autocomplete="username" />
                                        <InputError :message="form.errors['feedback.email']" />
                                    </div>
                                    <div class="col-span-2 space-y-2">
                                        <Label>Webhook URL</Label>
                                        <Input type="url" v-model="form.feedback.webhook" class="w-full" />
                                        <InputError :message="form.errors['feedback.webhook']" />
                                    </div>
                                </div>
                            </fieldset>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- INPUT FIELDS -->
                    <Collapsible :defaultOpen="true" class="rounded border border-gray-300">
                        <CollapsibleTrigger class="w-full rounded border border-gray-300 bg-gray-50 px-4 py-2 text-left font-semibold text-gray-600">
                            Input Fields
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <fieldset class="rounded border border-gray-300 p-4">
                                <div class="space-y-2">
                                    <Label class="mb-1 block font-medium">Select Required Inputs</Label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label
                                            v-for="input in props.availableInputs.split(',').map(i => i.trim())"
                                            :key="input"
                                            class="flex items-center space-x-1 bg-gray-50 px-2 py-1 rounded-md"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="form.inputs.fields.includes(input)"
                                                @change="toggleInputField(input)"
                                                class="form-checkbox text-blue-600 rounded-sm"
                                            />
                                            <span class="text-xs text-gray-700 whitespace-nowrap">{{ formatLabel(input) }}</span>
                                        </label>
                                    </div>
                                    <InputError :message="form.errors['inputs.fields']" />
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
                                        <Input type="url" v-model="form.rider.url" class="w-full" />
                                        <InputError :message="form.errors['rider.url']" />
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
                                        <Input type="text" v-model="form.cash.validation.location" />
                                        <InputError :message="form.errors['cash.validation.location']" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Radius</Label>
                                        <Input type="text" v-model="form.cash.validation.radius" />
                                        <InputError :message="form.errors['cash.validation.radius']" />
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
                                    <div class="col-span-1 space-y-2">
                                        <Label>Starts At</Label>
                                        <Input type="datetime-local" v-model="form.starts_at" />
                                        <InputError :message="form.errors.starts_at" />
                                    </div>
                                    <div class="col-span-1 space-y-2">
                                        <Label>Expires At</Label>
                                        <Input type="datetime-local" v-model="form.expires_at" />
                                        <InputError :message="form.errors.expires_at" />
                                    </div>
                                    <div class="col-span-1 space-y-2">
                                        <Label>Mask</Label>
                                        <Input type="text" v-model="form.mask" placeholder="e.g. ****"/>
                                        <InputError :message="form.errors.mask" />
                                    </div>
                                    <div class="col-span-1 space-y-2">
                                        <Label>Time to Live (TTL)</Label>
                                        <Input type="text" v-model="form.ttl" placeholder="e.g. PT12H, P2D" />
                                        <InputError :message="form.errors.ttl" />
                                    </div>
                                </div>
                            </fieldset>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <!-- FORM ACTION BUTTONS WITH BALANCE -->
                <div class="flex items-center justify-between pt-4">
                    <div class="flex items-center gap-4">
                        <Button type="submit">Generate</Button>

                        <Button
                            type="button"
                            variant="link"
                            class="text-sm"
                            @click="resetForm"
                        >
                            Clear
                        </Button>
                        <Button
                            v-if="voucherCodes.length"
                            type="button"
                            variant="link"
                            @click="showDialog = true"
                        >
                            Show
                        </Button>
                    </div>
                    <div class="text-sm text-gray-600 italic">
                        Balance:
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
                    Share the codes and review the submitted instructions.
                </DialogDescription>
            </DialogHeader>

            <!-- Voucher codes preview -->
            <div class="mt-2">
                <p class="text-xs font-medium text-gray-600 mb-1">Voucher Codes:</p>
                <input
                    type="text"
                    readonly
                    :value="formattedVoucherCodes"
                    class="w-full rounded border px-3 py-2 font-mono text-sm"
                    ref="voucherInput"
                />
                <div class="mt-2 flex justify-end">
                    <Button
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        @click="copyToClipboard(formattedVoucherCodes)"
                    >
                        Copy Codes
                    </Button>
                </div>
            </div>

            <!-- Instruction preview -->
            <div class="mt-4">
                <p class="text-xs font-medium text-gray-600 mb-1">Submitted Instructions:</p>
                <pre class="text-xs text-gray-700 max-h-64 overflow-y-auto whitespace-pre-wrap bg-gray-100 px-3 py-2 rounded">{{ JSON.stringify(confirmedInstructions, null, 2) }}</pre>
            </div>

            <DialogFooter class="mt-4">
                <DialogClose class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Close</DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showConfirmation">
        <DialogOverlay />
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Confirm Instructions</DialogTitle>
                <DialogDescription class="text-sm text-gray-500">
                    Please review the voucher instructions before submitting.
                </DialogDescription>
            </DialogHeader>

            <!-- Cleaned form preview -->
            <div class="mt-2 rounded bg-gray-100 px-3 py-2">
                <p class="text-xs font-medium text-gray-600 mb-1">Instruction Summary:</p>
                <pre class="text-xs text-gray-700 max-h-64 overflow-y-auto whitespace-pre-wrap">
{{ JSON.stringify(clean(form, [], excluded), null, 2) }}
            </pre>
            </div>

            <DialogFooter class="mt-4 flex justify-end gap-2">
                <DialogClose as="button" class="rounded bg-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-300">
                    Cancel
                </DialogClose>
                <button @click="submitConfirmed" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    Confirm & Submit
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
