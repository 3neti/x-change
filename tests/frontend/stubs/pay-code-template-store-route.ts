export default function CockpitPayCodeTemplateStoreController(): {
    url: string;
    method: 'post';
} {
    return {
        url: '/x/cockpit/pay-code-templates',
        method: 'post',
    };
}
