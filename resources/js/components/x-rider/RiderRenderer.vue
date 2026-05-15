<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';

interface RiderContent {
    enabled: boolean;
    type: string;
    content?: string | null;
    meta?: Record<string, unknown>;
}

const props = defineProps<{
    content?: RiderContent | null;
}>();

const hasContent = computed(() =>
    Boolean(props.content?.enabled && props.content?.content)
);

const renderedContent = computed(() => {
    const raw = props.content?.content;

    if (!hasContent.value || !raw) {
        return null;
    }

    if (props.content?.type === 'text') {
        return raw.replace(/\n/g, '<br>');
    }

    if (props.content?.type === 'markdown') {
        try {
            return marked.parse(raw) as string;
        } catch {
            return raw.replace(/\n/g, '<br>');
        }
    }

    /**
     * First slice fallback:
     * unknown content types are rendered as plain text.
     *
     * Later, this component can delegate to a renderer registry.
     */
    return raw.replace(/\n/g, '<br>');
});
</script>

<template>
    <div v-if="hasContent" class="overflow-visible">
        <div
            v-html="renderedContent"
            class="prose prose-lg max-w-none text-center font-semibold dark:prose-invert"
        />
    </div>
</template>
