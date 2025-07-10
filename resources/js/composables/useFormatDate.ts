import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(relativeTime)

export function useFormatDate() {
    type FormatDateOptions = {
        fallbackFormat?: string
        invalidText?: string
        showRelative?: boolean
    }

    /**
     * Format the given date based on how recent it is.
     *
     * @param datetime - Any valid date input (string | Date | number)
     * @param options - Formatting options:
     *                  - fallbackFormat: custom format if not relative (default: 'DD HHmm[H] MMM YYYY')
     *                  - invalidText: fallback if date is invalid (default: '')
     *                  - showRelative: whether to show relative time (default: true)
     */
    function formatDate(
        datetime: string | Date | number,
        options: FormatDateOptions = {}
    ): string {
        const {
            fallbackFormat = 'DD HHmm[H] MMM YYYY',
            invalidText = '',
            showRelative = true,
        } = options

        const date = dayjs(datetime)
        if (!date.isValid()) return invalidText

        const now = dayjs()
        const diffInHours = now.diff(date, 'hour')

        if (showRelative && diffInHours < 24) {
            return date.toNow(true) + ' ago'
        }

        return date.format(fallbackFormat)
    }

    return { formatDate }
}
