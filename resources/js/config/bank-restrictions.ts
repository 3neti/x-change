/**
 * Bank Rail Restrictions Configuration (Frontend)
 * 
 * This mirrors the backend config in packages/payment-gateway/config/bank-restrictions.php
 * to provide frontend validation for settlement rail selection.
 * 
 * IMPORTANT: Keep this synchronized with the backend config.
 */

export interface BankRestriction {
    allowed_rails: string[];
    name: string;
    reason: string;
}

export interface AmountLimit {
    min: number;
    max: number;
    currency: string;
}

export const EMI_RESTRICTIONS: Record<string, BankRestriction> = {
    GXCHPHM2XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'GCash',
        reason: 'EMI - Real-time transfers only',
    },
    PYMYPHM2XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'PayMaya',
        reason: 'EMI - Real-time transfers only',
    },
    APHIPHM2XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'Alipay / Lazada Wallet',
        reason: 'EMI - Real-time transfers only',
    },
    BFSRPHM2XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'Banana Fintech / BananaPay',
        reason: 'EMI - Real-time transfers only',
    },
    DCPHPHM1XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'DragonPay',
        reason: 'EMI - Real-time transfers only',
    },
    GHPEPHM1XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'GrabPay',
        reason: 'EMI - Real-time transfers only',
    },
    SHPHPHM1XXX: {
        allowed_rails: ['INSTAPAY'],
        name: 'ShopeePay',
        reason: 'EMI - Real-time transfers only',
    },
};

export const AMOUNT_LIMITS: Record<string, AmountLimit> = {
    INSTAPAY: {
        min: 1,
        max: 50000,
        currency: 'PHP',
    },
    PESONET: {
        min: 1,
        max: 1000000,
        currency: 'PHP',
    },
};

/**
 * Check if a bank code is an EMI (Electronic Money Issuer)
 */
export function isEMI(bankCode: string | null | undefined): boolean {
    if (!bankCode) return false;
    return bankCode in EMI_RESTRICTIONS;
}

/**
 * Get allowed rails for a bank code
 */
export function getAllowedRails(bankCode: string | null | undefined): string[] {
    if (!bankCode) return ['INSTAPAY', 'PESONET'];
    
    const restriction = EMI_RESTRICTIONS[bankCode];
    if (restriction) {
        return restriction.allowed_rails;
    }
    
    // Traditional banks support both rails
    return ['INSTAPAY', 'PESONET'];
}

/**
 * Check if a rail is allowed for a bank
 */
export function isRailAllowed(bankCode: string | null | undefined, rail: string | null): boolean {
    if (!rail) return true;
    const allowedRails = getAllowedRails(bankCode);
    return allowedRails.includes(rail);
}

/**
 * Get EMI name by bank code
 */
export function getEMIName(bankCode: string): string | null {
    return EMI_RESTRICTIONS[bankCode]?.name || null;
}
