import { describe, expect, it } from 'vitest';
import { shouldRenderSuccessRedirectCountdown } from '../../resources/js/components/x-change/successRedirect';

describe('success redirect', () => {
    it('renders countdown for claim-widget owned redirect', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'claim-widget',
            show_countdown: true,
            delay_seconds: 5,
        })).toBe(true);
    });

    it('renders countdown for success-page owned redirect', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'success-page',
            show_countdown: true,
            delay_seconds: 5,
        })).toBe(true);
    });

    it('does not render countdown for x-rider owned redirect', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'x-rider',
            show_countdown: true,
            delay_seconds: 5,
        })).toBe(false);
    });

    it('does not render countdown when countdown is disabled', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'claim-widget',
            show_countdown: false,
            delay_seconds: 5,
        })).toBe(false);
    });

    it('does not render countdown when redirect is missing', () => {
        expect(shouldRenderSuccessRedirectCountdown(null)).toBe(false);
        expect(shouldRenderSuccessRedirectCountdown(undefined)).toBe(false);
    });
});
