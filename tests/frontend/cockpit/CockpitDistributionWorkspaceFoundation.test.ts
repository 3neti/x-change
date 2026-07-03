import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CockpitDigitalDistributionPanel from '../../../resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue';
import CockpitDistributionAnalyticsPanel from '../../../resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue';
import CockpitPrintTemplatePanel from '../../../resources/js/cockpit/components/CockpitPrintTemplatePanel.vue';
import CockpitShareQrPanel from '../../../resources/js/cockpit/components/CockpitShareQrPanel.vue';
import DistributionWorkspace from '../../../resources/js/cockpit/pages/DistributionWorkspace.vue';
import {
    cockpitDistributionActions,
    cockpitDistributionChannels,
    cockpitDistributionMetrics,
    cockpitPrintTemplates,
    cockpitShareAssets,
} from '../../../resources/js/cockpit/distributionWorkspaceDefaults';

describe('Cockpit Distribution Workspace foundation', () => {
    it('renders digital distribution planning placeholders with disabled actions', () => {
        const wrapper = mount(CockpitDigitalDistributionPanel, {
            props: {
                channels: cockpitDistributionChannels,
                actions: cockpitDistributionActions,
            },
        });

        expect(wrapper.text()).toContain('Channel planning placeholder');
        expect(wrapper.text()).toContain('SMS handoff');
        expect(wrapper.text()).toContain('Email handoff');
        expect(wrapper.text()).toContain('In-app notification handoff');
        expect(wrapper.text()).toContain('Manual branch release');
        expect(wrapper.findAll('[data-testid="cockpit-distribution-channel"]')).toHaveLength(4);

        const actions = wrapper.findAll('[data-testid="cockpit-distribution-action"]');

        expect(actions).toHaveLength(4);

        for (const action of actions) {
            expect(action.attributes('disabled')).toBeDefined();
            expect(action.attributes('title')).toBeTruthy();
        }
    });

    it('renders print template placeholders without generating artifacts', () => {
        const wrapper = mount(CockpitPrintTemplatePanel, {
            props: {
                templates: cockpitPrintTemplates,
            },
        });

        expect(wrapper.text()).toContain('Print template placeholder');
        expect(wrapper.text()).toContain('Receipt card');
        expect(wrapper.text()).toContain('Branch release sheet');
        expect(wrapper.text()).toContain('Counter slip');
        expect(wrapper.text()).toContain('Print assets are not generated or persisted');
        expect(wrapper.findAll('[data-testid="cockpit-print-template"]')).toHaveLength(3);
    });

    it('renders share and QR placeholders without creating share assets', () => {
        const wrapper = mount(CockpitShareQrPanel, {
            props: {
                assets: cockpitShareAssets,
            },
        });

        expect(wrapper.text()).toContain('Share asset placeholder');
        expect(wrapper.text()).toContain('QR asset');
        expect(wrapper.text()).toContain('Short link');
        expect(wrapper.text()).toContain('Copy text');
        expect(wrapper.text()).toContain('QR generation must use an approved Pay Code representation');
        expect(wrapper.findAll('[data-testid="cockpit-share-asset"]')).toHaveLength(3);
    });

    it('renders operational distribution analytics without campaign ownership', () => {
        const wrapper = mount(CockpitDistributionAnalyticsPanel, {
            props: {
                metrics: cockpitDistributionMetrics,
            },
        });

        expect(wrapper.text()).toContain('Distribution analytics placeholder');
        expect(wrapper.text()).toContain('Planned sends');
        expect(wrapper.text()).toContain('Printed assets');
        expect(wrapper.text()).toContain('Delivery state');
        expect(wrapper.text()).toContain('Campaign state');
        expect(wrapper.text()).toContain('Campaign behavior is deferred until Wave 5');
        expect(wrapper.findAll('[data-testid="cockpit-distribution-metric"]')).toHaveLength(4);
    });

    it('renders the full distribution workspace page with Pay Codes navigation and side-effect boundaries', () => {
        const wrapper = mount(DistributionWorkspace);

        expect(wrapper.find('[data-testid="cockpit-distribution-workspace-shell"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Distribution Workspace Foundation');
        expect(wrapper.text()).toContain('Digital Distribution');
        expect(wrapper.text()).toContain('Print Templates');
        expect(wrapper.text()).toContain('Share / QR');
        expect(wrapper.text()).toContain('Operational Analytics');
        expect(wrapper.find('[aria-current="page"]').text()).toContain('Pay Codes');
        expect(wrapper.text()).toContain('does not dispatch distribution');
        expect(wrapper.text()).toContain('send feedback');
        expect(wrapper.text()).toContain('create campaigns');
        expect(wrapper.text()).toContain('mutate vouchers');
        expect(wrapper.text()).toContain('execute drivers');
        expect(wrapper.text()).toContain('write journal entries');
        expect(wrapper.text()).toContain('call providers');
        expect(wrapper.text()).toContain('move money');
    });
});
