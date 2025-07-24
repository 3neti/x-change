<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-gray-400 dark:text-gray-500">
            {{ message }}
        </p>
    </div>
</template>
