<script setup lang="ts">
withDefaults(
    defineProps<{ title?: string; codeLabel?: string; code?: string }>(),
    { title: 'Claim Pay Code', codeLabel: 'Pay Code', code: 'AA317' },
);
</script>
<template>
    <div
        data-testid="claim-entry-panel"
        data-flow-stack
        class="grid min-w-0 gap-6"
    >
        <header class="text-center">
            <h1 class="text-xl font-medium">{{ title }}</h1>
        </header>
        <div class="grid min-w-0 gap-2">
            <label for="code" class="text-sm font-medium">{{
                codeLabel
            }}</label>
            <slot name="input"
                ><div
                    class="grid min-h-11 place-items-center rounded-md border bg-background px-3 text-center text-lg font-medium tracking-wider"
                >
                    {{ code }}
                </div></slot
            >
        </div>
        <slot />
    </div>
</template>
