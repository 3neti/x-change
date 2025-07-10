export function useFlagEmoji() {
    function getFlagEmoji(countryCode: string): string {
        if (!countryCode || countryCode.length !== 2) return '🏳️'; // fallback to generic flag
        const codePoints = countryCode
            .toUpperCase()
            .split('')
            .map(char => 127397 + char.charCodeAt(0));
        return String.fromCodePoint(...codePoints);
    }

    return { getFlagEmoji };
}
