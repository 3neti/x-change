export default function CockpitPayCodeTemplateUpdateController(
    template: string | { reference: string },
): {
    url: string;
    method: 'patch';
} {
    const reference =
        typeof template === 'string' ? template : template.reference;

    return {
        url: `/x/cockpit/pay-code-templates/${reference}`,
        method: 'patch',
    };
}
