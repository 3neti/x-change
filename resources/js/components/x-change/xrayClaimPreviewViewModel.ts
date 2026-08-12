import type { Component } from 'vue';
import {
    Camera,
    CheckCircle2,
    Clock,
    HelpCircle,
    IdCard,
    KeyRound,
    MapPin,
    PenLine,
    ShieldCheck,
    Smartphone,
    Wallet,
    XCircle,
} from 'lucide-vue-next';

export type XRayBadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export interface XRayStatusViewModel {
    label: string;
    description: string;
    badgeVariant: XRayBadgeVariant;
    icon: Component;
}

export interface XRayRequirementLike {
    key?: string | null;
    label?: string | null;
    description?: string | null;
}

export interface XRayRequirementViewModel {
    key: string;
    label: string;
    description: string | null;
    icon: Component | null;
}

/**
 * Friendly, redeemer-facing copy for every x-ray claimability status. This is
 * presentation-only: the underlying disclosure policy and voucher lifecycle
 * status strings are untouched -- see
 * `LBHurtado\XChange\Services\XRay\VoucherXRayProjectionBuilder` and
 * `LBHurtado\XRay\Policies\DefaultXRayDisclosurePolicy`.
 */
const STATUS_VIEW_MODELS: Record<string, XRayStatusViewModel> = {
    claimable: {
        label: 'Verified',
        description: '',
        badgeVariant: 'default',
        icon: CheckCircle2,
    },
    partially_claimable: {
        label: 'Partially claimable',
        description:
            'Part of this Pay Code has already been claimed. The remaining balance can still be claimed.',
        badgeVariant: 'default',
        icon: CheckCircle2,
    },
    redeemed: {
        label: 'Already claimed',
        description: 'This Pay Code has already been fully claimed.',
        badgeVariant: 'secondary',
        icon: XCircle,
    },
    expired: {
        label: 'Expired',
        description: 'This Pay Code is no longer available to claim.',
        badgeVariant: 'destructive',
        icon: Clock,
    },
    hidden: {
        label: 'Unavailable',
        description: 'This Pay Code is not currently available.',
        badgeVariant: 'secondary',
        icon: HelpCircle,
    },
    not_found: {
        label: 'Not found',
        description:
            "We couldn't find a Pay Code with that code. Double-check it and try again.",
        badgeVariant: 'destructive',
        icon: HelpCircle,
    },
};

const CHECKING_STATUS_VIEW_MODEL: XRayStatusViewModel = {
    label: 'Checking...',
    description: '',
    badgeVariant: 'secondary',
    icon: HelpCircle,
};

function humanize(value: string): string {
    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

/**
 * Resolves the friendly status card content for the claim preview. `visible`
 * (from the disclosure policy) wins over an ambiguous/unmapped status string,
 * but an explicit `not_found` status always takes precedence over a generic
 * "unavailable" fallback so redeemers get the most specific, helpful copy.
 */
export function resolveXRayStatusViewModel(input: {
    status?: string | null;
    visible?: boolean | null;
}): XRayStatusViewModel {
    const status = String(input.status ?? '').trim();

    if (status === 'not_found') {
        return STATUS_VIEW_MODELS.not_found;
    }

    if (input.visible === false) {
        return STATUS_VIEW_MODELS.hidden;
    }

    if (status === '') {
        return CHECKING_STATUS_VIEW_MODEL;
    }

    return (
        STATUS_VIEW_MODELS[status] ?? {
            ...CHECKING_STATUS_VIEW_MODEL,
            label: humanize(status),
        }
    );
}

/**
 * Friendly requirement copy, keyed by the requirement `key` the backend
 * projection assigns (see `VoucherXRayProjectionBuilder::requirements()` and
 * `::description()`). Falls back to the backend-provided label when a key is
 * unrecognized, so new/custom requirement keys never disappear -- they just
 * render without a friendly override or icon.
 */
const REQUIREMENT_LABELS: Record<string, string> = {
    mobile: 'Mobile number',
    assigned_mobile: 'Mobile number',
    bank_account: 'Wallet or bank account',
    bank_code: 'Wallet or bank account',
    account_number: 'Wallet or bank account',
    secret: 'Passcode',
    passcode: 'Passcode',
    otp: 'OTP',
    kyc: 'KYC',
    selfie: 'Selfie',
    location: 'Location',
    signature: 'Signature',
};

const REQUIREMENT_ICONS: Record<string, Component> = {
    mobile: Smartphone,
    assigned_mobile: Smartphone,
    bank_account: Wallet,
    bank_code: Wallet,
    account_number: Wallet,
    secret: KeyRound,
    passcode: KeyRound,
    otp: ShieldCheck,
    kyc: IdCard,
    selfie: Camera,
    location: MapPin,
    signature: PenLine,
};

export function resolveXRayRequirementViewModel(
    requirement: XRayRequirementLike,
): XRayRequirementViewModel {
    const key = String(requirement.key ?? '').trim();
    const fallbackLabel =
        requirement.label?.trim() || (key ? humanize(key) : 'Requirement');

    return {
        key,
        label: REQUIREMENT_LABELS[key] ?? fallbackLabel,
        description: requirement.description?.trim() || null,
        icon: REQUIREMENT_ICONS[key] ?? null,
    };
}
