import { BANKS } from '@/data/banks';

const mobileWallets = new Set(['GXCHPHM2XXX', 'PAPHPHM1XXX']);

export function valueFlowErrors(bank: string, account: string): { bank: string | null; account: string | null } {
    const code = bank.toUpperCase();
    const bankError = BANKS.some(item => item.code.toUpperCase() === code) ? null : 'Choose a destination.';
    let accountError: string | null = null;
    if (!account) accountError = 'Enter an account number.';
    else if (mobileWallets.has(code) && !/^09\d{9}$/.test(account)) accountError = 'Enter an 11-digit mobile number starting with 09.';
    else if (!/^\d{6,34}$/.test(account)) accountError = 'Enter an account number with 6 to 34 digits.';
    return { bank: bankError, account: accountError };
}

export function isValidValueFlowDestination(destination: string): boolean {
    if (!destination || /^account\.mobile:09\d{9}\.client_funds$/.test(destination)) return true;
    const match = /^instapay\.([a-z0-9_-]*)\.(?:mobile|account):([0-9]*)$/i.exec(destination);
    if (!match) return false;
    const errors = valueFlowErrors(match[1], match[2]);
    return !errors.bank && !errors.account;
}
