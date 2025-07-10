export function useFormatCurrency() {
    return (
        value: number,
        options: {
            locale?: string
            currency?: string
            detailed?: boolean
            isMinor?: boolean
        } = {}
    ) => {
        const {
            locale = 'en-PH',
            currency = 'PHP',
            detailed = false,
            isMinor = false,
        } = options

        const amount = isMinor ? value / 100 : value

        const formatter = new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })

        if (!detailed) return formatter.format(amount)

        const parts = formatter.formatToParts(amount)

        return {
            symbol: parts.find(p => p.type === 'currency')?.value ?? '',
            amount: parts
                .filter(p => p.type !== 'currency')
                .map(p => p.value)
                .join(''),
        }
    }
}
