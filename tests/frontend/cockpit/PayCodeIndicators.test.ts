import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitPayCodeIndicator from '../../../resources/js/cockpit/components/CockpitPayCodeIndicator.vue';
import {
    payCodeOutcomeIndicatorKey,
    resolvePayCodeIndicator,
} from '../../../resources/js/cockpit/payCodeIndicators';

describe('Pay Code indicators', () => {
    it('maps x-commerce catalog references without inspecting display labels', () => {
        expect(resolvePayCodeIndicator('cash.amount').label).toBe(
            'Pay Code Generation',
        );
        expect(resolvePayCodeIndicator('inputs.fields.mobile').label).toBe(
            'Mobile Number',
        );
        expect(resolvePayCodeIndicator('cash.validation.mobile').label).toBe(
            'Mobile Restriction',
        );
        expect(resolvePayCodeIndicator('rider.splash').label).toBe(
            'Rider Splash',
        );
    });

    it('uses a neutral priced-instruction fallback for future catalog keys', () => {
        const indicator = resolvePayCodeIndicator(
            'future.biometric.attestation',
        );

        expect(indicator.label).toBe('Attestation');
        expect(indicator.tooltip).toBe('Attestation instruction.');
    });

    it('selects the first-class outcome indicator', () => {
        expect(
            payCodeOutcomeIndicatorKey('provider_disbursement', 'redeemable'),
        ).toBe('outcome.provider_disbursement');
        expect(
            payCodeOutcomeIndicatorKey('account_funding', 'redeemable'),
        ).toBe('outcome.account_funding');
        expect(
            payCodeOutcomeIndicatorKey('provider_disbursement', 'payable'),
        ).toBe('outcome.collect_payment');
        expect(
            payCodeOutcomeIndicatorKey('provider_disbursement', 'settlement'),
        ).toBe('outcome.settlement');
    });

    it('exposes every icon through a keyboard and pointer tooltip', () => {
        const wrapper = mount(CockpitPayCodeIndicator, {
            props: {
                indicatorKey: 'inputs.fields.selfie',
                tooltip: 'Selfie Photo — priced instruction.',
                tone: 'dark',
                size: 'sm',
            },
        });

        const icon = wrapper.get('[role="img"]');
        const tooltip = wrapper.get('[role="tooltip"]');

        expect(icon.attributes('tabindex')).toBe('0');
        expect(icon.attributes('aria-label')).toBe('Selfie Photo');
        expect(icon.attributes('aria-describedby')).toBe(
            tooltip.attributes('id'),
        );
        expect(tooltip.text()).toBe('Selfie Photo — priced instruction.');
        expect(tooltip.classes()).toContain(
            'group-hover/indicator:opacity-100',
        );
        expect(tooltip.classes()).toContain(
            'group-focus-within/indicator:opacity-100',
        );
    });
});
