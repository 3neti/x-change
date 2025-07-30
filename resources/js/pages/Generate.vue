<!-- resources/js/pages/Disburse.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import type { BreadcrumbItemType } from '@/types';
import { useFlashEventWatcher } from '@/composables/useFlashEventWatcher';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'

import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
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
import axios, { AxiosError } from 'axios';
import InputExtra from '@/components/domain/InputExtra.vue';
import { useCostBreakdown } from '@/composables/useCostBreakdown'
import { useFormatCurrency } from '@/composables/useFormatCurrency'
import get from 'lodash/get'
import { Trash } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItemType[] = [{ title: 'Generate', href: '/disburse' }];

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
    subjects: Record<string, string>;
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
        message: '',
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
const tabs = ['basic', 'advanced', 'text'] as const
type Tab = typeof tabs[number]
const activeTab = ref<Tab>('basic')

// const activeTab = ref<'basic' | 'advanced' | 'text'>('basic');
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

// const excluded = ['cash.currency', 'cash.validation.country', 'payeeMode']
const excluded = ['payeeMode']
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

const { getCostComponent, costBreakdown, loading } = useCostBreakdown(form, excluded)
const formatCurrency = useFormatCurrency()

function getCostMessage(index: string): string {
    const raw = getCostComponent(index)
    return raw ? (formatCurrency(Number(raw), { detailed: false }) as string) : ''
}

function getTotalCost(): string {
    const raw = costBreakdown.value?.total * form.count
    return raw ? (formatCurrency(Number(raw), { detailed: false }) as string) : ''
}

const rawText = ref('')
const parsedInstructions = ref(null)
const parsing = ref(false)
const parseError = ref<string | null>(null) // or string | undefined

async function parseInstructions() {
    parsing.value = true
    parseError.value = null
    parsedInstructions.value = null

    axios.defaults.withCredentials = true

    try {
        const response = await axios.post(route('parse-instructions'), {
            text: rawText.value,
        })

        parsedInstructions.value = clean(response.data)
    } catch (err) {
        const axiosError = err as AxiosError<{ message: string }>
        parseError.value =
            axiosError.response?.data?.message || 'Failed to parse instructions.'
    } finally {
        parsing.value = false
    }
}

function clearInstructions() {
    parsedInstructions.value = null
    parseError.value = ''
    parsing.value = false
}
watch(parsedInstructions, (data) => {
    if (!data) return;

    const cleaned = clean(data);

    resetForm();

    if (cleaned.cash) {
        form.cash.amount = cleaned.cash.amount ?? form.cash.amount;
        form.cash.currency = cleaned.cash.currency ?? form.cash.currency;

        if (cleaned.cash.validation) {
            form.cash.validation.secret = cleaned.cash.validation.secret ?? form.cash.validation.secret;
            form.cash.validation.mobile = cleaned.cash.validation.mobile ?? form.cash.validation.mobile;
            form.cash.validation.country = cleaned.cash.validation.country ?? form.cash.validation.country;
            form.cash.validation.location = cleaned.cash.validation.location ?? form.cash.validation.location;
            form.cash.validation.radius = cleaned.cash.validation.radius ?? form.cash.validation.radius;
        }
    }

    if (cleaned.inputs?.fields)
        form.inputs.fields = cleaned.inputs.fields;

    if (cleaned.feedback) {
        form.feedback.mobile = cleaned.feedback.mobile ?? form.feedback.mobile;
        form.feedback.email = cleaned.feedback.email ?? form.feedback.email;
        form.feedback.webhook = cleaned.feedback.webhook ?? form.feedback.webhook;
    }

    if (cleaned.rider) {
        form.rider.message = cleaned.rider.message ?? form.rider.message;
        form.rider.url = cleaned.rider.url ?? form.rider.url;
    }

    form.count = cleaned.count ?? form.count;
    form.prefix = cleaned.prefix ?? form.prefix;
    form.mask = cleaned.mask ?? form.mask;
    form.ttl = cleaned.ttl ?? form.ttl;
});

const messageFields = reactive({
    subject: '',
    title: '',
    body: '',
    closing: '',
});

function clearMessageFields() {
    messageFields.subject = '';
    messageFields.title = '';
    messageFields.body = '';
    messageFields.closing = '';
}

watch(messageFields, (val) => {
    const allFilled = Object.values(val).every(v => !!v?.trim());
    form.rider.message = allFilled ? JSON.stringify(val) : '';
}, { deep: true });

