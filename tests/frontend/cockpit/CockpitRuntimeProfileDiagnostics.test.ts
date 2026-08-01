import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RuntimeProfile from '../../../resources/js/cockpit/pages/RuntimeProfile.vue';
import RouteRuntimeProfile from '../../../resources/js/pages/x-change/cockpit/RuntimeProfile.vue';

const routerReloadMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/vue3')>()),
    Head: { template: '<div><slot /></div>' },
    router: { reload: routerReloadMock },
}));

const runtimeProfileReadModel = {
    schema: 'x-change.cockpit.runtime-profile-page.v1',
    status: 'available',
    authorized: true,
    read_only: true,
    profile: {
        schema: 'x-change.cockpit.operator-issuance-activity-runtime-profile.v1',
        status: 'partially_wired',
        repository_enabled: true,
        recorder_enabled: true,
        journal_handoff_enabled: false,
        action_handoff_enabled: false,
        feedback_handoff_enabled: false,
        components: [
            {
                key: 'repository',
                configured: 'database',
                enabled: true,
                resolved_class: 'LBHurtado\\XChange\\Services\\Cockpit\\DatabaseCockpitOperatorIssuanceActivityRepository',
                fallback_class: 'LBHurtado\\XChange\\Services\\Cockpit\\NullCockpitOperatorIssuanceActivityRepository',
                uses_fallback: false,
                purpose: 'Durable activity read storage',
            },
            {
                key: 'journal_handoff',
                configured: null,
                enabled: false,
                resolved_class: 'LBHurtado\\XChange\\Services\\Cockpit\\NullCockpitOperatorIssuanceActivityJournalHandoff',
                fallback_class: 'LBHurtado\\XChange\\Services\\Cockpit\\NullCockpitOperatorIssuanceActivityJournalHandoff',
                uses_fallback: true,
                purpose: 'x-journal evidence handoff',
            },
        ],
        safety: {
            defaults_safe: false,
            requires_explicit_opt_in: true,
            moves_money: false,
            calls_provider: false,
            executes_action: false,
            sends_feedback: false,
            writes_journal: false,
            owns_lifecycle_truth: false,
        },
    },
    system_readiness: {
        schema: 'x-change.cockpit.system-readiness.v1',
        status: 'operational',
        checked_at: '2026-08-01T19:30:00+08:00',
        summary: {
            ready: 11,
            total: 11,
            attention: 0,
        },
        context: {
            environment: 'local',
            profile: 'netbank',
            active_connections: ['netbank-primary'],
            active_providers: ['netbank'],
        },
        sections: [
            {
                key: 'deployment',
                label: 'Deployment',
                description: 'Identity, application security, and installation state.',
                status: 'ready',
                checks: [
                    {
                        name: 'installation manifest',
                        passed: true,
                        message: 'recorded installation matches the active deployment configuration',
                    },
                ],
            },
            {
                key: 'runtime',
                label: 'Runtime Services',
                description: 'Durable work, scheduling locks, and live updates.',
                status: 'ready',
                checks: [
                    {
                        name: 'durable queue runtime',
                        passed: true,
                        message: 'queue connection [database] is ready',
                    },
                ],
            },
            {
                key: 'delivery',
                label: 'Delivery And Access',
                description: 'Recipient verification and communication channels.',
                status: 'ready',
                checks: [
                    {
                        name: 'SMS delivery',
                        passed: true,
                        message: 'SMS delivery uses [engagespark]',
                    },
                ],
            },
        ],
        providers: {
            status: 'ready',
            active: ['netbank'],
            connections: ['netbank-primary'],
            installed_but_disabled: ['paynamics'],
            capabilities: {
                'netbank-primary': { ready: true, missing: [] },
            },
        },
        runtime_processes: {
            queues: ['x-change-funding', 'x-change-feedback', 'default'],
            local: {
                queue: 'php artisan queue:work database --queue=x-change-funding,x-change-feedback,default',
                scheduler: 'php artisan schedule:work',
            },
            cloud: [],
            forge: [],
            broadcasting_required: false,
        },
        technical: {
            operator_activity: {
                schema: 'x-change.cockpit.operator-issuance-activity-runtime-profile.v1',
                status: 'partially_wired',
                repository_enabled: true,
                recorder_enabled: true,
                journal_handoff_enabled: false,
                action_handoff_enabled: false,
                feedback_handoff_enabled: false,
                components: [
                    {
                        key: 'repository',
                        configured: 'database',
                        enabled: true,
                        resolved_class: 'DatabaseRepository',
                        fallback_class: 'NullRepository',
                        uses_fallback: false,
                        purpose: 'Durable activity read storage',
                    },
                ],
                safety: {},
            },
            legacy_published_config: false,
        },
        redactions: {
            secrets_exposed: false,
            credentials_exposed: false,
            account_numbers_exposed: false,
            provider_payloads_exposed: false,
            raw_responses_exposed: false,
            performs_live_provider_checks: false,
        },
    },
    copy: {
        eyebrow: 'Operations',
        title: 'System Readiness',
        description: 'Deployment, providers, delivery, and background-work readiness in one safe view.',
    },
    safety: {
        mutates_configuration: false,
        enables_handoffs: false,
        writes_journal: false,
        executes_action: false,
        sends_feedback: false,
        calls_provider: false,
        moves_money: false,
        owns_lifecycle_truth: false,
    },
    redactions: {
        payloads: 'runtime-configuration-class-names-only',
    },
};

