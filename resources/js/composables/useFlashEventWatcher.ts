import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface FlashEvent<T = any> {
    name: string;
    data: T;
}

interface FlashProps {
    event?: FlashEvent;
}

export function useFlashEventWatcher<T = any>(
    name: string,
    callback: (data: T) => void
) {
    watch(
        () => {
            const { event } = usePage().props.flash as FlashProps;
            return event;
        },
        (event) => {
            if (event?.name === name && event.data !== undefined) {
                callback(event.data as T);
            }
        },
        { immediate: true }
    );
}
