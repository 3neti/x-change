import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CockpitIssuedPayCodeDialog from '../../../resources/js/cockpit/components/CockpitIssuedPayCodeDialog.vue';
import CockpitQuickGenerateSubmitPanel from '../../../resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue';
import { cockpitQuickGenerateTemplates } from '../../../resources/js/cockpit/quickGenerateDefaults';

vi.mock('@inertiajs/vue3', () => ({
    router: {
        reload: vi.fn(),
    },
}));

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('issued Pay Code dialog', () => {
    it('renders the finalized front and back canvas with safe share fallbacks', async () => {
        const clipboardWrite = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            clipboard: {
                writeText: clipboardWrite,
            },
        });

        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'PAY-READY-7',
                amount: '125.50',
                currency: 'PHP',
                recipient: '09173011987',
                purpose: 'Family support',
                claimOutcome: 'provider_disbursement',
                voucherType: 'redeemable',
                expiry: '1 day',
                instructionLabels: ['Mobile Required', 'OTP Required'],
                costEstimate: {
                    currency: 'PHP',
                    charges: [
                        {
                            label: 'Pay Code Generation',
                            type: 'generation',
                            price: 12,
                        },
                        {
                            label: 'Selfie Verification',
                            type: 'selfie',
                            price: 5,
                        },
                    ],
                    total: 17,
                },
                claimUrl: 'https://example.test/x/claim/PAY-READY-7/experience',
                detailUrl: '/x/cockpit/pay-codes/PAY-READY-7',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        expect(wrapper.get('[role="dialog"]').attributes('aria-modal')).toBe(
            'true',
        );
        expect(wrapper.text()).toContain('Pay Code PAY-READY-7 Is Ready');
        expect(wrapper.text()).toContain('Issued Pay Code');
        expect(wrapper.text()).toContain('Final design ready to share.');
        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-detail"]')
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PAY-READY-7');

        const whatsapp = wrapper
            .get('[data-testid="cockpit-issued-pay-code-whatsapp"]')
            .attributes('href');
        const sms = wrapper
            .get('[data-testid="cockpit-issued-pay-code-sms"]')
            .attributes('href');
        const email = wrapper
            .get('[data-testid="cockpit-issued-pay-code-email"]')
            .attributes('href');

        expect(decodeURIComponent(whatsapp)).toContain(
            'Claim this Pay Code: PAY-READY-7 (PHP 125.50)',
        );
        expect(decodeURIComponent(whatsapp)).toContain(
            'https://example.test/x/claim/PAY-READY-7/experience',
        );
        expect(sms).toContain('sms:?body=');
        expect(email).toContain('mailto:?subject=');

        await wrapper
            .get('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-pay-code-canvas-back"]')
                .isVisible(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Issue Cost');
        expect(wrapper.text()).toContain('Pay Code Generation');
        expect(wrapper.text()).toContain('₱12.00');
        expect(wrapper.text()).toContain('Selfie Verification');
        expect(wrapper.text()).toContain('5.00');
        expect(wrapper.text()).toContain('₱17.00');
        expect(wrapper.text().match(/₱/g) ?? []).toHaveLength(2);

        await wrapper
            .get('[data-testid="cockpit-issued-pay-code-copy"]')
            .trigger('click');

        expect(clipboardWrite).toHaveBeenCalledWith(
            'https://example.test/x/claim/PAY-READY-7/experience',
        );
        expect(wrapper.text()).toContain('Copied');
    });

    it('uses native device sharing when the browser supports it', async () => {
        const nativeShare = vi.fn().mockResolvedValue(undefined);

        vi.stubGlobal('navigator', {
            share: nativeShare,
        });

        const wrapper = mount(CockpitIssuedPayCodeDialog, {
            props: {
                open: true,
                code: 'FUND-NATIVE-1',
                amount: 50,
                currency: 'PHP',
                claimOutcome: 'account_funding',
                voucherType: 'redeemable',
                claimUrl:
                    'https://example.test/x/claim/FUND-NATIVE-1/experience',
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-issued-pay-code-native-share"]')
            .trigger('click');

        expect(nativeShare).toHaveBeenCalledWith({
            title: 'Pay Code FUND-NATIVE-1',
            text: expect.stringContaining(
                'Add this Pay Code to your Account: FUND-NATIVE-1',
            ),
            url: 'https://example.test/x/claim/FUND-NATIVE-1/experience',
        });
    });

    it('opens automatically with the canonical result after issuance', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({
                status: 'issued',
                result: {
                    code: 'PAY-MODAL-1',
                    issue_cost: {
                        currency: 'PHP',
                        charges: [
                            {
                                label: 'Pay Code Generation',
                                type: 'generation',
                                price: 12,
                            },
                        ],
                        total: 12,
                    },
                    links: {
                        redeem: 'https://example.test/x/claim/PAY-MODAL-1/experience',
                        redeem_path: '/x/claim/PAY-MODAL-1/experience',
                        cockpit_detail: '/x/cockpit/pay-codes/PAY-MODAL-1',
                    },
                },
            }),
        });

        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('crypto', {
            randomUUID: () => 'cockpit-issued-dialog-idempotency',
        });

        const wrapper = mount(CockpitQuickGenerateSubmitPanel, {
            props: {
                templates: cockpitQuickGenerateTemplates,
                mutationContract: {
                    runtime_enabled: true,
                    route: 'x-change.cockpit.quick-generate.store',
                    route_url: '/x/cockpit/quick-generate',
                    allowed_methods: ['POST'],
                },
            },
            global: {
                stubs: {
                    Teleport: true,
                },
            },
        });

        await wrapper
            .get('[data-testid="cockpit-quick-generate-submit-panel"]')
            .trigger('submit');
        await Promise.resolve();
        await Promise.resolve();
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-dialog"]')
                .text(),
        ).toContain('Pay Code PAY-MODAL-1 Is Ready');
        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-detail"]')
                .attributes('href'),
        ).toBe('/x/cockpit/pay-codes/PAY-MODAL-1');

        await wrapper
            .findAll('[data-testid="cockpit-pay-code-canvas-back-button"]')
            .at(-1)
            ?.trigger('click');

        expect(
            wrapper
                .get('[data-testid="cockpit-issued-pay-code-dialog"]')
                .text(),
        ).toContain('₱12.00');
    });
});
