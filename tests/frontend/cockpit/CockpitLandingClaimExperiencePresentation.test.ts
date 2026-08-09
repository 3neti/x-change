import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitClaimExperiencePreview from '../../../resources/js/cockpit/components/CockpitClaimExperiencePreview.vue';
import CockpitLandingClaimExperiencePresentation from '../../../resources/js/cockpit/components/CockpitLandingClaimExperiencePresentation.vue';

describe('Cockpit landing claim experience presentation', () => {
    it('replays the canonical Claim tab journey with real safe form-flow screens', () => {
        const wrapper = shallowMount(CockpitLandingClaimExperiencePresentation);
        const preview = wrapper.getComponent(CockpitClaimExperiencePreview);
        const manifest = preview.props('manifest');

        expect(manifest.journey.steps.map((step) => step.title)).toEqual([
            'Claim entry',
            'Splash',
            'Disbursement Details',
            'Claim confirmation',
            'Claim success',
        ]);
        expect(manifest.journey.step_count).toBe(5);
        expect(manifest.journey.steps[0].screen?.kind).toBe('claim_entry');
        expect(manifest.journey.steps[0].screen).toMatchObject({
            code: 'AA-317',
            amount: '₱537.00',
        });
        expect(manifest.journey.steps[1].screen).toMatchObject({
            kind: 'form_flow_handler',
            component: 'form-flow/core/Splash',
            props: {
                preview_mode: true,
                voucher_code: 'AA-317',
                app_logo: '/vendor/x-change/images/pay-code/pay-code-logo.svg',
            },
        });
        expect(manifest.journey.steps[2].screen).toMatchObject({
            kind: 'form_flow_handler',
            component: 'form-flow/core/GenericForm',
            props: {
                preview_mode: true,
                title: 'Disbursement Details',
                fields: expect.arrayContaining([
                    expect.objectContaining({
                        name: 'amount',
                        default: '537',
                        available_balance: '537',
                    }),
                ]),
            },
        });
        expect(manifest.safety).toEqual({
            preview_only: true,
            interactive: false,
            money_movement: false,
            provider_calls: false,
            claim_submission: false,
        });
        expect(preview.props()).toMatchObject({
            safePresentation: true,
            canGenerate: false,
            autoplay: true,
        });
    });
});
