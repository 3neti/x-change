import { computed, type Ref } from 'vue';
import {
    resolveSuccessRedirect,
    type RiderRedirectPayload,
    type SuccessRedirectPayload,
} from '@/components/x-change/successRedirect';

export function useClaimSuccessRedirect(
    riderRedirect: Ref<RiderRedirectPayload | null | undefined>,
    redirect: Ref<SuccessRedirectPayload | null | undefined>,
    redirectEndpoint: Ref<string | null | undefined>,
) {
    const countdownRedirect = computed(() =>
        resolveSuccessRedirect(
            riderRedirect.value,
            redirect.value,
            redirectEndpoint.value,
        )
    );

    const hasRedirect = computed(() =>
        countdownRedirect.value !== null
    );

    return {
        countdownRedirect,
        hasRedirect,
    };
}
