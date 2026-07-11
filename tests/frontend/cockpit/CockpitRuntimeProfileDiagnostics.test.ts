import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RuntimeProfile from '../../../resources/js/cockpit/pages/RuntimeProfile.vue';
import RouteRuntimeProfile from '../../../resources/js/pages/x-change/cockpit/RuntimeProfile.vue';

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
    copy: {
        eyebrow: 'Wave 21 · Runtime diagnostics',
        title: 'Operator Activity Runtime Profile',
        description: 'Read-only visibility into Cockpit operator activity runtime configuration.',
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
    it('renders runtime profile status, components, and safety facts', () => {
        const wrapper = mount(RuntimeProfile, {
            props: {
                runtime_profile_read_model: runtimeProfileReadModel,
            },
        });

        const text = wrapper.text();

        expect(text).toContain('Operator Activity Runtime Profile');
        expect(text).toContain('partially_wired');
        expect(text).toContain('repository');
        expect(text).toContain('journal_handoff');
        expect(text).toContain('Durable activity read storage');
        expect(text).toContain('NullCockpitOperatorIssuanceActivityJournalHandoff');
        expect(text).toContain('This diagnostics surface is read-only');
        expect(text).toContain('Runtime capabilities remain explicit opt-in');
        expect(wrapper.findAll('[data-testid="cockpit-runtime-profile-summary-card"]')).toHaveLength(4);
        expect(wrapper.findAll('[data-testid="cockpit-runtime-profile-component"]')).toHaveLength(2);
    });

    it('does not render mutation affordances or unsafe payload labels', () => {
        const wrapper = mount(RuntimeProfile, {
            props: {
                runtime_profile_read_model: runtimeProfileReadModel,
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
        });

        expect(wrapper.find('[data-testid="cockpit-runtime-profile-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Operator Activity Runtime Profile');
        expect(wrapper.text()).toContain('partially_wired');
    });
});
