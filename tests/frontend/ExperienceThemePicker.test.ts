import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import ExperienceThemePicker from '../../resources/js/components/x-change/ExperienceThemePicker.vue';
import { EXPERIENCE_THEME_STORAGE_KEY } from '../../resources/js/composables/useTheme';

describe('ExperienceThemePicker', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.removeAttribute('data-x-change-theme');
    });

    it('offers every approved theme with accessible selection state', async () => {
        const wrapper = mount(ExperienceThemePicker);

        expect(wrapper.get('summary').attributes('aria-label')).toBe(
            'Choose appearance theme',
        );
        (wrapper.get('details').element as HTMLDetailsElement).open = true;
        await wrapper.get('details').trigger('toggle');
        expect(
            wrapper.findAll('[data-testid^="experience-theme-option-"]'),
        ).toHaveLength(3);
        expect(
            wrapper
                .get('[data-testid="experience-theme-option-default"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        await wrapper
            .get('[data-testid="experience-theme-option-steampunk"]')
            .trigger('click');

        expect(document.documentElement.dataset.xChangeTheme).toBe('steampunk');
        expect(localStorage.getItem(EXPERIENCE_THEME_STORAGE_KEY)).toBe(
            'steampunk',
        );
        expect(
            wrapper
                .get('[data-testid="experience-theme-option-steampunk"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });
});
