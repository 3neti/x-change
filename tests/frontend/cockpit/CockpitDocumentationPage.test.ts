import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CockpitDocumentation from '../../../resources/js/cockpit/pages/Documentation.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: {
        props: ['title'],
        template: '<span data-testid="inertia-head" />',
    },
    Link: {
        props: ['href'],
        template: '<a :href="href?.url ?? href"><slot /></a>',
    },
}));

const documentation = {
    schema: 'x-change.cockpit.documentation.v2',
    hero: {
        eyebrow: 'Beta Playbook',
        title: 'Run X-Change with confidence',
        description:
            'How to fund, issue, collect, pay, monitor, and recover Pay Codes safely during beta testing.',
        primary_action: {
            label: 'Issue a Pay Code',
            description: 'Create a Pay Code.',
            href: '/x/cockpit/quick-generate',
        },
        secondary_action: {
            label: 'Review claimed vouchers',
            description: 'Review claimed vouchers.',
            href: '/x/cockpit/pay-codes?status=redeemed',
        },
    },
    start_here: [
        {
            key: 'pay-code',
            title: 'Pay Codes carry intent',
            description: 'A Pay Code can disburse funds or collect payment.',
            href: '/x/cockpit/pay-codes',
        },
        {
            key: 'fund-first',
            title: 'Funding is the first safety gate',
            description: 'Client Funds and Issuance Capacity tell operators what can safely be issued.',
            href: '/x/cockpit/funding',
        },
        {
            key: 'claimed-center-stage',
            title: 'Claimed vouchers matter most',
            description: 'Claimed date, amount, recipient, evidence, and journal events become important.',
            href: '/x/cockpit/overview',
        },
    ],
    playbooks: [
        {
            key: 'daily-operator',
            title: 'Daily Operator Workflows',
            description: 'Common actions for Amelia.',
            links: [
                {
                    label: 'Add or confirm funds',
                    description: 'Use QR Ph funding.',
                    href: '/x/cockpit/funding',
                },
                {
                    label: 'Issue one Pay Code',
                    description: 'Enter Amount first.',
                    href: '/x/cockpit/quick-generate',
                },
                {
                    label: 'Run POS mode',
                    description: 'Create payable QR Ph vouchers.',
                    href: '/x/cockpit/quick-generate?surface=pos',
                },
                {
                    label: 'Inspect Pay Codes',
                    description: 'Find status and evidence.',
                    href: '/x/cockpit/pay-codes',
                },
            ],
        },
        {
            key: 'campaigns',
            title: 'Campaigns, Payroll, and Ayuda',
            description: 'Batch payout operations.',
            links: [
                {
                    label: 'Prepare a payroll run',
                    description: 'Import payroll rows.',
                    href: '/x/cockpit/campaigns',
                },
                {
                    label: 'Recover failed transfers',
                    description: 'Notify the recipient to claim recovery.',
                    href: '/x/cockpit/campaigns',
                },
            ],
        },
        {
            key: 'evidence',
            title: 'Evidence and Safety',
            description: 'Check before deciding money moved.',
            links: [
                {
                    label: 'Claim & Evidence',
                    description: 'See when, where, how much, and by whom.',
                    href: '/x/cockpit/pay-codes',
                },
                {
                    label: 'System Readiness',
                    description: 'Hidden for most workspaces.',
                    href: '/x/cockpit/diagnostics/runtime-profile',
                    visible: false,
                },
            ],
        },
    ],
    lifecycle: [
        {
            key: 'issued',
            label: 'Issued',
            description: 'The instruction exists.',
        },
        {
            key: 'claimed',
            label: 'Claimed / Paid / Redeemed',
            description:
                'Move claimed facts to center stage: amount, time, place, recipient, and evidence.',
        },
    ],
    safety_notes: [
        {
            key: 'funding',
            title: 'Provider evidence beats assumptions',
            description: 'Do not trust local balances without provider evidence.',
        },
        {
            key: 'journal',
            title: 'Journal every material event',
            description: 'CSV staged/applied, approval, issuance, provider success/failure, SMS, recovery, and claims should be traceable.',
        },
    ],
    builder_links: [
        {
            label: 'Getting Started',
            description: 'Adopt x-change in Laravel.',
            href: 'https://github.com/3neti/x-change/blob/main/GETTING_STARTED.md',
            external: true,
        },
        {
            label: 'System Readiness',
            description: 'Hidden in this fixture.',
            href: '/x/cockpit/diagnostics/runtime-profile',
            visible: false,
        },
    ],
};

describe('Cockpit documentation beta playbook', () => {
    it('renders operator-first guides before technical references', () => {
        const wrapper = mount(CockpitDocumentation, {
            props: { documentation },
        });

        expect(wrapper.get('[data-testid="documentation-hero"]').text()).toContain(
            'Run X-Change with confidence',
        );
        expect(
            wrapper.findAll('[data-testid="documentation-start-card"]'),
        ).toHaveLength(3);
        expect(
            wrapper.findAll('[data-testid="documentation-playbook"]'),
        ).toHaveLength(3);
        expect(wrapper.get('[data-testid="documentation-playbooks"]').text()).toContain(
            'Daily Operator Workflows',
        );
        expect(wrapper.get('[data-testid="documentation-playbooks"]').text()).toContain(
            'Campaigns, Payroll, and Ayuda',
        );
        expect(wrapper.get('[data-testid="documentation-lifecycle"]').text()).toContain(
            'Claimed / Paid / Redeemed',
        );
        expect(wrapper.get('[data-testid="documentation-safety"]').text()).toContain(
            'Journal every material event',
        );
        expect(wrapper.get('[data-testid="documentation-builder-links"]').text()).toContain(
            'Getting Started',
        );
    });

    it('keeps hidden diagnostics links out of the visible guide', () => {
        const wrapper = mount(CockpitDocumentation, {
            props: { documentation },
        });

        expect(wrapper.text()).not.toContain('Hidden for most workspaces.');
        expect(wrapper.text()).not.toContain('Hidden in this fixture.');
        expect(wrapper.text()).not.toContain('credentials');
        expect(wrapper.text()).not.toContain('secrets');
    });
});
