import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDashboard from '../../../resources/js/cockpit/pages/Dashboard.vue';
import DashboardRouteAdapter from '../../../resources/js/pages/x-change/cockpit/Dashboard.vue';

const dashboardReadModel = {
    status: 'available',
    authorized: true,
    metrics: [
        {
            key: 'pay-codes-visible',
            label: 'Pay Codes Visible',
            value: '4',
            helper: 'Sanitized voucher lifecycle list rows',
            tone: 'neutral',
        },
    ],
    pipeline: [
        {
            key: 'issued',
            label: 'Issued',
            value: '1',
            tone: 'neutral',
        },
    ],
    risk_signals: [
        {
            key: 'awaiting-approval',
            label: 'Awaiting Approval',
            value: '1 sanitized summaries',
            severity: 'watch',
        },
    ],
    activity: [
        {
            id: 'PC-AWAITING-001',
            label: 'PC-AWAITING-001',
            description: 'Status: awaiting_approval',
            timestamp: '2026-07-03T13:00:00+08:00',
            source: 'system',
            provider_payload: 'must-not-render',
        },
    ],
    redactions: {
        payloads: 'sanitized-dashboard-summary-only',
    },
    provider_payload: 'must-not-render',
    raw_payload: 'must-not-render',
    wallet: 'must-not-render',
};

describe('Cockpit dashboard read model hydration', () => {
    it('hydrates dashboard panels from sanitized dashboard read model props', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Codes Visible');
        expect(wrapper.text()).toContain('Sanitized voucher lifecycle list rows');
        expect(wrapper.text()).toContain('Issued');
        expect(wrapper.text()).toContain('Awaiting Approval');
        expect(wrapper.text()).toContain('PC-AWAITING-001');
        expect(wrapper.text()).toContain('Status: awaiting_approval');
        expect(wrapper.text()).toContain('2026-07-03T13:00:00+08:00');
        expect(wrapper.findAll('[data-testid="cockpit-dashboard-metric-card"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-pipeline-stage"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-risk-signal"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="cockpit-activity-item"]')).toHaveLength(1);
    });

    it('does not render unsafe dashboard payload values', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: dashboardReadModel,
            },
        });

        expect(wrapper.text()).not.toContain('must-not-render');
        expect(wrapper.text()).not.toContain('provider_payload');
        expect(wrapper.text()).not.toContain('raw_payload');
    });

    it('keeps dashboard defaults when read model is missing or not authorized', () => {
        const wrapper = mount(CockpitDashboard, {
            props: {
                dashboard_read_model: {
                    status: 'not_wired',
                    authorized: false,
                    metrics: [],
                    pipeline: [],
                    risk_signals: [],
                    activity: [],
                    redactions: {
                        payloads: 'not-loaded',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('Pending wallet read model');
        expect(wrapper.text()).toContain('No provider call in Slice 2');
        expect(wrapper.text()).toContain('Journal facts require authorization and redaction before display.');
    });

    it('forwards route adapter props into the cockpit dashboard page', () => {
        const wrapper = mount(DashboardRouteAdapter, {
            props: {
                dashboard_read_model: dashboardReadModel,
            },
        });

        expect(wrapper.text()).toContain('Pay Codes Visible');
        expect(wrapper.text()).toContain('PC-AWAITING-001');
        expect(wrapper.text()).not.toContain('must-not-render');
    });
});
