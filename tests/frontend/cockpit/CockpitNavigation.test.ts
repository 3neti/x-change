import { describe, expect, it } from 'vitest';
import {
    cockpitNavigationItems,
    cockpitPrimaryNavigation,
    cockpitSecondaryNavigation,
} from '../../../resources/js/cockpit/navigation';

describe('Cockpit navigation baseline', () => {
    it('defines the primary and secondary Cockpit navigation model', () => {
        expect(cockpitPrimaryNavigation.map((item) => item.label)).toEqual([
            'Dashboard',
            'Quick Generate',
            'Funding',
            'Pay Codes',
            'Templates',
            'Contacts',
            'Operations',
            'Reports',
        ]);

        expect(cockpitSecondaryNavigation.map((item) => item.label)).toEqual([
            'Approvals',
            'Administration',
        ]);

        expect(cockpitNavigationItems).toHaveLength(10);
    });

    it('keeps navigation as shell descriptors without domain behavior', () => {
        for (const item of cockpitNavigationItems) {
            expect(item.href).toMatch(/^\/x\/cockpit/);
            expect(item).not.toHaveProperty('action');
            expect(item).not.toHaveProperty('driver');
            expect(item).not.toHaveProperty('journalEvent');
            expect(item).not.toHaveProperty('feedbackIntent');
            expect(item).not.toHaveProperty('campaign');
            expect(item).not.toHaveProperty('issueVoucher');
            expect(item).not.toHaveProperty('moveMoney');
            expect(item).not.toHaveProperty('mutateVoucher');
            expect(item).not.toHaveProperty('providerCall');
        }
    });
});
