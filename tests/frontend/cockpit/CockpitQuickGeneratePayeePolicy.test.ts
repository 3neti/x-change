import { config, flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
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

function mountPanel() {
    return mount(CockpitQuickGenerateSubmitPanel, {
        props: {
            templates: cockpitQuickGenerateTemplates,
            mutationContract: {
                runtime_enabled: true,
                route: 'x-change.cockpit.quick-generate.store',
                route_url: '/x/cockpit/quick-generate',
                allowed_methods: ['POST'],
            },
        },
    });
}

function preview(wrapper: ReturnType<typeof mountPanel>): Record<string, any> {
    return JSON.parse(
        wrapper
            .get(
                '[data-testid="cockpit-quick-generate-engineering-preview-json"]',
            )
            .text(),
    );
}

describe('Cockpit Quick Generate payee policy', () => {
    it('locks mobile matching and OTP for a valid Philippine mobile', async () => {
        const wrapper = mountPanel();

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('09173011987');
        await flushPromises();

        const payload = preview(wrapper);

        expect(payload.cash.validation.mobile).toBe('+639173011987');
        expect(payload.cash.validation.mobile_verification).toEqual({});
        expect(payload.inputs.fields).toEqual(
            expect.arrayContaining(['mobile', 'otp']),
        );
        expect(payload.inputs.requirements).toContain('otp');
        expect(payload.validation.otp.required).toBe(true);
        expect(payload.feedback.mobile).toBeNull();
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-mobile-validation"]',
                )
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-otp-verification"]',
                )
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('fails closed for malformed mobile-like values', async () => {
        const wrapper = mountPanel();

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('0917301198');
        await flushPromises();

        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-primary-recipient-help"]',
                )
                .text(),
        ).toContain('complete Philippine mobile');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('treats a quoted mobile as a redacted release secret', async () => {
        const wrapper = mountPanel();

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('"09173011987"');
        await flushPromises();

        const payload = preview(wrapper);

        expect(payload.cash.validation.secret).toBe('[redacted secret]');
        expect(payload.cash.validation.mobile).toBeUndefined();
        expect(payload.metadata.custom.cockpit.payee).toEqual({
            kind: 'secret',
            explicit_secret: true,
        });
        expect(JSON.stringify(payload)).not.toContain('09173011987');
    });

    it('recognizes email but keeps issuance unavailable until email OTP exists', async () => {
        const wrapper = mountPanel();

        await wrapper
            .get('[data-testid="cockpit-quick-generate-primary-recipient"]')
            .setValue('person@example.test');
        await flushPromises();

        expect(preview(wrapper).inputs.fields).toContain('email');
        expect(
            wrapper
                .get(
                    '[data-testid="cockpit-quick-generate-primary-recipient-help"]',
                )
                .text(),
        ).toContain('email OTP capability');
        expect(
            wrapper
                .get('[data-testid="cockpit-quick-generate-submit-button"]')
                .attributes('disabled'),
        ).toBeDefined();
    });
});
