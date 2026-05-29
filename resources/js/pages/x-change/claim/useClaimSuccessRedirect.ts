import { computed, type Ref } from 'vue';

export function useClaimSuccessRedirect(
    riderRedirect: Ref<any>,
    redirect: Ref<any>,
    redirectEndpoint: Ref<string | null | undefined>,
) {
    const compiledRedirect = computed(() => {
        if (!redirect.value?.show_countdown || !redirectEndpoint.value) {
            return null;
        }

        return {
            enabled: true,
            url: redirectEndpoint.value,
            delay_seconds: redirect.value.delay_seconds ?? 5,
            content: 'Redirecting shortly...',
        };
    });

    const countdownRedirect = computed(() =>
        riderRedirect.value?.enabled
            ? riderRedirect.value
            : compiledRedirect.value,
    );

    const hasRedirect = computed(() =>
        Boolean(countdownRedirect.value?.enabled && redirectEndpoint.value),
    );

    return {
        compiledRedirect,
        countdownRedirect,
        hasRedirect,
    };
}
