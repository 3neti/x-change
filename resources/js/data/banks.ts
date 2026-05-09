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
 * Popular EMIs (shown first in dropdowns)
 */
export const POPULAR_EMIS = [
    'GXCHPHM2XXX', // GCash
    'PYMYPHM2XXX', // PayMaya
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
 * Get popular EMIs
 */
export function getPopularEMIs(): Bank[] {
    return POPULAR_EMIS
        .map(code => BANKS.find(b => b.code === code))
        .filter((b): b is Bank => b !== undefined);
}
