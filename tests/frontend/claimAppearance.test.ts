import { beforeEach, describe, expect, it, vi } from 'vitest';
const { navigation } = vi.hoisted(() => ({navigation:{callback:null as null | ((event:any)=>void)}}));
vi.mock('@inertiajs/vue3', () => ({router:{on:vi.fn((_name,callback)=>{navigation.callback=callback;return vi.fn();})}}));
import { initializeClaimAppearance, isPublicClaimPage } from '../../resources/js/composables/useClaimAppearance';

describe('public claim appearance',()=>{
    let systemChange: (()=>void) | null;
    beforeEach(()=>{
        localStorage.clear();document.documentElement.className='dark';document.documentElement.style.colorScheme='';
        delete document.documentElement.dataset.xChangeClaimAppearance;
        systemChange=null;
        vi.stubGlobal('matchMedia',vi.fn(()=>({matches:true,addEventListener:vi.fn((_event,callback)=>{systemChange=callback;}),removeEventListener:vi.fn()})));
    });
    it('keeps public claims light without replacing the saved dark preference',()=>{
        localStorage.setItem('appearance','dark');
        const cleanup=initializeClaimAppearance({component:'x-change/claim/Entry',props:{}});
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');
        document.documentElement.classList.add('dark');systemChange?.();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        navigation.callback?.({detail:{page:{component:'x-change/cockpit/Overview',props:{}}}});
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.style.colorScheme).toBe('');
        expect(localStorage.getItem('appearance')).toBe('dark');cleanup();
    });
    it('leaves unrelated form flows and cockpit appearance alone',()=>{
        expect(isPublicClaimPage({component:'form-flow/core/GenericForm',props:{}})).toBe(false);
        const cleanup=initializeClaimAppearance({component:'x-change/cockpit/Overview',props:{}});
        expect(document.documentElement.classList.contains('dark')).toBe(true);cleanup();
    });
    it('includes every guided claim handler and restores a saved light preference',()=>{
        localStorage.setItem('appearance','light');
        const cleanup=initializeClaimAppearance({component:'form-flow/selfie/SelfieCapturePage',props:{presentation:{profile:'guided-claim-v1'}}});
        expect(document.documentElement.dataset.xChangeClaimAppearance).toBe('light');
        cleanup();expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
