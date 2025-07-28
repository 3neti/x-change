<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl shadow-xl max-w-md w-full">
            <h2 class="text-lg font-semibold text-red-600 mb-2">
                Location Access Denied
            </h2>
            <p class="text-sm text-gray-800 dark:text-gray-200 mb-4">
                You need to allow location access to continue the redemption process. Here's how to enable it:
            </p>

            <ul class="list-disc text-sm text-gray-700 dark:text-gray-300 pl-5 space-y-1">
                <li>Click the <strong>🔒 padlock</strong> icon near your browser’s address bar</li>
                <li>Go to <strong>Site settings</strong></li>
                <li>Find <strong>Location</strong> and set it to <strong>"Allow"</strong></li>
                <li>Reload the page and try again</li>
            </ul>

            <div class="mt-6 flex justify-end">
                <button
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded"
                    @click="close"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const show = ref(false);

function open() {
    show.value = true;
}

function close() {
    show.value = false;
}

// expose the open method for parent components
defineExpose({ open });
</script>
