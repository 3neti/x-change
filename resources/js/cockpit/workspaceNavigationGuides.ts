export type CockpitWorkspaceGuide = {
    purpose: string;
    beforeYouBegin: string;
    steps: string[];
    next: string;
};

export const cockpitWorkspaceGuides = {
    funding: {
        purpose: 'Add money to Client Funds and confirm where it came from.',
        beforeYouBegin:
            'Have your transfer reference or a funding Pay Code ready.',
        steps: [
            'Choose a funding method.',
            'Follow the destination and reference instructions.',
            'Wait for the confirmed amount to appear in Client Funds.',
        ],
        next: 'Continue to Issuance when your capacity can cover the Pay Code.',
    },
    issuance: {
        purpose:
            'Draft the instruction, review its cost, and issue a Pay Code.',
        beforeYouBegin:
            'Know the amount, recipient, and any claim requirements you need.',
        steps: [
            'Set the amount and recipient.',
            'Add only the claim requirements the payout needs.',
            'Review the Order before issuing the Pay Code.',
        ],
        next: 'Open Pay Codes to track, share, or manage what you issued.',
    },
    campaigns: {
        purpose: 'Prepare the same controlled issuance for many recipients.',
        beforeYouBegin:
            'Finish the single-recipient instruction and prepare your audience.',
        steps: [
            'Choose the campaign and instruction template.',
            'Import and validate the recipients.',
            'Approve the run before distribution begins.',
        ],
        next: 'Use Pay Codes to follow the resulting recipient payouts.',
    },
    payCodes: {
        purpose: 'Find Pay Codes and follow each one through its lifecycle.',
        beforeYouBegin:
            'Keep the Pay Code or recipient reference you want to inspect.',
        steps: [
            'Search or filter the issued Pay Codes.',
            'Open a record to see its current state and evidence.',
            'Use only the actions available for that state.',
        ],
        next: 'Return to Issuance for another Pay Code or Overview for trends.',
    },
    overview: {
        purpose: 'Review funds, capacity, activity, and operational signals.',
        beforeYouBegin:
            'Use this page after work has started; it is a read-only overview.',
        steps: [
            'Check Client Funds and Issuance Capacity.',
            'Review outstanding Pay Codes and activity.',
            'Follow any warning to its operational workspace.',
        ],
        next: 'Go to Funding, Issuance, or Pay Codes to take action.',
    },
} satisfies Record<string, CockpitWorkspaceGuide>;