// ⛰️ Populate from existing props.data.rider.message if available
onMounted(() => {
    const message = props?.data?.rider?.message;

    if (message) {
        try {
            const parsed = JSON.parse(message);
            if (
                parsed &&
                typeof parsed === 'object' &&
                ['subject', 'title', 'body', 'closing'].every(key => key in parsed)
            ) {
                messageFields.subject = parsed.subject ?? '';
                messageFields.title = parsed.title ?? '';
                messageFields.body = parsed.body ?? '';
                messageFields.closing = parsed.closing ?? '';
            }
        } catch (e) {
            console.warn('Invalid message JSON in rider.message:', e);
        }
    }
});
</script>

<template>
    <Head title="Create Voucher" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl space-y-1 rounded bg-white p-6 shadow">
            <h1 class="text-2xl font-bold text-gray-700">Instructions</h1>
            <p class="mt-1 text-sm text-gray-500">Escrow Fund Transfer</p>

            <div class="flex space-x-4 border-b text-sm font-medium">
                <button
                    v-for="tab in ['basic', 'advanced', 'text']"
                    :key="tab"
                    :class="['px-4 py-2', activeTab === tab ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600 hover:text-blue-500']"
                    @click="activeTab = tab as 'basic' | 'advanced' | 'text'"
                >
                    {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
                </button>
            </div>

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
                                    <div class="text-right">
                                        <InputExtra :message="getCostMessage('cash.amount')" />
                                    </div>
                                    <InputError :message="get(form.errors, 'cash.amount')" />
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
                    <Collapsible :defaultOpen="false" class="rounded border border-gray-300">
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
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('cash.validation.secret')" />
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
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('cash.validation.mobile')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'cash.validation.mobile')" />
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
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('cash.validation.secret')" />
                                        </div>
                                    </div>
                                </template>

