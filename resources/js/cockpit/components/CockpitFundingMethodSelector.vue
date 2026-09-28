<script setup lang="ts">
import { Landmark, QrCode, TicketCheck } from 'lucide-vue-next';
import { nextTick } from 'vue';
import type {
    CockpitFundingMethodSelectorReadModel,
    CockpitPrimaryFundingWorkspaceMode,
} from '../types';

const props = defineProps<{
    selector: CockpitFundingMethodSelectorReadModel;
    modelValue: CockpitPrimaryFundingWorkspaceMode | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [mode: CockpitPrimaryFundingWorkspaceMode];
}>();

function select(mode: CockpitPrimaryFundingWorkspaceMode): void {
    const method = props.selector.methods.find(
        (candidate) => candidate.workspace_mode === mode,
    );

    if (method?.selectable !== true) {
        return;
    }

    emit('update:modelValue', mode);
}

async function moveFocus(
    event: KeyboardEvent,
    currentIndex: number,
): Promise<void> {
    const selectableMethods = props.selector.methods.filter(
        (method) => method.selectable,
    );
    const currentMethod = props.selector.methods[currentIndex];
    const selectableIndex = selectableMethods.findIndex(
        (method) => method.key === currentMethod?.key,
    );

    if (selectableIndex < 0 || selectableMethods.length === 0) {
        return;
    }

    let targetIndex: number | null = null;

    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        targetIndex = (selectableIndex + 1) % selectableMethods.length;
    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        targetIndex =
            (selectableIndex - 1 + selectableMethods.length) %
            selectableMethods.length;
    } else if (event.key === 'Home') {
        targetIndex = 0;
    } else if (event.key === 'End') {
        targetIndex = selectableMethods.length - 1;
    }

    if (targetIndex === null) {
        return;
    }

    event.preventDefault();
    const target = selectableMethods[targetIndex];

    select(target.workspace_mode);
    await nextTick();
    document.getElementById(`funding-mode-${target.workspace_mode}`)?.focus();
}
</script>

<template>
    <div
        class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-800"
        data-testid="cockpit-funding-mode-switcher"
    >
        <div
            class="grid grid-cols-3 gap-2"
            role="tablist"
            aria-label="Funding workspace mode"
        >
            <button
                v-for="(method, index) in selector.methods"
                :id="`funding-mode-${method.workspace_mode}`"
                :key="method.key"
                type="button"
                role="tab"
                class="inline-flex min-h-10 min-w-0 items-center justify-center gap-2 rounded-xl px-2 py-2 text-center text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                :class="
                    modelValue === method.workspace_mode
                        ? 'bg-slate-950 text-white shadow-sm dark:bg-sky-300 dark:text-slate-950'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                "
                :aria-selected="modelValue === method.workspace_mode"
                :aria-controls="`funding-panel-${method.workspace_mode}`"
                :aria-describedby="
                    method.available
                        ? undefined
                        : `funding-method-${method.key}-availability`
                "
                :tabindex="modelValue === method.workspace_mode ? 0 : -1"
                :disabled="!method.selectable"
                :data-testid="`funding-mode-${method.workspace_mode}`"
                @click="select(method.workspace_mode)"
                @keydown="moveFocus($event, index)"
            >
                <QrCode
                    v-if="method.key === 'qr_ph'"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                    data-testid="funding-mode-icon-self_top_up"
                />
                <Landmark
                    v-else-if="method.key === 'bank_transfer'"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                    data-testid="funding-mode-icon-bank_transfer"
                />
                <TicketCheck
                    v-else
                    class="size-4 shrink-0"
                    aria-hidden="true"
                    data-testid="funding-mode-icon-pay_code"
                />
                <span class="truncate">{{ method.label }}</span>
                <span
                    v-if="!method.available"
                    :id="`funding-method-${method.key}-availability`"
                    class="sr-only"
                >
                    {{ method.unavailable_reason ?? 'Unavailable' }}
                </span>
            </button>
        </div>
    </div>

    <p
        class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
        data-testid="funding-confirmation-notice"
    >
        {{ selector.notice }}
    </p>
</template>
