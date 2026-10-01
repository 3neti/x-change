export const cockpitClaimEntryLauncherEvent =
    'x-change:open-cockpit-claim-entry';

export function openCockpitClaimEntryLauncher(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(cockpitClaimEntryLauncherEvent));
}
