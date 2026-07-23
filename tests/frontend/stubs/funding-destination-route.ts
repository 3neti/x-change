export function update(provider: string) {
    return {
        url: `/x/cockpit/accounts/providers/${provider}/funding-destination`,
        method: 'patch',
    };
}
