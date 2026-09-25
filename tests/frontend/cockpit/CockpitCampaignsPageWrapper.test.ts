import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CampaignsPage from '../../../resources/js/pages/x-change/cockpit/Campaigns.vue';

vi.mock('../../../resources/js/cockpit/pages/Campaigns.vue', async () => {
    const { defineComponent } = await import('vue');
    return {
        default: defineComponent({
            name: 'CampaignsContent',
            props: ['workflow_drafts'],
            template:
                '<section><button v-if="workflow_drafts" data-testid="forwarded-draft-trigger">New workflow draft</button><span v-for="draft in workflow_drafts?.drafts" :key="draft.reference">{{ draft.name }}</span></section>',
        }),
    };
});

describe('Campaigns host page wrapper', () => {
    it('declares and forwards the workflow draft catalog to its rendered Campaigns content', () => {
        const catalog = {
            action_url: '/drafts',
            workflows: [],
            drafts: [
                {
                    reference: 'DRAFT1',
                    name: 'Authorized demo draft',
                    updated_at: null,
                    workflow_title: 'AUI demo',
                    plan_title: 'Demo plan',
                    entry_method: 'payment_qr',
                },
            ],
        };
        const wrapper = mount(CampaignsPage, {
            props: { worksheets: [], workflow_drafts: catalog },
        });

        expect(wrapper.props('workflow_drafts')).toEqual(catalog);
        expect(
            wrapper
                .findComponent({ name: 'CampaignsContent' })
                .props('workflow_drafts'),
        ).toEqual(catalog);
        expect(
            wrapper.get('[data-testid="forwarded-draft-trigger"]').text(),
        ).toBe('New workflow draft');
        expect(wrapper.text()).toContain('Authorized demo draft');
    });

    it('supports existing host responses without a workflow catalog', () => {
        const wrapper = mount(CampaignsPage, { props: { worksheets: [] } });
        expect(
            wrapper.find('[data-testid="forwarded-draft-trigger"]').exists(),
        ).toBe(false);
    });
});
