import { mount } from '@vue/test-utils';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import Campaigns from '../../../resources/js/cockpit/pages/Campaigns.vue';
import CampaignsRouteAdapter from '../../../resources/js/pages/x-change/cockpit/Campaigns.vue';

const worksheet = {
    reference: '01KYCAMPAIGNWORKSHEET000000',
    profile: 'payroll' as const,
    name: 'July Payroll',
    currency: 'PHP',
    status: 'draft',
    fulfillment_mode: 'pay_code_distribution',
    delivery_plan: ['csv'],
    beneficiary_count: 0,
    principal_minor: 0,
    updated_at: '2026-07-29T12:00:00+08:00',
};

describe('Cockpit campaign worksheets', () => {
    it('presents only aggregate draft facts until beneficiaries are added', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [worksheet],
            },
        });

        expect(wrapper.text()).toContain('Campaign Activity');
        expect(wrapper.text()).toContain('July Payroll');
        expect(wrapper.text()).toContain('0');
        expect(wrapper.text()).toContain('₱0.00');
        expect(wrapper.text()).toContain('Draft only');
        expect(wrapper.text()).not.toContain('Maria Santos');
    });

    it('labels authorized activity accurately instead of calling it draft only', () => {
        const wrapper = mount(Campaigns, {
            props: {
                worksheets: [{ ...worksheet, status: 'authorized' }],
            },
        });

        expect(wrapper.text()).toContain('Authorized');
        expect(wrapper.text()).not.toContain('Draft only');
    });

    it('keeps the host Inertia adapter aligned with the package page', () => {
        const wrapper = mount(CampaignsRouteAdapter, {
            props: {
                worksheets: [worksheet],
            },
        });

        expect(wrapper.find('[data-testid="cockpit-campaigns-page"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Create A Worksheet');
    });

    it('makes the planned post-approval state explicit before beneficiary issuance', () => {
        const page = readFileSync(
            resolve(import.meta.dirname, '../../../resources/js/cockpit/pages/CampaignWorksheet.vue'),
            'utf8',
        );

        expect(page).toContain('data-testid="campaign-fulfillment-readiness"');
        expect(page).toContain('Authorized Beneficiaries');
        expect(page).toContain("v-if=\"isDraft()\"");
        expect(page).toContain('Pay Codes Issued');
        expect(page).toContain('Direct Bank Transfer Is Not Enabled');
        expect(page).toContain('props.direct_bank_transfer_enabled');
        expect(page).toContain('Beneficiaries Ready To Issue');
        expect(page).toContain('No Pay Codes, delivery, or bank transfers have started.');
        expect(page).toContain('v-if="plannedCount() > 0"');
        expect(page).toContain('data-testid="campaign-delivery-controls"');
        expect(page).toContain('Issuance never sends messages.');
        expect(page).toContain('Download CSV');
        expect(page).toContain('SMS Disabled');
        expect(page).toContain('Email Disabled');
    });
});
