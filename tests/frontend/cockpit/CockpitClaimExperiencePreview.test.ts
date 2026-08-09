import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitClaimExperiencePreview from '../../../resources/js/cockpit/components/CockpitClaimExperiencePreview.vue';

describe('Cockpit claim experience preview', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('renders the claim journey inside its canonical isolated mobile viewport', async () => {
        const wrapper = mount(CockpitClaimExperiencePreview, {
            global: {
                stubs: {
                    Teleport: true,
                },
            },
            props: {
                status: 'ready',
                processing: false,
                message: 'Claim walkthrough preview is ready.',
                stale: false,
                canGenerate: true,
                manifest: {
                    schema: 'x-change.claim-experience-preview.manifest.v1',
                    status: 'ready',
                    reference: 'preview-storyboard',
                    fingerprint: 'storyboard-fingerprint',
                    generated_at: '2026-08-07T00:00:00Z',
                    cache_hit: false,
                    safety: {
                        preview_only: true,
                        interactive: false,
                        money_movement: false,
                        provider_calls: false,
                        claim_submission: false,
                    },
                    journey: {
                        viewport: {
                            profile: 'mobile_claim_v1',
                            width: 360,
                            height: 720,
                        },
                        step_count: 2,
                        steps: [
                            {
                                sequence: 1,
                                key: 'claim-entry',
                                phase: 'entry',
                                title: 'Claim entry',
                                description: 'Open the Pay Code securely.',
                                actor: 'redeemer',
                                render_kind: 'live_screen',
                                status: 'rendered',
                                preview_url:
                                    '/x/cockpit/quick-generate/claim-previews/preview-storyboard/steps/claim-entry',
                                frame: null,
                                screen: {
                                    kind: 'claim_entry',
                                    code: 'PREVIEW',
                                    amount: '₱25.00',
                                    title: 'Claim Pay Code',
                                    description:
                                        'Enter the Pay Code shared with you.',
                                    fields: [
                                        {
                                            key: 'code',
                                            label: 'Pay Code',
                                            value: 'PREVIEW',
                                        },
                                    ],
                                },
                            },
                            {
                                sequence: 2,
                                key: 'confirmation',
                                phase: 'review',
                                title: 'Claim confirmation',
                                description:
                                    'Review the claim before continuing.',
                                actor: 'redeemer',
                                render_kind: 'live_screen',
                                status: 'rendered',
                                preview_url:
                                    '/x/cockpit/quick-generate/claim-previews/preview-storyboard/steps/confirmation',
                                frame: null,
                                screen: {
                                    kind: 'confirmation',
                                    code: 'PREVIEW',
                                    amount: '₱25.00',
                                    title: 'Confirm Claim',
                                    description:
                                        'Review and confirm your Pay Code claim.',
                                    fields: [],
                                },
                            },
                        ],
                    },
                    exports: {},
                },
            },
        });

        const iframe = wrapper.get(
            '[data-testid="cockpit-claim-preview-iframe"]',
        );

        expect(iframe.attributes('src')).toContain('/steps/claim-entry');
        expect(iframe.attributes('width')).toBe('360');
        expect(iframe.attributes('height')).toBe('720');
        expect(iframe.attributes('sandbox')).toBe(
            'allow-scripts allow-same-origin',
        );
        expect(iframe.attributes('style')).toContain(
            'transform-origin: top left',
        );
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-preview-device"]')
                .classes(),
        ).toContain('box-content');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-preview-viewport"]')
                .attributes('data-viewport-profile'),
        ).toBe('mobile_claim_v1');
        expect(wrapper.text()).not.toContain('Preview unavailable');

        await wrapper
            .get('[data-testid="cockpit-claim-experience-next"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-preview-iframe"]')
                .attributes('src'),
        ).toContain('/steps/confirmation');
    });

    it('opens a large synchronized walkthrough without changing the active step', async () => {
        const wrapper = mount(CockpitClaimExperiencePreview, {
            attachTo: document.body,
            global: {
                stubs: {
                    Teleport: true,
                },
            },
            props: {
                status: 'ready',
                processing: false,
                message: 'Ready.',
                canGenerate: true,
                manifest: {
                    schema: 'x-change.claim-experience-preview.manifest.v1',
                    status: 'ready',
                    reference: 'preview-modal',
                    fingerprint: 'modal-fingerprint',
                    generated_at: '2026-08-07T00:00:00Z',
                    cache_hit: false,
                    safety: {
                        preview_only: true,
                        interactive: false,
                        money_movement: false,
                        provider_calls: false,
                        claim_submission: false,
                    },
                    journey: {
                        viewport: {
                            profile: 'mobile_claim_v1',
                            width: 360,
                            height: 720,
                        },
                        step_count: 2,
                        steps: [
                            {
                                sequence: 1,
                                key: 'claim-entry',
                                phase: 'entry',
                                title: 'Claim entry',
                                description: 'Open the code.',
                                actor: 'redeemer',
                                render_kind: 'live_screen',
                                status: 'rendered',
                                preview_url: '/preview/steps/claim-entry',
                                frame: null,
                                screen: { kind: 'claim_entry' },
                            },
                            {
                                sequence: 2,
                                key: 'confirmation',
                                phase: 'review',
                                title: 'Confirm claim',
                                description: 'Confirm it.',
                                actor: 'redeemer',
                                render_kind: 'live_screen',
                                status: 'rendered',
                                preview_url: '/preview/steps/confirmation',
                                frame: null,
                                screen: { kind: 'confirmation' },
                            },
                        ],
                    },
                    exports: {},
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-claim-experience-next"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-claim-experience-expand"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-experience-dialog"]')
                .attributes('aria-modal'),
        ).toBe('true');
        expect(wrapper.text()).toContain('Confirm claim');

        const expandedViewport = wrapper.findAll(
            '[data-testid="cockpit-claim-preview-viewport"]',
        )[1];
        expect(expandedViewport.attributes('data-presentation')).toBe(
            'expanded',
        );
        expect(
            expandedViewport
                .get('[data-testid="cockpit-claim-preview-iframe"]')
                .attributes('src'),
        ).toBe('/preview/steps/confirmation');

        await wrapper
            .get('[data-testid="cockpit-claim-experience-dialog-step-1"]')
            .trigger('click');

        expect(
            wrapper
                .findAll('[data-testid="cockpit-claim-preview-iframe"]')[0]
                .attributes('src'),
        ).toBe('/preview/steps/claim-entry');

        await wrapper
            .get('[data-testid="cockpit-claim-experience-dialog-close"]')
            .trigger('click');
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-experience-dialog"]')
                .exists(),
        ).toBe(false);

        wrapper.unmount();
    });

    it('runs a request-free autoplay walkthrough from local claim screens', async () => {
        vi.useFakeTimers();

        const wrapper = mount(CockpitClaimExperiencePreview, {
            props: {
                status: 'ready',
                processing: false,
                message: 'Safe landing-page demonstration.',
                canGenerate: false,
                safePresentation: true,
                autoplay: true,
                autoplayIntervalMs: 1000,
                manifest: {
                    schema: 'x-change.claim-experience-preview.manifest.v1',
                    status: 'ready',
                    reference: 'public-demo',
                    fingerprint: 'public-demo-fingerprint',
                    generated_at: '2026-08-08T00:00:00Z',
                    cache_hit: true,
                    safety: {
                        preview_only: true,
                        interactive: false,
                        money_movement: false,
                        provider_calls: false,
                        claim_submission: false,
                    },
                    journey: {
                        viewport: {
                            profile: 'mobile_claim_v1',
                            width: 360,
                            height: 720,
                        },
                        step_count: 2,
                        steps: [
                            {
                                sequence: 1,
                                key: 'claim-entry',
                                phase: 'entry',
                                title: 'Open Pay Code',
                                description: 'Open the code.',
                                actor: 'redeemer',
                                render_kind: 'live_screen',
                                status: 'rendered',
                                preview_url: '',
                                frame: null,
                                screen: {
                                    kind: 'claim_entry',
                                    code: 'DEMO-500',
                                    amount: '₱500.00',
                                    title: 'Claim Pay Code',
                                },
                            },
                            {
                                sequence: 2,
                                key: 'confirmation',
                                phase: 'review',
                                title: 'Confirm details',
                                description: 'Review the claim.',
                                actor: 'redeemer',
                                render_kind: 'live_screen',
                                status: 'rendered',
                                preview_url: '',
                                frame: null,
                                screen: {
                                    kind: 'confirmation',
                                    code: 'DEMO-500',
                                    amount: '₱500.00',
                                    title: 'Confirm Claim',
                                },
                            },
                        ],
                    },
                    exports: {},
                },
            },
        });

        expect(
            wrapper
                .find('[data-testid="cockpit-claim-preview-iframe"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-live-screen"]')
                .attributes('data-screen-kind'),
        ).toBe('claim_entry');
        expect(wrapper.get('img[alt="Pay Code"]').attributes('src')).toContain(
            '/pay-code/pay-code-logo.svg',
        );
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-experience-refresh"]')
                .exists(),
        ).toBe(false);

        await vi.advanceTimersByTimeAsync(1000);

        expect(wrapper.text()).toContain('Confirm details');
        expect(
            wrapper
                .get('[data-testid="cockpit-claim-live-screen"]')
                .attributes('data-screen-kind'),
        ).toBe('confirmation');

        await wrapper
            .get('[data-testid="cockpit-claim-experience-previous"]')
            .trigger('click');

        expect(wrapper.text()).toContain('Open Pay Code');
        expect(wrapper.emitted('generate')).toBeUndefined();
        expect(wrapper.emitted('refresh')).toBeUndefined();
    });
});
