const bankAliases: Record<string, string> = {
    BNORPHMMXXX: 'BDO',
    GXCHPHM2XXX: 'GCash',
};

export function useBankAlias() {
    function getBankAlias(bankCode: string): string {
        return bankAliases[bankCode] ?? bankCode;
    }

    return { getBankAlias };
}
