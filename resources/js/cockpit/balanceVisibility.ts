import type { InjectionKey, Ref } from 'vue';

export const balanceVisibilityKey: InjectionKey<Ref<boolean>> = Symbol('cockpit-balance-visibility');
