export function useFormatCurrency() {
    return (minor: number, locale = 'en-PH', currency = 'PHP') =>
        new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
        }).format(minor / 100)
}
