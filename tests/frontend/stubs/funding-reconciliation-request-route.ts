export function store(reference: string) {
    return {
        url: `/x/cockpit/funding/suspense/${reference}/reconciliation-requests`,
        method: 'post',
    };
}
