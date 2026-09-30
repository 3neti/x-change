import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PublicAutoGenerateScenarioRunner from '../../../resources/js/cockpit/pages/PublicAutoGenerateScenarioRunner.vue';

const routerPostMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
    router: { post: routerPostMock },
}));

describe('Public Auto-Generate scenario runner', () => {
    it('starts the rollback-only lifecycle from the browser', async () => {
        const wrapper = mount(PublicAutoGenerateScenarioRunner, {
            props: { run: null },
        });

        expect(wrapper.text()).toContain('Rollback-only browser lifecycle');
        expect(wrapper.text()).toContain('No provider is called');

        await wrapper.get('[data-testid="public-auto-generate-run"]').trigger('click');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/x/cockpit/campaigns/public-auto-generate-scenario-runner',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('renders the safe result, steps, and rolled-back artifacts', () => {
        const wrapper = mount(PublicAutoGenerateScenarioRunner, {
            props: {
                run: {
                    success: true,
                    message: 'Rollback-only public Auto-Generate lifecycle completed.',
                    rollback_completed: true,
                    simulation: {
                        rollback_only: true,
                        provider_calls: 0,
                        monetary_value: false,
                        persisted: false,
                    },
                    steps: [
                        {
                            key: 'principal_resolved',
                            label: 'Commercial Principal resolved by the server',
                            outcome: 'protected',
                            facts: [{ label: 'Browser selectable', value: 'No' }],
                        },
                        {
                            key: 'pay_code_ready',
                            label: 'Exactly one Pay Code and normal stamp/share result are produced',
                            outcome: 'ready',
                            facts: [{ label: 'Issued once', value: 'Yes' }],
                        },
                    ],
                    artifacts: {
                        commercial_principal_reference: 'commercial-primary',
                        order_reference: 'ORDER-ROLLBACK',
                        pay_code: 'PUBLIC-TEST',
                        claim_qr_present: true,
                        share_result_present: true,
                        expected_payment_minor: 4300,
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('₱43.00');
        expect(wrapper.text()).toContain('Commercial Principal resolved by the server');
        expect(wrapper.text()).toContain('Exactly one Pay Code');
        expect(wrapper.text()).toContain('Rollback confirmed: Yes');
        expect(wrapper.text()).toContain('Provider calls: 0');
        expect(wrapper.text()).toContain('PUBLIC-TEST');
        expect(wrapper.get('[data-testid="public-auto-generate-artifacts"]').text()).toContain(
            'Stamp/share projected',
        );
    });
});
