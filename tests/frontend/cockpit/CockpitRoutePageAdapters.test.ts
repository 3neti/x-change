import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Dashboard from '../../../resources/js/pages/x-change/cockpit/Dashboard.vue';
import DistributionWorkspace from '../../../resources/js/pages/x-change/cockpit/DistributionWorkspace.vue';
import PayCodeExplorer from '../../../resources/js/pages/x-change/cockpit/PayCodeExplorer.vue';
import QuickGenerate from '../../../resources/js/pages/x-change/cockpit/QuickGenerate.vue';
import VoucherDetail from '../../../resources/js/pages/x-change/cockpit/VoucherDetail.vue';

describe('Cockpit route page adapters', () => {
    it('mounts the dashboard route adapter', () => {
        const wrapper = mount(Dashboard);

        expect(wrapper.find('[data-testid="cockpit-dashboard-shell"]').exists()).toBe(true);
    });

    it('mounts the quick generate route adapter', () => {
        const wrapper = mount(QuickGenerate);

        expect(wrapper.find('[data-testid="cockpit-quick-generate-shell"]').exists()).toBe(true);
    });

    it('mounts the pay code explorer route adapter', () => {
        const wrapper = mount(PayCodeExplorer);

        expect(wrapper.find('[data-testid="cockpit-pay-code-explorer-shell"]').exists()).toBe(true);
    });

    it('mounts the voucher detail route adapter', () => {
        const wrapper = mount(VoucherDetail);

        expect(wrapper.find('[data-testid="cockpit-voucher-detail-shell"]').exists()).toBe(true);
    });

    it('mounts the distribution workspace route adapter', () => {
        const wrapper = mount(DistributionWorkspace);

        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-shell"]').exists()).toBe(true);
    });
});