<!--                                &lt;!&ndash; Message &ndash;&gt;-->
<!--                                <div class="col-span-2 space-y-2">-->
<!--                                    <Label>Message</Label>-->
<!--                                    <Input type="text" v-model="form.rider.message" class="w-full" />-->
<!--                                    <div class="text-right">-->
<!--                                        <InputExtra :message="getCostMessage('rider.message')" />-->
<!--                                    </div>-->
<!--                                    <InputError :message="get(form.errors, 'rider.message')" />-->
<!--                                </div>-->

                                <!-- Structured Message Fields -->
                                <fieldset class="col-span-2 border rounded p-4">
                                    <legend class="text-sm font-medium text-gray-700 px-1">Message</legend>

                                    <div class="grid grid-cols-2 gap-4 mt-2">
                                        <div class="col-span-2 space-y-2">
                                            <Label>Title</Label>
                                            <Input type="text" v-model="messageFields.title" placeholder="Disenchanted - My Chemical Romance"/>
                                        </div>

                                        <div class="flex flex-col space-y-1.5">
                                            <Label for="message-subject">Subject</Label>
                                            <Select v-model="messageFields.subject">
                                                <SelectTrigger id="subject">
                                                    <SelectValue placeholder="Choose a subject" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="item in props.subjects"
                                                        :key="item.value"
                                                        :value="item.label"
                                                    >
                                                        {{ item.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>Closing</Label>
                                            <Input type="text" v-model="messageFields.closing" />
                                        </div>

                                        <div class="col-span-2 space-y-2">
                                            <Label>Body</Label>
                                            <Textarea v-model="messageFields.body" rows="4" />
                                            <div class="text-right">
                                                <InputExtra :message="getCostMessage('rider.message')" />
                                            </div>
                                            <InputError :message="get(form.errors, 'rider.message')" />
                                        </div>

                                        <!-- Message Hint + Clear Button -->
                                        <div class="col-span-2 flex items-center justify-between mt-2">
                                            <p class="text-xs text-muted-foreground">
                                                Message will be auto-generated when all fields are filled.
                                            </p>
                                            <button
                                                type="button"
                                                @click="clearMessageFields"
                                                class="inline-flex items-center justify-center rounded p-1 text-gray-400 hover:text-red-500 transition-colors"
                                                title="Clear"
                                            >
                                                <Trash class="w-4 h-4" />
                                            </button>
                                        </div>

                                    </div>
                                </fieldset>

<!--                                &lt;!&ndash; Resulting JSON Message (auto-updated) &ndash;&gt;-->
<!--                                <div class="col-span-2 space-y-2">-->
<!--                                    <Label>Message JSON (auto-generated)</Label>-->
<!--                                    <Input type="text" v-model="form.rider.message" readonly class="w-full" />-->
<!--                                    <div class="text-right">-->
<!--                                        <InputExtra :message="getCostMessage('rider.message')" />-->
<!--                                    </div>-->
<!--                                    <InputError :message="get(form.errors, 'rider.message')" />-->
<!--                                </div>-->
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
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('feedback.mobile')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'feedback.mobile')" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Email Address</Label>
                                        <Input type="email" v-model="form.feedback.email" autocomplete="username" />
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('feedback.email')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'feedback.email')" />
                                    </div>
                                    <div class="col-span-2 space-y-2">
                                        <Label>Webhook URL</Label>
                                        <Input type="url" v-model="form.feedback.webhook" class="w-full" />
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('feedback.webhook')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'feedback.webhook')" />
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
                                            class="flex items-center justify-between w-full bg-gray-50 px-2 py-1 rounded-md text-xs text-gray-700"
                                        >
                                            <!-- Left side: checkbox + label -->
                                            <div class="flex items-center space-x-1">
                                                <input
                                                    type="checkbox"
                                                    :checked="form.inputs.fields.includes(input)"
                                                    @change="toggleInputField(input)"
                                                    class="form-checkbox text-blue-600 rounded-sm"
                                                />
                                                <span class="whitespace-nowrap">{{ formatLabel(input) }}</span>
                                            </div>

                                            <!-- Right side: Cost -->
                                            <span
                                                v-if="getCostMessage(`inputs.fields.${input}`)"
                                                class="text-[0.7rem] text-gray-500"
                                            >
                    {{ getCostMessage(`inputs.fields.${input}`) }}
                </span>
                                        </label>
                                    </div>
                                    <InputError :message="get(form.errors, 'inputs.fields')" />
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
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('rider.url')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'rider.url')" />
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
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('cash.validation.location')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'cash.validation.location')" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Radius</Label>
                                        <Input type="text" v-model="form.cash.validation.radius" />
                                        <div class="text-right">
                                            <InputExtra :message="getCostMessage('cash.validation.radius')" />
                                        </div>
                                        <InputError :message="get(form.errors, 'cash.validation.radius')" />
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

                <!-- TEXT TAB CONTENT -->
                <div v-if="activeTab === 'text'" class="space-y-6">
                    <div class="space-y-2">
                        <Label for="voucher-text">Enter Instructions</Label>

                        <!-- Show textarea only if parsedInstructions is not yet available -->
                        <template v-if="!parsedInstructions">
            <textarea
                id="voucher-text"
                v-model="rawText"
                rows="10"
                placeholder="e.g., Cut 3 checks worth ₱500 each to 09171234567, secret 123456"
                class="w-full rounded border border-gray-300 p-2 text-sm"
            />
                        </template>

                        <!-- Show parsed instructions in same space -->
                        <template v-else>
                            <div class="rounded border border-gray-200 bg-gray-50 p-4 text-sm whitespace-pre-wrap">
                                <pre class="whitespace-pre-wrap font-mono text-xs text-gray-800">{{ parsedInstructions }}</pre>
                            </div>
                        </template>

                        <!-- Button row -->
                        <div class="text-right">
                            <button
                                class="mt-2 rounded bg-blue-600 px-4 py-1 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
                                :disabled="parsing"
                                @click.prevent="parsedInstructions ? clearInstructions() : parseInstructions()"
                            >
                                {{ parsedInstructions ? 'Clear Instructions' : parsing ? 'Parsing…' : 'Parse Instructions' }}
                            </button>
                        </div>

                        <p v-if="parseError" class="text-sm text-red-600">{{ parseError }}</p>
                    </div>
                </div>

                <!-- FORM ACTION BUTTONS WITH BALANCE -->
                <div class="flex items-center justify-between pt-4">
                    <div class="flex items-center gap-4">
                        <Button type="submit">Generate Code</Button>
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
                    <div class="text-sm space-y-1">
                        <div class="flex justify-between text-gray-600 italic">
                            <span>Balance:</span>
                            <span class="font-semibold text-green-800 text-right w-24">{{ formattedBalance }}</span>
                        </div>

                        <div
                            v-if="costBreakdown"
                            class="flex justify-between text-gray-500"
                        >
                            <span>Cost:</span>
                            <span class="font-semibold text-red-600 text-right w-24">{{ getTotalCost() }}</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
    <Dialog v-model:open="showDialog">
        <DialogOverlay />
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Codes Generated</DialogTitle>
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
