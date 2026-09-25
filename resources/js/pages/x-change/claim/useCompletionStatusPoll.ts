import { computed, onMounted, onUnmounted, ref, watch, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

/** Re-read presentation only; never invoke claim or policy execution. */
export function useCompletionStatusPoll(processing: Ref<boolean>) {
    const checking = ref(false);
    const stopped = ref(false);
    let mounted = false;
    let deadline = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let expiry: ReturnType<typeof setTimeout> | undefined;
    let cancel: (() => void) | undefined;

    function clear() {
        clearTimeout(timer);
        clearTimeout(expiry);
        cancel?.();
        cancel = undefined;
    }

    function schedule() {
        clearTimeout(timer);
        if (!mounted || !processing.value || stopped.value) return;
        if (Date.now() >= deadline) {
            stopped.value = true;
            return;
        }
        timer = setTimeout(() => {
            if (document.visibilityState === 'hidden') schedule();
            else check();
        }, 5000);
    }

    function check() {
        if (!mounted || !processing.value || checking.value || document.visibilityState === 'hidden') return;
        checking.value = true;
        router.reload({
            only: ['success_presentation', 'success_action'],
            onCancelToken: (token) => { cancel = () => token.cancel(); },
            onError: () => { stopped.value = true; },
            onHttpException: () => { stopped.value = true; return false; },
            onNetworkError: () => { stopped.value = true; return false; },
            onFinish: () => {
                checking.value = false;
                cancel = undefined;
                schedule();
            },
        });
    }

    function begin() {
        if (!mounted || !processing.value) return;
        stopped.value = false;
        deadline = Date.now() + 120000;
        expiry = setTimeout(() => {
            stopped.value = true;
            clear();
        }, 120000);
        schedule();
    }

    watch(processing, (value) => {
        clear();
        if (value) begin();
    });
    onMounted(() => { mounted = true; begin(); });
    onUnmounted(() => { mounted = false; clear(); });

    const message = computed(() => stopped.value
        ? 'Processing is taking longer than expected or the status check is unavailable. We’ll send the link by SMS when it is ready. You can check the status again.'
        : 'This page will update automatically when your policy result is ready.');

    return { checking, stopped, message, check };
}