describe('Cockpit runtime profile diagnostics', () => {
    it('renders standardized system readiness and keeps technical wiring collapsed', () => {
        const wrapper = mount(RuntimeProfile, {
            props: {
                runtime_profile_read_model: runtimeProfileReadModel,
            },
            global: {
                stubs: { Head: true },
            },
        });

        const text = wrapper.text();

        expect(text).toContain('System Readiness');
        expect(text).toContain('Operational');
        expect(text).toContain('Netbank');
        expect(text).toContain('Providers And Connections');
        expect(text).toContain('Deployment');
        expect(text).toContain('Runtime Services');
        expect(text).toContain('Delivery And Access');
        expect(text).toContain('Run Checks');
        expect(text).toContain('Local Development');
        expect(wrapper.findAll('[data-testid="cockpit-system-readiness-section"]')).toHaveLength(3);
        expect(wrapper.find('[data-testid="cockpit-system-readiness-runtime-processes"]').attributes('open')).toBeUndefined();
        expect(wrapper.find('[data-testid="cockpit-system-readiness-technical"]').attributes('open')).toBeUndefined();
        expect(text).not.toContain('Wave 21');
        expect(text).not.toContain('defaults_safe');
    });

    it('reruns safe read-model checks without invoking an operational action', async () => {
        const wrapper = mount(RuntimeProfile, {
            props: {
                runtime_profile_read_model: runtimeProfileReadModel,
            },
            global: {
                stubs: { Head: true },
            },
        });

        await wrapper
            .find('[data-testid="cockpit-system-readiness-refresh"]')
            .trigger('click');

        expect(routerReloadMock).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['runtime_profile_read_model'],
                preserveScroll: true,
            }),
        );
    });

    it('does not render mutation affordances or unsafe payload labels', () => {
        const wrapper = mount(RuntimeProfile, {
            props: {
                runtime_profile_read_model: runtimeProfileReadModel,
            },
            global: {
                stubs: { Head: true },
            },
        });

        const text = wrapper.text();

        expect(text).not.toContain('Enable handoffs');
        expect(text).not.toContain('Save configuration');
        expect(text).not.toContain('provider_payload');
        expect(text).not.toContain('raw_payload');
        expect(text).not.toContain('wallet_data');
    });

    it('forwards route adapter props into the runtime profile page', () => {
        const wrapper = mount(RouteRuntimeProfile, {
            props: {
                runtime_profile_read_model: runtimeProfileReadModel,
            },
            global: {
                stubs: { Head: true },
            },
        });

        expect(wrapper.find('[data-testid="cockpit-runtime-profile-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('System Readiness');
        expect(wrapper.text()).toContain('Operational');
    });
});
