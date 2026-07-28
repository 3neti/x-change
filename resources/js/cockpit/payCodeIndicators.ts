import {
    ArrowLeftRight,
    BadgeCheck,
    BadgeDollarSign,
    Banknote,
    CalendarDays,
    GitFork,
    HandCoins,
    Hash,
    Image,
    KeyRound,
    Landmark,
    Link,
    Mail,
    MapPin,
    MapPinned,
    MessageSquare,
    PenLine,
    ReceiptText,
    ScanFace,
    Smartphone,
    UserRound,
    Webhook,
    type LucideIcon,
} from 'lucide-vue-next';

export type PayCodeIndicator = {
    key: string;
    label: string;
    tooltip: string;
    icon: LucideIcon;
};

const indicators: Record<string, Omit<PayCodeIndicator, 'key'>> = {
    'outcome.provider_disbursement': {
        label: 'Receive Funds',
        tooltip: 'The recipient can receive the Pay Code value.',
        icon: Banknote,
    },
    'outcome.account_funding': {
        label: 'Add to Account',
        tooltip: 'The Pay Code value can be added to the recipient’s Account.',
        icon: Landmark,
    },
    'outcome.collect_payment': {
        label: 'Collect Payment',
        tooltip: 'The Pay Code can collect a payment.',
        icon: HandCoins,
    },
    'outcome.settlement': {
        label: 'Settlement',
        tooltip: 'The Pay Code follows a settlement flow.',
        icon: ArrowLeftRight,
    },
    'input.mobile': {
        label: 'Mobile Number',
        tooltip: 'The recipient must provide a mobile number.',
        icon: Smartphone,
    },
    'input.name': {
        label: 'Full Name',
        tooltip: 'The recipient must provide a full name.',
        icon: UserRound,
    },
    'input.email': {
        label: 'Email Address',
        tooltip: 'The recipient must provide an email address.',
        icon: Mail,
    },
    'input.address': {
        label: 'Full Address',
        tooltip: 'The recipient must provide a full address.',
        icon: MapPinned,
    },
    'input.birth_date': {
        label: 'Birth Date',
        tooltip: 'The recipient must provide a birth date.',
        icon: CalendarDays,
    },
    'input.gross_monthly_income': {
        label: 'Monthly Income',
        tooltip: 'The recipient must provide monthly income.',
        icon: BadgeDollarSign,
    },
    'input.reference_code': {
        label: 'Reference Code',
        tooltip: 'The recipient must provide a reference code.',
        icon: Hash,
    },
    'input.kyc': {
        label: 'KYC Verification',
        tooltip: 'The recipient must complete an identity check.',
        icon: BadgeCheck,
    },
    'input.otp': {
        label: 'OTP Verification',
        tooltip: 'The recipient must complete one-time-passcode verification.',
        icon: KeyRound,
    },
    'input.selfie': {
        label: 'Selfie Photo',
        tooltip: 'The recipient must provide a selfie.',
        icon: ScanFace,
    },
    'input.signature': {
        label: 'Digital Signature',
        tooltip: 'The recipient must provide a signature.',
        icon: PenLine,
    },
    'input.location': {
        label: 'GPS Location',
        tooltip: 'The recipient must provide location evidence.',
        icon: MapPin,
    },
    'validation.mobile': {
        label: 'Mobile Restriction',
        tooltip: 'Only the intended mobile number can claim.',
        icon: Smartphone,
    },
    'validation.otp': {
        label: 'OTP Required',
        tooltip: 'A one-time passcode is required to claim.',
        icon: KeyRound,
    },
    'validation.identity': {
        label: 'Identity Check',
        tooltip: 'Identity verification is required to claim.',
        icon: BadgeCheck,
    },
    'validation.selfie': {
        label: 'Selfie Verification',
        tooltip: 'A recipient selfie is required to claim.',
        icon: ScanFace,
    },
    'validation.signature': {
        label: 'Signature Required',
        tooltip: 'A recipient signature is required to claim.',
        icon: PenLine,
    },
    'validation.location': {
        label: 'Location Validation',
        tooltip: 'The claim must satisfy the configured location rule.',
        icon: MapPin,
    },
    'validation.time': {
        label: 'Time Window',
        tooltip: 'The claim must occur within the configured time window.',
        icon: CalendarDays,
    },
    'claim.multiple': {
        label: 'Multiple Claims',
        tooltip: 'The Pay Code value can be claimed in multiple slices.',
        icon: GitFork,
    },
    'feedback.email': {
        label: 'Email Update',
        tooltip: 'An email update is included in the instructions.',
        icon: Mail,
    },
    'feedback.mobile': {
        label: 'SMS Update',
        tooltip: 'An SMS update is included in the instructions.',
        icon: Smartphone,
    },
    'feedback.webhook': {
        label: 'Webhook Update',
        tooltip: 'A webhook update is included in the instructions.',
        icon: Webhook,
    },
    'rider.message': {
        label: 'Rider Message',
        tooltip: 'The Pay Code includes a Rider message.',
        icon: MessageSquare,
    },
    'rider.url': {
        label: 'Rider URL',
        tooltip: 'The Pay Code includes a Rider action link.',
        icon: Link,
    },
    'rider.splash': {
        label: 'Rider Splash',
        tooltip: 'The Pay Code includes Rider Splash artwork.',
        icon: Image,
    },
    generation: {
        label: 'Pay Code Generation',
        tooltip: 'Base Pay Code generation charge.',
        icon: ReceiptText,
    },
};

