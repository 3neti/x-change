export type SettlementRail = 'INSTAPAY' | 'PESONET';

export type DestinationInstitution = {
    code: string;
    label: string;
    shortLabel: string;
    category: 'wallet' | 'bank' | 'provider' | 'rail' | 'unknown';
    iconKey: string;
};

const INSTITUTIONS: Record<string, DestinationInstitution> = {
    GXCHPHM2XXX: {
        code: 'GXCHPHM2XXX',
        label: 'GCash',
        shortLabel: 'GCash',
        category: 'wallet',
        iconKey: 'wallet.gcash',
    },
    PAPHPHM1XXX: {
        code: 'PAPHPHM1XXX',
        label: 'Maya Wallet',
        shortLabel: 'Maya Wallet',
        category: 'wallet',
        iconKey: 'wallet.maya',
    },
    MYDBPHM2XXX: {
        code: 'MYDBPHM2XXX',
        label: 'Maya Bank',
        shortLabel: 'Maya Bank',
        category: 'bank',
        iconKey: 'bank.maya',
    },
};

const RAIL_LABELS: Record<string, string> = {
    INSTAPAY: 'InstaPay',
    PESONET: 'PESONet',
};

export function destinationInstitution(code: string | null | undefined): DestinationInstitution {
    const normalized = String(code ?? '').trim().toUpperCase();

    return INSTITUTIONS[normalized] ?? {
        code: normalized,
        label: normalized || 'Selected destination',
        shortLabel: normalized || 'Destination',
        category: 'unknown',
        iconKey: 'institution.generic',
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
