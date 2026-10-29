import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ClaimCodeField from '../../resources/js/components/x-change/ClaimCodeField.vue';

describe('ClaimCodeField', () => {
    it.each(['ABCD', 'ABCD1234', 'ABCDEFGHIJKL'])('keeps the verified code and pipes in one compact group for %s', (code) => {
        const wrapper = mount(ClaimCodeField, {
            props: { id: 'code', label: 'Pay Code', modelValue: code, verified: true },
        });

        const input = wrapper.get<HTMLInputElement>('[data-testid="claim-code-input"]');

        expect(input.element.value).toBe(code);
        expect(input.element.style.width).toBe(`${code.length}ch`);
        expect(wrapper.get('[data-testid="claim-code-field"]').attributes('style')).toContain('gap: 1ch');
        expect(wrapper.findAll('[data-testid="claim-code-left-pipes"]')).toHaveLength(1);
        expect(wrapper.findAll('[data-testid="claim-code-right-pipes"]')).toHaveLength(1);
    });

    it('keeps the raw value editable and hides pipes before verification', async () => {
        const wrapper = mount(ClaimCodeField, {
            props: { id: 'code', label: 'Pay Code', modelValue: 'ABCD' },
        });

        const input = wrapper.get<HTMLInputElement>('[data-testid="claim-code-input"]');

        expect(wrapper.find('[data-testid="claim-code-left-pipes"]').exists()).toBe(false);
        await input.setValue('EFGH');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['EFGH']);
    });
});
