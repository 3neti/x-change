import banksJson from '@/../../resources/documents/banks.json';
import { EMI_RESTRICTIONS } from '@/config/bank-restrictions';

export interface Bank {
    code: string; // SWIFT/BIC
    name: string;
    rails: ('INSTAPAY' | 'PESONET')[];
    isEMI: boolean;
}

/**
 * Parse banks.json and transform to typed Bank array
 */
export function parseBanks(): Bank[] {
    const banks: Bank[] = [];
    
    for (const [code, bankData] of Object.entries(banksJson.banks)) {
        const rails = Object.keys(bankData.settlement_rail) as ('INSTAPAY' | 'PESONET')[];
        
        banks.push({
            code,
            name: bankData.full_name,
            rails,
            isEMI: code in EMI_RESTRICTIONS,
        });
    }
    
    return banks.sort((a, b) => a.name.localeCompare(b.name));
}

/**
 * All banks/EMIs
 */
export const BANKS = parseBanks();

/**
 * Common institutions shown first in dropdowns.
 *
 * Keep this separate from EMI classification. Maya Wallet and Maya Bank are
 * distinct destinations, and only the wallet is an EMI.
 */
export const COMMON_INSTITUTIONS = [
    'GXCHPHM2XXX', // GCash
    'PAPHPHM1XXX', // Maya Wallet / PayMaya
    'MYDBPHM2XXX', // Maya Bank
    'GHPEPHM1XXX', // GrabPay
    'SHPHPHM1XXX', // ShopeePay
];

/**
 * Get bank by code
 */
export function getBank(code: string | null | undefined): Bank | undefined {
    if (!code) return undefined;
    return BANKS.find(b => b.code === code);
}

/**
 * Get banks that support a specific rail
 */
export function getBanksByRail(rail: 'INSTAPAY' | 'PESONET' | null): Bank[] {
    if (!rail) return BANKS;
    return BANKS.filter(b => b.rails.includes(rail));
}

/**
 * Get common institutions.
 */
export function getCommonInstitutions(): Bank[] {
    return COMMON_INSTITUTIONS
        .map(code => BANKS.find(b => b.code === code))
        .filter((b): b is Bank => b !== undefined);
}

/**
 * @deprecated Use getCommonInstitutions. Common placement is not the same as EMI classification.
 */
export function getPopularEMIs(): Bank[] {
    return getCommonInstitutions();
}
