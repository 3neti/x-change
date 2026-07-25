export function store(reference: string) {
    return {
        url: `/x/cockpit/funding/requests/${reference}/approvals`,
        method: 'post',
    };
}
