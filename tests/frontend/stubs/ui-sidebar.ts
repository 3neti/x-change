import { h } from 'vue';

const passthrough = (name: string, tag = 'div') => ({
    name,
    inheritAttrs: false,
    setup(
        _props: unknown,
        {
            attrs,
            slots,
        }: {
            attrs: Record<string, unknown>;
            slots: Record<string, () => unknown[]>;
        },
    ) {
        return () => h(tag, attrs, slots.default?.());
    },
});

export const SidebarMenuButton = passthrough('SidebarMenuButton');
export const SidebarMenuItem = passthrough('SidebarMenuItem', 'li');
