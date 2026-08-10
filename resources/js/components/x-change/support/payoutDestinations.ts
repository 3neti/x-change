import iconMetadata from '@/../../resources/documents/payout-destination-icons.json';
import { getBank } from '@/data/banks';

export type SettlementRail = 'INSTAPAY' | 'PESONET';

/**
 * Mirrors `PayoutDestinationRegistry::snapshot()`'s return shape on the PHP
 * side. Pages that receive a persisted/in-flight destination snapshot as an
 * Inertia prop (e.g. claim Success/Approval) should type it with this.
 */
export type PayoutDestinationSnapshot = {
    bank_code: string | null;
    bank_name: string | null;
    bank_label: string | null;
    icon_asset: string | null;
    settlement_rail: string | null;
    account_number_masked: string | null;
    route: string[];
    route_icons: (string | null)[];
};

export type DestinationInstitution = {
    code: string;
    label: string;
    shortLabel: string;
    category: 'wallet' | 'bank' | 'provider' | 'rail' | 'unknown';
    iconKey: string;
    iconAsset: string | null;
};

const INSTITUTIONS: Record<string, DestinationInstitution> = {
    GXCHPHM2XXX: {
        code: 'GXCHPHM2XXX',
        label: 'GCash',
        shortLabel: 'GCash',
        category: 'wallet',
        iconKey: 'wallet.gcash',
        iconAsset: null,
    },
    PAPHPHM1XXX: {
        code: 'PAPHPHM1XXX',
        label: 'Maya Wallet',
        shortLabel: 'Maya Wallet',
        category: 'wallet',
        iconKey: 'wallet.maya',
        iconAsset: null,
    },
    MYDBPHM2XXX: {
        code: 'MYDBPHM2XXX',
        label: 'Maya Bank',
        shortLabel: 'Maya Bank',
        category: 'bank',
        iconKey: 'bank.maya',
        iconAsset: null,
    },
};

const RAIL_LABELS: Record<string, string> = {
    INSTAPAY: 'InstaPay',
    PESONET: 'PESONet',
};

const PAYOUT_DESTINATION_ICON_BASE = '/vendor/x-change/images/payout-destinations';

type IconAssetFiles = { png64?: string; png128?: string; png256?: string; svg?: string };
type IconEntry = { slug?: string; assets?: IconAssetFiles };

const ICON_ENTRIES: Record<string, IconEntry> =
    (iconMetadata as { entries?: Record<string, IconEntry> }).entries ?? {};

function iconAssetFromEntry(entry: IconEntry | undefined): string | null {
    const file = entry?.assets?.png128 ?? entry?.assets?.png64 ?? entry?.assets?.png256 ?? entry?.assets?.svg;

    return file ? `${PAYOUT_DESTINATION_ICON_BASE}/${file}` : null;
}

/**
 * Resolves a packaged local icon asset path for any code in the payout
 * destination icon metadata (bank/EMI SWIFT codes, or synthetic
 * `RAIL:*` / `PROVIDER:*` / `ORCHESTRATOR:*` codes). Returns null when the
 * code has no covered icon -- callers must keep rendering text labels
 * regardless of icon availability.
 */
export function iconAssetForCode(code: string | null | undefined): string | null {
    const normalized = String(code ?? '').trim().toUpperCase();

    return normalized ? iconAssetFromEntry(ICON_ENTRIES[normalized]) : null;
}

export function iconAssetForRail(rail: string | null | undefined): string | null {
    const normalized = String(rail ?? '').trim().toUpperCase();

    return normalized ? iconAssetForCode(`RAIL:${normalized}`) : null;
}

export function iconAssetForProvider(provider: string | null | undefined): string | null {
    const normalized = String(provider ?? '').trim().toUpperCase();

    return normalized ? iconAssetForCode(`PROVIDER:${normalized}`) : null;
}

export function orchestratorIconAsset(): string | null {
    return iconAssetForCode('ORCHESTRATOR:XCHANGE');
}

export function destinationInstitution(code: string | null | undefined): DestinationInstitution {
    const normalized = String(code ?? '').trim().toUpperCase();
    const iconAsset = iconAssetForCode(normalized);

    const known = INSTITUTIONS[normalized];

    if (known) {
        return { ...known, iconAsset: known.iconAsset ?? iconAsset };
    }

    const bank = getBank(normalized);

    if (bank) {
        const category = bank.isEMI ? 'wallet' : 'bank';

        return {
            code: normalized,
            label: bank.name,
            shortLabel: bank.name,
            category,
            iconKey: `${category}.generic`,
            iconAsset,
        };
    }

    return {
        code: normalized,
        label: normalized || 'Selected destination',
        shortLabel: normalized || 'Destination',
        category: 'unknown',
        iconKey: 'institution.generic',
        iconAsset,
    };
}

export function settlementRailLabel(rail: string | null | undefined): string {
    const normalized = String(rail ?? '').trim().toUpperCase();

    return RAIL_LABELS[normalized] ?? normalized;
}

export function payoutRouteSegments(input: {
    orchestrator?: string | null;
    provider?: string | null;
    settlementRail?: string | null;
    bankCode?: string | null;
    accountNumber?: string | null;
}): string[] {
    const institution = destinationInstitution(input.bankCode);

    return [
        input.orchestrator || 'x-change',
        input.provider || 'NetBank',
        settlementRailLabel(input.settlementRail || 'INSTAPAY'),
        institution.shortLabel,
        input.accountNumber || null,
    ].filter((segment): segment is string => Boolean(segment));
}

/**
 * Returns one icon asset path (or null) per segment produced by
 * `payoutRouteSegments`, in the same order: orchestrator, provider, rail,
 * institution, account number. The account number segment has no icon.
 */
export function payoutRouteIcons(input: {
    orchestrator?: string | null;
    provider?: string | null;
    settlementRail?: string | null;
    bankCode?: string | null;
    accountNumber?: string | null;
}): (string | null)[] {
    const institution = destinationInstitution(input.bankCode);
    const rail = input.settlementRail || 'INSTAPAY';
    const icons = [
        orchestratorIconAsset(),
        iconAssetForProvider(input.provider || 'NetBank'),
        iconAssetForRail(rail),
        institution.iconAsset,
    ];

    return input.accountNumber ? [...icons, null] : icons;
}

export function payoutRouteSentence(input: {
    amount?: string | number | null;
    settlementRail?: string | null;
    bankCode?: string | null;
    accountNumber?: string | null;
}): string {
    const amount = input.amount ? String(input.amount) : 'the money';
    const institution = destinationInstitution(input.bankCode);
    const rail = settlementRailLabel(input.settlementRail || 'INSTAPAY');

    return `Send ${amount} to ${institution.shortLabel} account ${input.accountNumber || 'provided by redeemer'} via ${rail}.`;
}
