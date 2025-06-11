<script setup lang="ts">
import { defineProps } from 'vue';

interface Props {
    balance: number | null;
    currency: string | null;
    type: string;
    status: 'idle' | 'loading' | 'success' | 'error';
    message: string;
    refresh: () => void;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-medium text-gray-800 dark:text-gray-200">
                    {{ props.type.charAt(0).toUpperCase() + props.type.slice(1) }} Balance
                </h2>
                <div v-if="props.status === 'loading'" class="text-sm text-gray-500">
                    {{ props.message }}
                </div>
                <div v-else-if="props.status === 'error'" class="text-sm text-red-500">
                    {{ props.message }}
                </div>
                <div v-else-if="props.status === 'success'" class="mt-1">
          <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
            {{ props.currency }} {{ props.balance }}
          </span>
                </div>
            </div>
            <button
                @click="props.refresh()"
                class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
            >
                Refresh
            </button>
        </div>
    </div>
</template>
