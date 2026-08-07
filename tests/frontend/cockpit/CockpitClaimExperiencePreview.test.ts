import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitClaimExperiencePreview from '../../../resources/js/cockpit/components/CockpitClaimExperiencePreview.vue';

describe('Cockpit claim experience preview', () => {
    it('renders storyboard steps when browser-captured frames are unavailable', () => {
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
                                render_kind: 'experience_card',
                                status: 'pending_capture',
                                frame: null,
                            },
                            {
                                sequence: 2,
                                key: 'confirmation',
                                phase: 'review',
                                title: 'Claim confirmation',
                                description: 'Review the claim before continuing.',
                                actor: 'redeemer',
                                render_kind: 'experience_card',
                                status: 'pending_capture',
                                frame: null,
                            },
                        ],
                    },
                    exports: {},
                },
            },
        });

        expect(wrapper.text()).toContain('Claim entry');
        expect(wrapper.text()).toContain('Open the Pay Code securely.');
        expect(
            wrapper
                .find('[data-testid="cockpit-claim-experience-concept"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).not.toContain('Preview unavailable');
    });
});
