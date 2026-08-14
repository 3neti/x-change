export default function riderLibraryForgetRoute(reference: string) {
    return {
        url: `/x/cockpit/rider-library/${reference}`,
        method: 'delete',
    };
}
