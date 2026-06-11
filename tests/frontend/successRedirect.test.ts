import { describe, expect, it } from 'vitest';
import {   resolveSuccessRedirect, shouldRenderSuccessRedirectCountdown } from '../../resources/js/components/x-change/successRedirect';

describe('success redirect', () => {
    it('renders countdown for claim-widget owned redirect', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'claim-widget',
            show_countdown: true,
            delay_seconds: 5,
        })).toBe(true);
    });

    it('renders countdown for claim-widget owned redirect', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'claim-widget',
            show_countdown: true,
            delay_seconds: 5,
        })).toBe(true);
    });

    it('does not render countdown for success-page owned redirect', () => {
        expect(shouldRenderSuccessRedirectCountdown({
            owner: 'success-page',
            show_countdown: true,
            delay_seconds: 5,
        })).toBe(false);
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

    it('resolves rider redirect before compiled redirect', () => {
        expect(resolveSuccessRedirect(
            {
                enabled: true,
                url: 'https://rider.example.com',
                delay_seconds: 3,
            },
            {
                owner: 'claim-widget',
                show_countdown: true,
                delay_seconds: 5,
            },
            '/x/claim/TEST123/redirect',
        )).toEqual({
            enabled: true,
            url: 'https://rider.example.com',
            delay_seconds: 3,
        });
    });

    it('does not build compiled redirect for success-page owned redirect', () => {
        expect(resolveSuccessRedirect(
            null,
            {
                owner: 'success-page',
                show_countdown: true,
                delay_seconds: 5,
            },
            '/x/claim/TEST123/redirect',
        )).toBeNull();
    });

    it('resolves compiled redirect to redirect endpoint', () => {
        expect(resolveSuccessRedirect(
            null,
            {
                owner: 'claim-widget',
                show_countdown: true,
                delay_seconds: 5,
            },
            '/x/claim/TEST123/redirect',
        )).toEqual({
            enabled: true,
            url: '/x/claim/TEST123/redirect',
            delay_seconds: 5,
            content: 'Redirecting shortly...',
        });
    });

    it('does not resolve redirect without redirect endpoint', () => {
        expect(resolveSuccessRedirect(
            {
                enabled: true,
                url: 'https://rider.example.com',
                delay_seconds: 3,
            },
            {
                owner: 'claim-widget',
                show_countdown: true,
                delay_seconds: 5,
            },
            null,
        )).toBeNull();
    });

    it('does not resolve disabled redirect', () => {
        expect(resolveSuccessRedirect(
            null,
            {
                owner: 'claim-widget',
                show_countdown: false,
                delay_seconds: 5,
            },
            '/x/claim/TEST123/redirect',
        )).toBeNull();
    });
});
