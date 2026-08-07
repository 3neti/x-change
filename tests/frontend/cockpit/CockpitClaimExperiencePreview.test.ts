import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitClaimExperiencePreview from '../../../resources/js/cockpit/components/CockpitClaimExperiencePreview.vue';

describe('Cockpit claim experience preview', () => {
    it('renders and navigates the real claim screens when browser capture is unavailable', async () => {
        const wrapper = mount(CockpitClaimExperiencePreview, {
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

        expect(wrapper.text()).toContain('Claim Pay Code');
        expect(wrapper.text()).toContain('PREVIEW');
        expect(wrapper.text()).toContain('Continue');
        expect(
            wrapper.find('[data-testid="cockpit-claim-live-screen"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-live-screen"]')
                .attributes('data-screen-kind'),
        ).toBe('claim_entry');
        expect(wrapper.text()).not.toContain('Preview unavailable');

        await wrapper
            .get('[data-testid="cockpit-claim-experience-next"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-claim-live-screen"]')
                .attributes('data-screen-kind'),
        ).toBe('confirmation');
        expect(wrapper.text()).toContain('Confirm & Claim');
    });
});
