import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CommercialPayCodeScenarioRunner from '../../../resources/js/cockpit/pages/CommercialPayCodeScenarioRunner.vue';

const routerPostMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
    router: { post: routerPostMock },
}));

const baseProps = {
    commercial_principal: {
        reference: 'commercial-primary',
        legal_name: '3neti R&D OPC',
        active: true,
        funding_source: 'Commercial Principal Client Funds',
        revenue_account_excluded: true,
        balances: {
            client_funds_minor: 10000,
            pay_code_reserve_minor: 0,
            revenue_minor: 0,
        },
    },
    maker: { id: '10', name: 'Maker One', mobile: '09170000001' },
    maker_ready: true,
    checkers: [{ id: '11', name: 'Checker One', mobile: '09170000002' }],
    run: null,
};

describe('Commercial Pay Code scenario runner', () => {
    it('shows the Commercial Principal, Maker, Checker, and revenue exclusion', () => {
        const wrapper = mount(CommercialPayCodeScenarioRunner, { props: baseProps });

        expect(wrapper.text()).toContain('3neti R&D OPC');
        expect(wrapper.text()).toContain('Maker One');
        expect(wrapper.text()).toContain('Checker One');
        expect(wrapper.text()).toContain('Commercial Revenue Account stays at ₱0.00');
        expect(wrapper.text()).toContain('₱50.00');
    });

    it('submits the sealed maker instruction for approval', async () => {
        const wrapper = mount(CommercialPayCodeScenarioRunner, { props: baseProps });

        await wrapper.get('[data-testid="commercial-pay-code-prepare"]').trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/x/cockpit/campaigns/commercial-pay-code-scenario-runner',
            {
                phase: 'prepare',
                checker: '11',
                run_reference: null,
                amount: 50,
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows approval and issuance artifacts after preparation', () => {
        const wrapper = mount(CommercialPayCodeScenarioRunner, {
            props: {
                ...baseProps,
                run: {
                    reference: 'COMM-TEST',
                    phase: 'awaiting_checker',
                    amount: '₱50.00',
                    worksheet_reference: 'worksheet-1',
                    approval_pay_code: 'APPR-1234',
                    authorization_reference: 'auth-1',
                    funding_source: 'Commercial Principal Client Funds',
                    pay_code: null,
                    steps: [
                        { label: 'Maker prepared frozen instruction', complete: true },
                        { label: 'Checker independently approved', complete: false },
                    ],
                },
            },
        });

        expect(wrapper.text()).toContain('APPR-1234');
        expect(wrapper.text()).toContain('Awaiting Checker');
        expect(wrapper.get('[data-testid="commercial-pay-code-approve"]').text()).toContain('Approve & issue');
    });

    it('offers a controlled cancellation after issuance', async () => {
        const wrapper = mount(CommercialPayCodeScenarioRunner, {
            props: {
                ...baseProps,
                run: {
                    reference: 'COMM-ISSUED',
                    phase: 'issued',
                    amount: '₱50.00',
                    worksheet_reference: 'worksheet-2',
                    approval_pay_code: 'APPR-5678',
                    authorization_reference: 'auth-2',
                    funding_source: 'Commercial Principal Client Funds',
                    pay_code: 'CAMP-1234',
                    steps: [],
                },
            },
        });

        await wrapper.get('[data-testid="commercial-pay-code-cancel"]').trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/x/cockpit/campaigns/commercial-pay-code-scenario-runner',
            {
                phase: 'cancel',
                checker: '11',
                run_reference: 'COMM-ISSUED',
                amount: 50,
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
