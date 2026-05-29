import { ref } from 'vue';
import { describe, expect, it } from 'vitest';
import { useClaimSuccessRedirect } from '../../resources/js/pages/x-change/claim/useClaimSuccessRedirect';

describe('useClaimSuccessRedirect', () => {
    it('builds compiled countdown redirect when enabled', () => {
        const { hasRedirect, countdownRedirect } = useClaimSuccessRedirect(
            ref(null),
            ref({
                show_countdown: true,
                owner: 'claim-widget',
                delay_seconds: 5,
            }),
            ref('/x/claim/TEST123/redirect'),
        );

        expect(hasRedirect.value).toBe(true);
        expect(countdownRedirect.value).toMatchObject({
            enabled: true,
            url: '/x/claim/TEST123/redirect',
            delay_seconds: 5,
        });
    });

    it('does not build countdown redirect when disabled', () => {
        const { hasRedirect, countdownRedirect } = useClaimSuccessRedirect(
            ref(null),
            ref({
                show_countdown: false,
                owner: 'claim-widget',
                delay_seconds: 5,
            }),
            ref('/x/claim/TEST123/redirect'),
        );

        expect(hasRedirect.value).toBe(false);
        expect(countdownRedirect.value).toBeNull();
    });

    it('lets rider redirect win when already present', () => {
        const { hasRedirect, countdownRedirect } = useClaimSuccessRedirect(
            ref({
                enabled: true,
                delay_seconds: 9,
                url: 'https://rider.example.com',
            }),
            ref({
                show_countdown: true,
                owner: 'claim-widget',
                delay_seconds: 5,
            }),
            ref('/x/claim/TEST123/redirect'),
        );

        expect(hasRedirect.value).toBe(true);
        expect(countdownRedirect.value).toMatchObject({
            enabled: true,
            delay_seconds: 9,
            url: 'https://rider.example.com',
        });
    });
});
