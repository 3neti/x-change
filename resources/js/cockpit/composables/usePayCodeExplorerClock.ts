import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

export function usePayCodeExplorerClock(enabled: Ref<boolean>): Ref<number> {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        if (enabled.value) {
            timer = setInterval(() => {
                now.value = Date.now();
            }, 30_000);
        }
    });

    onBeforeUnmount(() => {
        if (timer !== null) {
            clearInterval(timer);
        }
    });

    return now;
}
