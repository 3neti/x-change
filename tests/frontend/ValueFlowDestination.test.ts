import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ValueFlowDestination from '../../resources/js/components/x-change-shared-financial/ValueFlowDestination.vue';
import BankEMISelect from '../../resources/js/components/x-change-shared-financial/BankEMISelect.vue';

describe('voucher value destination', () => {
    it('uses the shared institution picker and preserves leading account zeroes', async () => {
        const wrapper = mount(ValueFlowDestination, { props: { storedValue: true } });
        await wrapper.get('select').setValue(true);
        expect(wrapper.getComponent(BankEMISelect).props('compact')).toBe(true);
        expect(wrapper.get('[data-testid="bank-emi-select-trigger"]').classes()).toContain('h-10');
        expect(wrapper.get('input[inputmode="numeric"]').classes()).toContain('h-10');
        expect(wrapper.get('input[inputmode="numeric"]').classes()).not.toContain('text-2xl');
        wrapper.getComponent(BankEMISelect).vm.$emit('update:modelValue', 'GXCHPHM2XXX');
        await wrapper.get('input[inputmode="numeric"]').setValue('0917 999 9999');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['instapay.gxchphm2xxx.account:09179999999']);
        expect(wrapper.text()).toContain('Every spend from this Pay Code goes to this account');
        expect(wrapper.text()).not.toMatch(/demo/i);
        await wrapper.get('select').setValue(false);
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['']);
    });

    it('restores the issued address and resets when a template clears it', async () => {
        const wrapper = mount(ValueFlowDestination, { props: { modelValue: 'instapay.gxchphm2xxx.mobile:09179999999' } });
        expect((wrapper.get('input[inputmode="numeric"]').element as HTMLInputElement).value).toBe('09179999999');
        expect(wrapper.getComponent(BankEMISelect).props('modelValue')).toBe('GXCHPHM2XXX');
        await wrapper.setProps({ modelValue: '' });
        expect(wrapper.find('input[inputmode="numeric"]').exists()).toBe(false);
    });
});
