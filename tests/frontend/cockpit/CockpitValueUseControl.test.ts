import { config, flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitValueUseControl from '../../../resources/js/cockpit/components/CockpitValueUseControl.vue';

config.global.stubs = {
    ...config.global.stubs,
    Teleport: true,
};

function defaultProps() {
    return {
        mode: 'whole' as const,
        amount: 1000,
        currency: 'PHP',
        fixedCount: 4,
        maxClaims: 10,
        minimumClaim: 25,
        scheduledCount: 3,
        scheduledPortions: [
            {
                id: 'slice_1',
                amount: '500.00',
                description: 'Slice 1',
                tag: '',
                claim_on: '',
                claim_by: '',
            },
            {
                id: 'slice_2',
                amount: '500.00',
                description: 'Slice 2',
                tag: '',
                claim_on: '',
                claim_by: '',
            },
        ],
        scheduledTotal: 1000,
        scheduledRemaining: 0,
        scheduledMinimumAmount: 25,
        scheduledAvailable: true,
        scheduledUnavailableReason: null,
        scheduledAddDisabledReason: null,
        scheduledValidationMessage: null,
        reusableBalance: false,
        storedValueAvailable: false,
        storedValueUnavailableReason:
            'Reusable Balance is unavailable until its durable wallet engine is commissioned.',
        storedValueReplenishable: false,
        storedValueMaximumBalance: 1000,
        storedValueOtpAbove: null,
    };
}

describe('Cockpit Value Use control', () => {
    it('keeps the Order card compact and opens an intent-first claim plan editor', async () => {
        const wrapper = mount(CockpitValueUseControl, {
            props: defaultProps(),
        });

        expect(
            wrapper.get('[data-testid="cockpit-value-use-trigger"]').text(),
        ).toContain('Whole amount');

        await wrapper
            .get('[data-testid="cockpit-value-use-trigger"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-value-use-dialog"]')
                .attributes('role'),
        ).toBe('dialog');
        expect(
            wrapper.get('[data-testid="cockpit-value-use-mode-fixed"]').text(),
        ).toContain('Equal portions');
        expect(
            wrapper.get('[data-testid="cockpit-value-use-mode-open"]').text(),
        ).toContain('Flexible amounts');
        expect(
            wrapper.get('[data-testid="cockpit-value-use-mode-named"]').text(),
        ).toContain('Scheduled portions');

        await wrapper
            .get('[data-testid="cockpit-value-use-mode-fixed"]')
            .trigger('click');

        expect(wrapper.emitted('mode')?.at(-1)).toEqual(['fixed']);
    });

    it('keeps Scheduled portions inside the modal and exposes row edits', async () => {
        const wrapper = mount(CockpitValueUseControl, {
            props: {
                ...defaultProps(),
                mode: 'named',
            },
        });

        await wrapper
            .get('[data-testid="cockpit-value-use-trigger"]')
            .trigger('click');

        expect(
            wrapper.get('[data-testid="cockpit-value-use-dialog"]').isVisible(),
        ).toBe(true);
        expect(
            wrapper.findAll('[data-testid^="cockpit-scheduled-portion-"]'),
        ).not.toHaveLength(0);

        await wrapper
            .get('[data-testid="cockpit-scheduled-portions-add"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-scheduled-portion-0-description"]')
            .setValue('Transport');

        expect(wrapper.emitted('scheduledAdd')).toHaveLength(1);
        expect(wrapper.emitted('scheduledUpdate')?.at(-1)).toEqual([
            0,
            'description',
            'Transport',
        ]);
    });

    it('fails closed when the durable stored-value engine is unavailable', () => {
        const wrapper = mount(CockpitValueUseControl, {
            props: defaultProps(),
        });

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-value-use-reusable-balance"]',
            ).element.disabled,
        ).toBe(true);
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-value-use-stored-value-unavailable"]',
                )
                .text(),
        ).toContain('durable wallet engine');
    });

    it('exposes only the governed reusable-balance settings when available', async () => {
        const wrapper = mount(CockpitValueUseControl, {
            props: {
                ...defaultProps(),
                reusableBalance: true,
                storedValueAvailable: true,
                storedValueReplenishable: true,
                storedValueMaximumBalance: 2000,
                storedValueOtpAbove: 500,
            },
        });

        await wrapper
            .get('[data-testid="cockpit-value-use-trigger"]')
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .get('[data-testid="cockpit-value-use-stored-value-settings"]')
                .text(),
        ).toContain('Starting balance');
        expect(
            wrapper
                .find('[data-testid="cockpit-value-use-mode-open"]')
                .exists(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="cockpit-value-use-maximum-balance"]')
            .setValue('2500');
        await wrapper
            .get('[data-testid="cockpit-value-use-otp-above"]')
            .setValue('750');

        expect(wrapper.emitted('storedValueMaximumBalance')?.at(-1)).toEqual([
            2500,
        ]);
        expect(wrapper.emitted('storedValueOtpAbove')?.at(-1)).toEqual([750]);
    });

    it('uses shrink-safe responsive boundaries without fixed-width workarounds', () => {
        const wrapper = mount(CockpitValueUseControl, {
            props: defaultProps(),
        });
        const html = wrapper.html();

        expect(
            wrapper.get('[data-testid="cockpit-value-use-control"]').classes(),
        ).toContain('min-w-0');
        expect(
            wrapper.get('[data-testid="cockpit-value-use-control"]').classes(),
        ).toContain('@container');
        expect(
            wrapper
                .get('[data-testid="cockpit-value-use-reusable-balance"]')
                .element.closest('label')?.textContent,
        ).toContain('Reusable balance');
        expect(html).not.toContain('>Reusable<');
        expect(html).not.toMatch(/min-w-(?:32|40|48|64|\[)/);
    });
});
