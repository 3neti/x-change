import { h } from 'vue';

export const Button = {
    name: 'Button',
    emits: ['click'],
    props: ['as', 'href', 'disabled'],
    setup(props: { as?: string; href?: string; disabled?: boolean }, { slots, emit }: { slots: Record<string, () => unknown[]>; emit: (event: 'click') => void }) {
        return () => h(
            props.as || 'button',
            {
                'data-testid': 'button',
                href: props.href,
                disabled: props.disabled,
                onClick: () => emit('click'),
            },
            slots.default?.(),
        );
    },
};
