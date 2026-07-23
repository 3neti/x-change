import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import NetbankProfileCard from '../../../resources/js/components/x-change/NetbankProfileCard.vue';
import PaynamicsWalletProfileCard from '../../../resources/js/components/x-change/PaynamicsWalletProfileCard.vue';

describe('Cockpit Account profile summaries', () => {
    it('keeps NetBank configuration read-only and links to Accounts', () => {
        const wrapper = mount(NetbankProfileCard, {
            props: {
                profile: {
                    active: true,
                    client_alias: 'treasury',
                    source_account_readiness: {
                        enabled: true,
                        ready: true,
                        account_number_masked: '•••• 0019',
                    },
                },
            },
        });

        expect(wrapper.text()).toContain('NetBank source account');
        expect(wrapper.text()).toContain('PIN-protected');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('a').attributes('href')).toBe(
            '/x/cockpit/accounts',
        );
    });

    it('removes direct Paynamics wallet mutation from Profile', () => {
        const wrapper = mount(PaynamicsWalletProfileCard, {
            props: {
                wallet: {
                    wallet_id: '•••• LLET01',
                    status: 'ready',
                    verification_status: 'ownership_verified',
                },
            },
        });

        expect(wrapper.text()).toContain('This profile is a summary');
        expect(wrapper.text()).toContain('Manage Accounts');
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('input').exists()).toBe(false);
        expect(wrapper.find('a').attributes('href')).toBe(
            '/x/cockpit/accounts',
        );
    });
});