const catalogAliases: Record<string, string> = {
    base: 'generation',
    cash: 'generation',
    'cash.amount': 'generation',
    generation: 'generation',
    'flow_type.collectible': 'outcome.collect_payment',
    'voucher_type.payable': 'outcome.collect_payment',
    'voucher_type.settlement': 'outcome.settlement',
    'inputs.fields.mobile': 'input.mobile',
    'inputs.fields.name': 'input.name',
    'inputs.fields.email': 'input.email',
    'inputs.fields.address': 'input.address',
    'inputs.fields.birth_date': 'input.birth_date',
    'inputs.fields.gross_monthly_income': 'input.gross_monthly_income',
    'inputs.fields.reference_code': 'input.reference_code',
    'inputs.fields.kyc': 'input.kyc',
    'inputs.fields.otp': 'input.otp',
    'inputs.fields.selfie': 'input.selfie',
    'inputs.fields.signature': 'input.signature',
    'inputs.fields.location': 'input.location',
    'cash.validation.mobile': 'validation.mobile',
    'cash.validation.secret': 'validation.otp',
    'cash.validation.payable': 'outcome.collect_payment',
    'validation.time': 'validation.time',
    'validation.location': 'validation.location',
    'feedback.email': 'feedback.email',
    email_feedback: 'feedback.email',
    'feedback.mobile': 'feedback.mobile',
    sms_feedback: 'feedback.mobile',
    'feedback.webhook': 'feedback.webhook',
    webhook: 'feedback.webhook',
    'rider.message': 'rider.message',
    'rider.url': 'rider.url',
    'rider.splash': 'rider.splash',
};

export function resolvePayCodeIndicator(key: string): PayCodeIndicator {
    const canonicalKey = catalogAliases[key] ?? key;
    const indicator = indicators[canonicalKey];

    if (indicator !== undefined) {
        return {
            key: canonicalKey,
            ...indicator,
        };
    }

    return {
        key,
        label: humanizeIndicatorKey(key),
        tooltip: `${humanizeIndicatorKey(key)} instruction.`,
        icon: ReceiptText,
    };
}

export function payCodeOutcomeIndicatorKey(
    claimOutcome: 'provider_disbursement' | 'account_funding',
    voucherType: 'redeemable' | 'payable' | 'settlement',
): string {
    if (claimOutcome === 'account_funding') {
        return 'outcome.account_funding';
    }

    if (voucherType === 'payable') {
        return 'outcome.collect_payment';
    }

    if (voucherType === 'settlement') {
        return 'outcome.settlement';
    }

    return 'outcome.provider_disbursement';
}

function humanizeIndicatorKey(key: string): string {
    const normalized = key
        .split('.')
        .at(-1)
        ?.replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

    return normalized || 'Priced Instruction';
}
