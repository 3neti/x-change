export function show(reference: string) {
    return {
        url: `/x/cockpit/funding/intents/${reference}/instructions`,
        method: 'get',
    };
}
