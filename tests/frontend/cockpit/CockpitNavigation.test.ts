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
            'Create Pay Code',
            'Funding',
            'Pay Codes',
            'Templates',
            'Contacts',
            'Operations',
            'Reports',
        ]);

        expect(cockpitSecondaryNavigation.map((item) => item.label)).toEqual([
            'Accounts',
            'Runtime Profile',
            'Approvals',
            'Administration',
        ]);

        expect(cockpitNavigationItems).toHaveLength(12);
    });

    it('marks only implemented Cockpit routes as enabled navigation links', () => {
        const enabledItems = cockpitNavigationItems.filter(
            (item) => item.enabled !== false,
        );
        const disabledItems = cockpitNavigationItems.filter(
            (item) => item.enabled === false,
        );

        expect(enabledItems.map((item) => item.key)).toEqual([
            'dashboard',
            'quick-generate',
            'funding',
            'pay-codes',
            'accounts',
            'runtime-profile',
        ]);

        expect(disabledItems.map((item) => item.key)).toEqual([
            'templates',
            'contacts',
            'operations',
            'reports',
            'approvals',
            'administration',
        ]);

        for (const item of disabledItems) {
            expect(item.disabledLabel).toBe('Coming soon');
            expect(item.disabledReason).toContain(
                'Cockpit route has not been implemented yet.',
            );
        }
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
            expect(item).not.toHaveProperty('executeDriver');
            expect(item).not.toHaveProperty('journalWrite');
        }
    });
});
