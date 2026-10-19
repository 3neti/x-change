import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Surface from '@/pages/form-flow/core/components/FormFlowArtworkSurface.vue';

const presentation = {artwork:{background:{enabled:true,opacity:0.55,blur:18,overlay:0.75},foreground:{position:'top',top_spacing:32,gap:20,padding:20,max_width:672}}} as any;
describe('shared artwork surface', () => {
    it('renders only the image in a decorative background and the content once', () => {
        const wrapper = mount(Surface,{props:{artworkSrc:'/artwork.png',presentation},slots:{default:'<p>One introduction</p>'}});
        expect(wrapper.get('[data-testid="artwork-background"]').attributes('aria-hidden')).toBe('true');
        expect(wrapper.get('.artwork-backdrop').attributes('style')).toContain('opacity: 0.55');
        expect(wrapper.get('.artwork-backdrop').attributes('style')).toContain('blur(18px)');
        expect(wrapper.findAll('p')).toHaveLength(1);
        expect(wrapper.get('[data-testid="artwork-foreground"]').attributes('style')).toContain('gap: 20px');
    });
    it('disables the background for an old profile or an unsafe source', () => {
        expect(mount(Surface,{props:{artworkSrc:'/artwork.png'}}).find('[data-testid="artwork-background"]').exists()).toBe(false);
        expect(mount(Surface,{props:{artworkSrc:'javascript:alert(1)',presentation}}).find('[data-testid="artwork-background"]').exists()).toBe(false);
    });
});
