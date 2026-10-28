import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XRayClaimPreview from '../../resources/js/components/x-change/XRayClaimPreview.vue';

vi.mock('lucide-vue-next', () => ({
    AlertCircle: {
        template: '<span />',
    },
    Calendar: {
        template: '<span />',
    },
    CheckCircle2: {
        template: '<span />',
    },
    Clock: {
        template: '<span />',
    },
    HelpCircle: {
        template: '<span />',
    },
    Home: {
        template: '<span />',
    },
    XCircle: {
        template: '<span />',
    },
    Camera: {
        template: '<span />',
    },
    IdCard: {
        template: '<span />',
    },
    KeyRound: {
        template: '<span />',
    },
    Mail: {
        template: '<span />',
    },
    MapPin: {
        template: '<span />',
    },
    PenLine: {
        template: '<span />',
    },
    ShieldCheck: {
        template: '<span />',
    },
    Smartphone: {
        template: '<span />',
    },
    User: {
        template: '<span />',
    },
    Wallet: {
        template: '<span />',
    },
}));

describe('XRayClaimPreview', () => {
    it('presents disclosed money details and service information in separate tabs', async () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    disclosures: [
                        { key: 'amount', value: '₱50.00' },
                        { key: 'reference', label: 'Pay Code', value: 'JK33' },
                        { key: 'expires_at', value: '2026-10-29T08:33:00+08:00' },
                        { key: 'issuer', label: 'Issuer', value: 'Amelia Cruz' },
                        { key: 'provider', label: 'Payout provider', value: 'NetBank' },
                        { key: 'service_info', value: { host_name: 'x-change', publisher: '3neti', x_change_version: 'v1.1.2' } },
                    ],
                    requirements: [{ key: 'mobile', label: 'Mobile' }],
                },
            },
        });

        expect(wrapper.get('[data-testid="xray-claim-amount"]').text()).toContain('₱50.00');
        expect(wrapper.get('[data-testid="xray-claim-amount"]').text()).toContain('|| JK33 ||');
        expect(wrapper.get('[data-testid="xray-validity"]').text()).toContain('Expires on');
        expect(wrapper.get('[data-testid="xray-required-inputs"]').text()).toContain('Mobile number');
        expect(wrapper.get('#xray-instructions-panel').isVisible()).toBe(true);
        expect(wrapper.get('#xray-system-info-panel').isVisible()).toBe(false);

        await wrapper.get('#xray-system-info-tab').trigger('click');

        expect(wrapper.get('#xray-system-info-tab').attributes('aria-selected')).toBe('true');
        expect(wrapper.get('#xray-system-info-panel').attributes('style')).not.toContain('display: none');
        expect(wrapper.get('#xray-system-info-panel').text()).toContain('Amelia Cruz');
        expect(wrapper.get('#xray-system-info-panel').text()).toContain('NetBank');
        expect(wrapper.get('#xray-system-info-panel').text()).toContain('3neti');
        expect(wrapper.get('#xray-instructions-panel').attributes('style')).toContain('display: none');
    });

    it('renders a verified Pay Code panel without repeating the claim action', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    disclosures: [
                        {
                            key: 'status',
                            label: 'Status',
                            value: 'claimable',
                        },
                    ],
                    requirements: [],
                },
            },
        });

        expect(wrapper.text()).toContain('Pay Code verified');
        expect(wrapper.text()).not.toContain('Ready to claim');
        expect(wrapper.find('[data-testid="xray-status-description"]').exists()).toBe(false);
        // The raw status disclosure row is redundant with the friendly badge
        // above and should not be dumped verbatim.
        expect(wrapper.text()).not.toContain('claimable');
    });

    it('renders friendly status labels for every claimability state', () => {
        const cases: Array<[string, string]> = [
            ['claimable', 'Pay Code verified'],
            ['partially_claimable', 'Partially claimable'],
            ['redeemed', 'Already claimed'],
            ['expired', 'Expired'],
            ['hidden', 'Unavailable'],
            ['not_found', 'Not found'],
            ['needs_attention', 'Needs attention'],
        ];

        for (const [status, label] of cases) {
            const wrapper = mount(XRayClaimPreview, {
                props: { result: { visible: status !== 'not_found', status } },
            });

            expect(wrapper.text()).toContain(label);
        }
    });

    it('renders readable input rows and separates security requirements', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    requirements: [
                        {
                            key: 'mobile',
                            label: 'Mobile',
                            description: 'Mobile number is required.',
                        },
                        {
                            key: 'otp',
                            label: 'Otp',
                        },
                        {
                            key: 'secret',
                            label: 'Secret / PIN',
                            description: 'A matching issuer-provided secret is required.',
                        },
                    ],
                },
            },
        });

        expect(wrapper.get('[data-testid="xray-required-inputs"]').text()).toContain('Required Inputs');
        expect(wrapper.get('[data-testid="xray-required-inputs"]').text()).toContain('Mobile number');
        expect(wrapper.get('[data-testid="xray-required-inputs"]').text()).toContain('OTP');
        expect(wrapper.get('[data-testid="xray-security-requirements"]').text()).toContain('Passcode required');
        expect(wrapper.get('[data-testid="xray-security-requirements"]').text()).toContain('A matching issuer-provided secret is required.');
        expect(wrapper.get('[data-testid="xray-required-inputs"]').text()).not.toContain('Secret / PIN');
        // Friendly copy replaces debug-style backend labels/keys.
        expect(wrapper.text()).not.toContain('Otp');
        expect(wrapper.text()).not.toContain('mobile');
    });

    it('does not invent validity or security details when they were not disclosed', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    requirements: [],
                },
            },
        });

        expect(wrapper.get('[data-testid="xray-no-requirements"]').text()).toContain('No additional information required');
        expect(wrapper.find('[data-testid="xray-validity"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="xray-security-requirements"]').exists()).toBe(false);
    });

    it('renders issuer preview stages under a human heading', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    stages: [
                        {
                            type: 'message',
                            payload: {
                                message: 'Issuer preview message.',
                            },
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Note from the issuer');
        expect(wrapper.text()).toContain('Issuer preview message.');
    });

    it('shows labeled slice choices on the first claim preview', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'partially_claimable',
                    disclosures: [
                        {
                            key: 'remaining_slices',
                            label: 'Remaining Slices',
                            value: [
                                {
                                    id: 'slice_1',
                                    label: 'Morning fare',
                                    amount_minor: 2500,
                                    status: 'succeeded',
                                    status_label: 'Paid',
                                    claimed_at: '2026-08-20T06:56:25+00:00',
                                },
                                {
                                    id: 'remaining_capacity',
                                    label: 'Remaining capacity',
                                    amount_minor: 25000,
                                    status: 'available',
                                    status_label: 'Available',
                                    max_slices: 2,
                                    min_amount_minor: 2500,
                                    claims_used: 1,
                                    claims_remaining: 1,
                                    is_final_claim: true,
                                },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.get('[data-testid="xray-slice-plan"]').text()).toContain(
            'Slices',
        );
        expect(wrapper.text()).toContain('Morning fare');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).toContain('Claimed');
        expect(wrapper.text()).toContain('₱250.00');
        expect(
            wrapper.get('[data-testid="xray-flexible-plan-summary"]').text(),
        ).toContain('Claims remaining');
        expect(wrapper.text()).toContain('1 of 2');
        expect(wrapper.text()).toContain('Minimum claim');
        expect(wrapper.text()).toContain('₱25.00');
        expect(wrapper.text()).toContain(
            'Final claim — use the full remaining balance.',
        );
        expect(wrapper.text()).not.toContain('Remaining capacity');
        expect(wrapper.text()).not.toContain('[object Object]');
    });

    it('defers slice rendering to the canonical selector when it is present', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                hideSlicePlan: true,
                result: {
                    visible: true,
                    status: 'claimable',
                    disclosures: [
                        {
                            key: 'remaining_slices',
                            value: [
                                { id: 'slice_1', label: 'Morning fare', amount_minor: 2500 },
                            ],
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Pay Code verified');
        expect(wrapper.find('[data-testid="xray-slice-plan"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Morning fare');
    });

    it('does not render a disclosure-style redaction footer on the claim page', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    redactions: [{ key: 'amount' }],
                },
            },
        });

        expect(wrapper.find('[data-testid="xray-redaction-footer"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Private issuer');
        expect(wrapper.text()).not.toContain('protected until they are needed');
        expect(wrapper.find('[data-testid="alert"]').exists()).toBe(false);
    });

    it('renders no redaction footer at all when nothing was redacted', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    redactions: [],
                },
            },
        });

        expect(wrapper.find('[data-testid="xray-redaction-footer"]').exists()).toBe(false);
    });

    it('renders inspection errors', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                error: 'Unable to inspect this Pay Code.',
            },
        });

        expect(wrapper.text()).toContain('Unable to inspect this Pay Code.');
    });

    it('summarizes html preview stages as readable text', () => {
        const wrapper = mount(XRayClaimPreview, {
            props: {
                result: {
                    visible: true,
                    status: 'claimable',
                    stages: [
                        {
                            type: 'splash',
                            payload: {
                                content_type: 'html',
                                content: '<h1>Claim walkthrough</h1><p>This splash confirms the Pay Code.</p>',
                            },
                        },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('Claim walkthrough This splash confirms the Pay Code.');
        expect(wrapper.text()).not.toContain('<h1>');
    });
});
