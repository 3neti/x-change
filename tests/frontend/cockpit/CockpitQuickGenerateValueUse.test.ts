import { config, flushPromises, mount } from '@vue/test-utils';
import { vi, describe, expect, it } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
    router: {
        reload: vi.fn(),
    },
}));

config.global.stubs = {
    ...config.global.stubs,
    Teleport: true,
};

function preview(wrapper: ReturnType<typeof mount>): Record<string, any> {
    return JSON.parse(
        wrapper
            .get(
                '[data-testid="cockpit-quick-generate-engineering-preview-json"]',
            )
            .text(),
    );
}

function storedValueCapability() {
    return {
        key: 'stored_value',
        label: 'Reusable Balance',
        status: 'ready' as const,
        issuance_allowed: true,
        claim_retryable: false,
        missing_configuration: [],
        source: 'wallet-stored-value',
    };
}

describe('Cockpit Quick Generate value use', () => {
    it('commits a calculator amount while Reusable Balance is enabled', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                instructionCapabilities: {
                    stored_value: storedValueCapability(),
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-value-use-reusable-balance"]')
            .setValue(true);
        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-amount"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="numeric-keypad-quick-100"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="numeric-keypad-confirm"]')
            .trigger('click');
        await flushPromises();

        expect(
            wrapper.get<HTMLInputElement>(
                '[data-testid="cockpit-quick-generate-primary-amount"]',
            ).element.value,
        ).toBe('100.00');
        expect(preview(wrapper).cash.amount).toBe(100);
        expect(preview(wrapper).stored_value).toMatchObject({
            enabled: true,
            maximum_balance: 100,
        });
    });

    it('preserves Flexible claim settings when amount changes', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
            },
        });

        await wrapper
            .get('[data-testid="cockpit-value-use-trigger"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-value-use-mode-open"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-value-use-max-claims"]')
            .setValue('7');
        await wrapper
            .get('[data-testid="cockpit-value-use-minimum-claim"]')
            .setValue('30');
        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-amount"]')
            .setValue('250');
        await flushPromises();

        expect(preview(wrapper).slice_plan).toMatchObject({
            mode: 'flexible',
            max_slices: 7,
            min_amount_minor: 3000,
            total_minor: 25000,
        });
    });

    it('compiles a typed Reusable Balance draft and restores the previous slice plan', async () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                instructionCapabilities: {
                    stored_value: storedValueCapability(),
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-value-use-trigger"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-value-use-mode-open"]')
            .trigger('click');
        await wrapper
            .get('[data-testid="cockpit-value-use-max-claims"]')
            .setValue('6');
        await wrapper
            .get('[data-testid="cockpit-value-use-minimum-claim"]')
            .setValue('30');
        await wrapper
            .get('[data-testid="cockpit-value-use-done"]')
            .trigger('click');

        await wrapper
            .get('[data-testid="cockpit-value-use-reusable-balance"]')
            .setValue(true);
        await flushPromises();

        const storedValuePreview = preview(wrapper);

        expect(storedValuePreview.stored_value).toEqual({
            enabled: true,
            replenishable: false,
            maximum_balance: storedValuePreview.cash.amount,
            otp_required_above: null,
        });
        expect(storedValuePreview.cash).not.toHaveProperty('slice_mode');
        expect(storedValuePreview.cash).not.toHaveProperty('settlement_rail');
        expect(storedValuePreview.metadata.custom.cockpit).not.toHaveProperty(
            'slice_plan',
        );
        expect(
            wrapper
                .find(
                    '[data-testid="cockpit-quick-generate-primary-settlement-rail"]',
                )
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-voucher-kind"]')
                .text(),
        ).toBe('Stored Value');

        await wrapper
            .get('[data-testid="cockpit-value-use-reusable-balance"]')
            .setValue(false);
        await flushPromises();

        expect(preview(wrapper).slice_plan).toMatchObject({
            mode: 'flexible',
            max_slices: 6,
            min_amount_minor: 3000,
        });
    });

    it('renders Reusable Balance disabled when no durable capability is commissioned', () => {
        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
            },
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
});
