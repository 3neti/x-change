import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormFlowStatusIcon from '@/pages/form-flow/core/components/FormFlowStatusIcon.vue';

describe('semantic claim icons', () => {
    it('renders a configured review icon without altering its semantic role', () => {
        const wrapper = mount(FormFlowStatusIcon, {props:{role:'review',presentation:{icons:{size:40,review:{name:'clipboard-check',color:'#123456'}}} as any}});
        expect(wrapper.attributes('data-claim-icon-role')).toBe('review');
        expect(wrapper.attributes('data-claim-icon-name')).toBe('clipboard-check');
        expect(wrapper.attributes('style')).toContain('width: 40px');
        expect(wrapper.attributes('style')).toContain('rgb(18, 52, 86)');
    });
    it('keeps legacy pending snapshots distinct from completed claims', () => {
        const wrapper = mount(FormFlowStatusIcon, {props:{role:'pending'}});
        expect(wrapper.attributes('data-claim-icon-name')).toBe('clock');
        expect(wrapper.attributes('style')).toContain('width: 48px');
    });
});
