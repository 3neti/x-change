import { mount } from '@vue/test-utils';
import { Mail, ScanFace, Smartphone } from 'lucide-vue-next';
import { describe, expect, it } from 'vitest';
import CockpitClaimRequirementsControl from '../../../resources/js/cockpit/components/CockpitClaimRequirementsControl.vue';
import type { CockpitClaimRequirementOption } from '../../../resources/js/cockpit/components/CockpitClaimRequirementsControl.vue';

function option(
    overrides: Partial<CockpitClaimRequirementOption> &
        Pick<CockpitClaimRequirementOption, 'value' | 'label'>,
): CockpitClaimRequirementOption {
    return {
        icon: Mail,
        category: 'common',
        helper: `Collect ${overrides.label}.`,
        selected: false,
        locked: false,
        lockedReason: null,
        disabled: false,
        unavailable: false,
        unavailableReason: null,
        priceLabel: null,
        ...overrides,
    };
}

describe('CockpitClaimRequirementsControl', () => {
    it('renders a compact row with an empty state when nothing is selected', () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [option({ value: 'name', label: 'Full Name' })],
            },
        });

        expect(
            wrapper.get('[data-testid="cockpit-claim-requirements-control"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper.get('[data-testid="cockpit-claim-requirements-empty"]')
                .text(),
        ).toBe('None selected');
        expect(
            wrapper.get('[data-testid="cockpit-claim-requirements-trigger"]')
                .text(),
        ).toContain('Claim Requirements');
    });

    it('renders removable chips for selected optional requirements', async () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [
                    option({
                        value: 'selfie',
                        label: 'Selfie Photo',
                        icon: ScanFace,
                        category: 'evidence',
                        selected: true,
                    }),
                ],
            },
        });
        const chip = wrapper.get(
            '[data-testid="cockpit-claim-requirement-chip-selfie"]',
        );

        expect(chip.attributes('data-locked')).toBe('false');
        expect(chip.text()).toContain('Selfie Photo');

        await wrapper
            .get('[data-testid="cockpit-claim-requirement-chip-remove"]')
            .trigger('click');

        expect(wrapper.emitted('toggle')).toEqual([['selfie', false]]);
    });

    it('renders locked chips without a remove control and with a non-tooltip-only accessible reason', () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [
                    option({
                        value: 'mobile',
                        label: 'Mobile Number',
                        icon: Smartphone,
                        selected: true,
                        locked: true,
                        lockedReason:
                            'Required because Pay To is a mobile number.',
                        disabled: true,
                    }),
                ],
            },
        });
        const chip = wrapper.get(
            '[data-testid="cockpit-claim-requirement-chip-mobile"]',
        );

        expect(chip.attributes('data-locked')).toBe('true');
        expect(
            chip.find('[data-testid="cockpit-claim-requirement-chip-remove"]')
                .exists(),
        ).toBe(false);
        expect(
            chip.find('[data-testid="cockpit-claim-requirement-chip-lock"]')
                .exists(),
        ).toBe(true);
        // The reason is carried by a static aria-label, not only by a
        // hover/focus tooltip, so it is available on touch/mobile too.
        expect(chip.attributes('aria-label')).toContain(
            'Required because Pay To is a mobile number.',
        );
        expect(chip.attributes('tabindex')).toBe('0');
    });

    it('opens the popover from the label trigger and from the + button', async () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: { options: [option({ value: 'name', label: 'Full Name' })] },
        });

        expect(
            wrapper.find('[data-testid="cockpit-claim-requirements-popover"]')
                .exists(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-trigger"]')
            .trigger('click');

        expect(
            wrapper.get('[data-testid="cockpit-claim-requirements-popover"]')
                .exists(),
        ).toBe(true);

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-backdrop"]')
            .trigger('click');

        expect(
            wrapper.find('[data-testid="cockpit-claim-requirements-popover"]')
                .exists(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-add"]')
            .trigger('click');

        expect(
            wrapper.get('[data-testid="cockpit-claim-requirements-popover"]')
                .exists(),
        ).toBe(true);
    });

    it('closes the popover on Escape', async () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: { options: [option({ value: 'name', label: 'Full Name' })] },
        });

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-trigger"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-claim-requirements-search"]')
            .trigger('keydown', { key: 'Escape' });

        expect(
            wrapper.find('[data-testid="cockpit-claim-requirements-popover"]')
                .exists(),
        ).toBe(false);
    });

    it('filters options by label and by a friendly alias, but always selects the canonical value', async () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [
                    option({ value: 'name', label: 'Full Name' }),
                    option({
                        value: 'kyc',
                        label: 'KYC',
                        category: 'evidence',
                        helper: 'Require identity verification.',
                    }),
                ],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-trigger"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-claim-requirements-search"]')
            .setValue('identity');

        expect(
            wrapper
                .find('[data-testid="cockpit-claim-requirement-option-name"]')
                .exists(),
        ).toBe(false);
        const kycOption = wrapper.get(
            '[data-testid="cockpit-claim-requirement-option-kyc"]',
        );

        expect(kycOption.text()).toContain('KYC');

        await kycOption.get('input[type="checkbox"]').setValue(true);

        expect(wrapper.emitted('toggle')).toEqual([['kyc', true]]);
    });

    it('keeps an unavailable capability disabled and explains the reason as visible text', async () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [
                    option({
                        value: 'location',
                        label: 'Location',
                        category: 'evidence',
                        disabled: true,
                        unavailable: true,
                        unavailableReason:
                            'Location services are not configured.',
                    }),
                ],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-trigger"]')
            .trigger('click');
        const locationOption = wrapper.get(
            '[data-testid="cockpit-claim-requirement-option-location"]',
        );

        expect(
            locationOption.get('input[type="checkbox"]').attributes(
                'disabled',
            ),
        ).toBeDefined();
        expect(locationOption.text()).toContain(
            'Location services are not configured.',
        );

        await locationOption
            .get('input[type="checkbox"]')
            .trigger('change');

        expect(wrapper.emitted('toggle')).toBeUndefined();
    });

    it('emits a preset event without inventing a competing selection model', async () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [option({ value: 'name', label: 'Full Name' })],
                presets: [{ key: 'basic_identity', label: 'Basic Identity' }],
            },
        });

        await wrapper
            .get('[data-testid="cockpit-claim-requirements-trigger"]')
            .trigger('click');
        await wrapper
            .get(
                '[data-testid="cockpit-claim-requirements-preset-basic_identity"]',
            )
            .trigger('click');

        expect(wrapper.emitted('preset')).toEqual([['basic_identity']]);
    });

    it('keeps internal horizontal containment without a hard minimum width beyond the Order card', () => {
        const wrapper = mount(CockpitClaimRequirementsControl, {
            props: {
                options: [
                    option({ value: 'name', label: 'Full Name', selected: true }),
                ],
            },
        });
        const root = wrapper.get(
            '[data-testid="cockpit-claim-requirements-control"]',
        );
        const chipRow = wrapper.get(
            '[data-testid="cockpit-claim-requirements-chips"]',
        );

        expect(root.classes()).toContain('min-w-0');
        expect(chipRow.classes()).toContain('min-w-0');
        expect(chipRow.classes()).toContain('overflow-x-auto');
        expect(
            root.html().match(/min-w-(\d|\[)/g)?.filter(
                (match) => match !== 'min-w-0',
            ) ?? [],
        ).toHaveLength(0);
    });
});
