<script setup lang="ts">
import CockpitPayCodeTerminalStateController from '@/actions/LBHurtado/XChange/Http/Controllers/Web/Cockpit/CockpitPayCodeTerminalStateController';
import { Form } from '@inertiajs/vue3';
import { Ban, ClockAlert, ShieldCheck } from 'lucide-vue-next';
import type { CockpitPayCodeTerminalControl } from '../types';

defineProps<{
    code: string;
    control: CockpitPayCodeTerminalControl;
}>();

function formatMoney(amountMinor: number, currency: string): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amountMinor / 100);
}
</script>

<template>
    <section
        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5 xl:col-span-2 dark:border-slate-800 dark:bg-slate-950/50"
        data-testid="pay-code-terminal-controls"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <ShieldCheck class="size-4 text-emerald-600" />
                    <h3
                        class="text-sm font-semibold text-slate-950 dark:text-white"
                    >
                        Treasury-safe terminal actions
                    </h3>
                </div>
                <p
                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                >
                    Expire or cancel an unclaimed regular Pay Code without
                    calling a provider.
                </p>
            </div>
            <span
                class="w-fit rounded-full px-2.5 py-1 text-[0.65rem] font-semibold"
                :class="
                    control.status === 'available'
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200'
                        : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                "
            >
                {{
                    control.status === 'available'
                        ? 'Principal Releasable'
                        : 'Protected'
                }}
            </span>
        </div>

        <div
            class="mt-4 grid gap-3 rounded-2xl border border-emerald-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center dark:border-emerald-900/70 dark:bg-slate-900"
        >
            <div>
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                >
                    From
                </p>
                <p
                    class="mt-1 text-sm font-semibold text-slate-950 dark:text-white"
                >
                    {{ control.release.from }}
                </p>
            </div>
            <span class="hidden text-emerald-600 md:block" aria-hidden="true"
                >→</span
            >
            <div class="md:text-right">
                <p
                    class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"
                >
                    To
                </p>
                <p
                    class="mt-1 text-sm font-semibold text-slate-950 dark:text-white"
                >
                    {{ control.release.to }} ·
                    {{
                        formatMoney(
                            control.release.amount_minor,
                            control.release.currency,
                        )
                    }}
                </p>
            </div>
        </div>

        <div
            class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[0.7rem] text-slate-500 dark:text-slate-400"
        >
            <span>Provider Inventory unchanged</span>
            <span>No provider call</span>
            <span>Issuance charges retained</span>
        </div>

        <p
            v-if="control.status !== 'available'"
            class="mt-4 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs leading-5 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
        >
            {{ control.blocked_reason }}
        </p>

        <div v-else class="mt-4 grid gap-3 lg:grid-cols-2">
            <Form
                v-bind="CockpitPayCodeTerminalStateController.form({ code })"
                #default="{ errors, processing }"
                class="rounded-2xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900/70 dark:bg-amber-950/20"
                data-testid="pay-code-expire-form"
            >
                <input type="hidden" name="action" value="expire" />
                <div class="flex items-start gap-2">
                    <ClockAlert
                        class="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-300"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-semibold text-amber-950 dark:text-amber-100"
                        >
                            Expire now
                        </p>
                        <p
                            class="mt-1 text-[0.7rem] leading-5 text-amber-800 dark:text-amber-200"
                        >
                            Ends claim availability immediately and releases the
                            unclaimed principal.
                        </p>
                    </div>
                </div>
                <label
                    class="mt-3 grid gap-1 text-[0.7rem] font-semibold text-slate-700 dark:text-slate-200"
                >
                    Reason
                    <input
                        name="reason"
                        required
                        maxlength="500"
                        class="rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs font-normal text-slate-950 outline-none focus:border-amber-500 dark:border-amber-900 dark:bg-slate-950 dark:text-white"
                        placeholder="Why should this Pay Code expire now?"
                    />
                    <span
                        v-if="errors.reason"
                        class="font-normal text-rose-700 dark:text-rose-300"
                        >{{ errors.reason }}</span
                    >
                </label>
                <label
                    class="mt-3 flex items-start gap-2 text-[0.7rem] leading-5 text-slate-600 dark:text-slate-300"
                >
                    <input
                        name="confirmed"
                        type="checkbox"
                        value="1"
                        required
                        class="mt-1"
                    />
                    I understand this action is immediate and the original
                    instructions remain on record.
                </label>
                <button
                    type="submit"
                    :disabled="processing"
                    class="mt-3 inline-flex items-center gap-2 rounded-xl bg-amber-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <ClockAlert class="size-4" />
                    {{ processing ? 'Expiring…' : 'Expire Pay Code' }}
                </button>
            </Form>

            <Form
                v-bind="CockpitPayCodeTerminalStateController.form({ code })"
                #default="{ errors, processing }"
                class="rounded-2xl border border-rose-200 bg-rose-50/70 p-3 dark:border-rose-900/70 dark:bg-rose-950/20"
                data-testid="pay-code-cancel-form"
            >
                <input type="hidden" name="action" value="cancel" />
                <div class="flex items-start gap-2">
                    <Ban
                        class="mt-0.5 size-4 shrink-0 text-rose-700 dark:text-rose-300"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-semibold text-rose-950 dark:text-rose-100"
                        >
                            Cancel
                        </p>
                        <p
                            class="mt-1 text-[0.7rem] leading-5 text-rose-800 dark:text-rose-200"
                        >
                            Permanently cancels this Pay Code and releases the
                            unclaimed principal.
                        </p>
                    </div>
                </div>
                <label
                    class="mt-3 grid gap-1 text-[0.7rem] font-semibold text-slate-700 dark:text-slate-200"
                >
                    Reason
                    <input
                        name="reason"
                        required
                        maxlength="500"
                        class="rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-normal text-slate-950 outline-none focus:border-rose-500 dark:border-rose-900 dark:bg-slate-950 dark:text-white"
                        placeholder="Why should this Pay Code be cancelled?"
                    />
                    <span
                        v-if="errors.reason"
                        class="font-normal text-rose-700 dark:text-rose-300"
                        >{{ errors.reason }}</span
                    >
                </label>
                <label
                    class="mt-3 flex items-start gap-2 text-[0.7rem] leading-5 text-slate-600 dark:text-slate-300"
                >
                    <input
                        name="confirmed"
                        type="checkbox"
                        value="1"
                        required
                        class="mt-1"
                    />
                    I understand cancellation is permanent and commercial
                    charges are not refunded.
                </label>
                <button
                    type="submit"
                    :disabled="processing"
                    class="mt-3 inline-flex items-center gap-2 rounded-xl bg-rose-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <Ban class="size-4" />
                    {{ processing ? 'Cancelling…' : 'Cancel Pay Code' }}
                </button>
            </Form>
        </div>
    </section>
</template>
