const url = () => '/x/cockpit/campaigns/lead-scenario-runner';
const route = () => ({ url: url() });

export const show = Object.assign(route, { url });
export const store = Object.assign(route, { url });
