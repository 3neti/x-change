import { ref } from 'vue'

export function useFormatDate(locale = 'en-PH') {
    /**
     * Formats any ISO-string or Date into a localized
     * "long" date+time in the given locale.
     */
    function formatDate(datetime: string | Date | number): string {
        const date = new Date(datetime)
        return date.toLocaleString(locale, {
            year:   'numeric',
            month:  'long',
            day:    'numeric',
            hour:   'numeric',
            minute: 'numeric',
            second: 'numeric',
            hour12: true,
        })
    }

    return { formatDate }
}
