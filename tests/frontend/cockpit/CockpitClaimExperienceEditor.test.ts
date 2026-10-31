import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href?.url ?? href"><slot /></a>' },
    router: { reload: vi.fn() },
    usePage: vi.fn(() => ({ props: {} })),
}));

function editor(extraProps: Record<string, unknown> = {}) {
    return mount(CockpitQuickGenerateSubmitPanel, {
        props: { templates: cockpitQuickGenerateTemplates, ...extraProps },
        global: { stubs: { Teleport: true, CockpitPayCodeCanvas: true } },
    });
}

function payload(wrapper: ReturnType<typeof editor>): Record<string, any> {
    return JSON.parse(wrapper.get('[data-testid="cockpit-quick-generate-engineering-preview-json"]').text());
}

describe('Claim Experience editor', () => {
    it('keeps public checkout drafts in the supported Disburse flow', () => {
        const wrapper = editor({
            publicMode: true,
            showEngineeringPreview: true,
            checkoutDraft: {
                reference: 'draft-1',
                guest_token: 'guest-1',
                instructions: { voucher_type: 'payable', cash: { amount: 25 }, target_amount: 25 },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-claim-experience-mode"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="cockpit-claim-experience-overview"]').text()).toContain('Disburse journey');
        expect(payload(wrapper).voucher_type).toBeUndefined();
    });

    it('requires an explicit center, compiles it into claim evidence, and tests the area', async () => {
        const wrapper = editor();
        await wrapper.get('[data-testid="cockpit-quick-generate-location-validation-enabled"]').setValue(true);
        expect(wrapper.get('[data-testid="cockpit-quick-generate-location-error"]').text()).toContain('Choose an allowed location');

        await wrapper.get('[data-testid="cockpit-quick-generate-location-latitude"]').setValue('14.5995');
        await wrapper.get('[data-testid="cockpit-quick-generate-location-longitude"]').setValue('120.9842');
        await wrapper.get('[data-testid="cockpit-quick-generate-location-radius"]').setValue('250');
        await wrapper.get('[data-testid="cockpit-quick-generate-location-test-latitude"]').setValue('14.5995');
        await wrapper.get('[data-testid="cockpit-quick-generate-location-test-longitude"]').setValue('120.9842');

        expect(wrapper.get('[data-testid="cockpit-quick-generate-location-test-result"]').text()).toBe('Inside the allowed area');
        expect(payload(wrapper).inputs.fields).toContain('location');
        expect(payload(wrapper).validation.location).toMatchObject({
            required: true,
            target_lat: 14.5995,
            target_lng: 120.9842,
            radius_meters: 250,
        });
    });

    it('shows the value flow and validates the daily claim window', async () => {
        const wrapper = editor();
        expect(wrapper.get('[data-testid="cockpit-claim-experience-overview"]').text()).toContain('Disburse journey');
        await wrapper.get('[data-testid="cockpit-quick-generate-time-validation-enabled"]').setValue(true);
        expect(wrapper.get('[data-testid="cockpit-quick-generate-time-window-example"]').text()).toContain('Asia/Manila');
        await wrapper.get('[data-testid="cockpit-quick-generate-time-test"]').setValue('10:00');
        expect(wrapper.get('[data-testid="cockpit-quick-generate-time-test-result"]').text()).toBe('Allowed claim time');
        await wrapper.get('[data-testid="cockpit-quick-generate-time-test"]').setValue('18:00');
        expect(wrapper.get('[data-testid="cockpit-quick-generate-time-test-result"]').text()).toContain('Blocked');
        await wrapper.get('[data-testid="cockpit-quick-generate-time-window-end"]').setValue('09:00');
        expect(wrapper.get('[data-testid="cockpit-quick-generate-time-window-error"]').text()).toContain('different start and end');
    });

    it('explains Collect and Settle without changing the Order or Pay Code cards', async () => {
        const wrapper = editor();
        const orderCard = wrapper.get('[data-testid="cockpit-quick-generate-order-card"]');
        expect(orderCard.find('[data-testid="cockpit-value-use-control"]').exists()).toBe(true);
        expect(orderCard.find('[data-testid="cockpit-claim-experience-navigation"]').exists()).toBe(false);
        await wrapper.get('[data-testid="cockpit-claim-experience-nav-quick-generate-contract-validation"]').trigger('click');
        expect((wrapper.get('#quick-generate-contract-validation').element as HTMLDetailsElement).open).toBe(true);

        await wrapper.get('[data-testid="cockpit-quick-generate-primary-amount"]').setValue('50');
        await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"]').get('input').setValue();
        expect(wrapper.get('[data-testid="cockpit-claim-experience-overview"]').text()).toContain('Collect journey');
        expect(payload(wrapper).target_amount).toBe(50);

        await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-settlement"]').get('input').setValue();
        await wrapper.get('[data-testid="cockpit-quick-generate-target-amount"]').setValue('75');
        expect(wrapper.get('[data-testid="cockpit-claim-experience-overview"]').text()).toContain('Settle journey');
        expect(payload(wrapper).target_amount).toBe(75);
        expect(orderCard.find('[data-testid="cockpit-value-use-control"]').exists()).toBe(true);
    });

    it('blocks a collection target from retaining a release slice plan', async () => {
        const wrapper = editor();
        await wrapper.get('[data-testid="cockpit-quick-generate-slice-mode-fixed"]').trigger('click');
        await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"]').get('input').setValue();
        expect(wrapper.get('[data-testid="cockpit-claim-experience-value-use-error"]').text()).toContain('one collection target');
        await wrapper.get('[data-testid="cockpit-quick-generate-slice-mode-whole"]').trigger('click');
        expect(wrapper.find('[data-testid="cockpit-claim-experience-value-use-error"]').exists()).toBe(false);
    });

    it('keeps the claim editor in sync with purpose and amounts from the Order card', async () => {
        const wrapper = editor();
        await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue();
        await wrapper.get('[data-testid="cockpit-quick-generate-submit-amount"]').setValue('40');
        expect(payload(wrapper).voucher_type).toBe('payable');
        expect(payload(wrapper).target_amount).toBe(40);

        await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-settlement"] input').setValue();
        await wrapper.get('[data-testid="cockpit-quick-generate-target-amount"]').setValue('60');
        expect(payload(wrapper).voucher_type).toBe('settlement');
        expect(payload(wrapper).target_amount).toBe(60);
    });

    it('explains why reusable balance is unavailable for Collect even when commissioned', async () => {
        const wrapper = editor({
            instructionCapabilities: {
                stored_value: {
                    key: 'stored_value',
                    label: 'Reusable Balance',
                    status: 'ready',
                    issuance_allowed: true,
                    claim_retryable: false,
                    missing_configuration: [],
                    source: 'wallet-stored-value',
                },
            },
        });
        await wrapper.get('[data-testid="cockpit-quick-generate-voucher-type-payable"] input').setValue();
        expect(wrapper.get('[data-testid="cockpit-claim-experience-value-use-summary"]').text()).toContain('available only for Disburse');
    });
});
