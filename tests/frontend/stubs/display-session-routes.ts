export const store = Object.assign(
    () => ({ url: '/x/cockpit/display-sessions', method: 'post' }),
    { url: () => '/x/cockpit/display-sessions' },
);
