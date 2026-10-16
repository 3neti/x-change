<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";

type TurnstileApi = {
  render: (element: HTMLElement, options: Record<string, unknown>) => string;
  reset: (widgetId: string) => void;
  remove: (widgetId: string) => void;
};

declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

const props = defineProps<{ siteKey: string }>();
const emit = defineEmits<{ verified: [token: string] }>();
const container = ref<HTMLElement | null>(null);
const unavailable = ref(false);
let widgetId: string | null = null;
let script: HTMLScriptElement | null = null;

function renderWidget(): void {
  if (!container.value || !window.turnstile || widgetId !== null) {
    return;
  }

  widgetId = window.turnstile.render(container.value, {
    sitekey: props.siteKey,
    action: "public_auto_generate",
    callback: (token: string) => emit("verified", token),
    "expired-callback": () => emit("verified", ""),
    "error-callback": () => emit("verified", ""),
  });
}

function reset(): void {
  emit("verified", "");
  if (widgetId !== null) {
    window.turnstile?.reset(widgetId);
  }
}

defineExpose({ reset });

onMounted(() => {
  if (window.turnstile) {
    renderWidget();
    return;
  }

  script = document.createElement("script");
  script.src =
    "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
  script.async = true;
  script.onload = renderWidget;
  script.onerror = () => {
    unavailable.value = true;
  };
  document.head.appendChild(script);
});

onBeforeUnmount(() => {
  if (widgetId !== null) {
    window.turnstile?.remove(widgetId);
  }
  if (script) {
    script.onload = null;
    script.onerror = null;
    if (!window.turnstile) {
      script.remove();
    }
  }
});
</script>

<template>
  <div>
    <div ref="container" data-testid="public-issuance-turnstile" />
    <p v-if="unavailable" class="text-sm text-rose-700" role="alert">
      Verification could not load. Please refresh this page.
    </p>
  </div>
</template>
