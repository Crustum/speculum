/**
 * Relative and local clock formatting for Speculum timestamps (ISO-8601 preferred).
 *
 * Naive `Y-m-d H:i:s` values are interpreted in `window.Speculum.timezone` (app TZ),
 * not as UTC — matching Cake storage of wall-clock datetimes.
 *
 * @returns {{ timeAgo: (time: string|Date|null|undefined) => string, localTime: (time: string|Date|null|undefined) => string }}
 */
export function useTimeAgo() {
    /**
     * @returns {string}
     */
    function appTimeZone() {
        return window.Speculum?.timezone || 'UTC';
    }

    /**
     * @param {Date} date
     * @param {string} timeZone
     * @returns {{ year: string, month: string, day: string, hour: string, minute: string, second: string }}
     */
    function zonedParts(date, timeZone) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date);

        /** @type {Record<string, string>} */
        const map = {};
        for (const part of parts) {
            if (part.type !== 'literal') {
                map[part.type] = part.value;
            }
        }

        return {
            year: map.year,
            month: map.month,
            day: map.day,
            hour: map.hour,
            minute: map.minute,
            second: map.second,
        };
    }

    /**
     * Interpret a naive wall clock as an instant in the given IANA timezone.
     *
     * @param {string} naive `YYYY-MM-DD HH:mm:ss`
     * @param {string} timeZone
     * @returns {Date|null}
     */
    function parseNaiveInTimeZone(naive, timeZone) {
        const match = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/.exec(naive);
        if (!match) {
            return null;
        }

        const [, year, month, day, hour, minute, second] = match;
        const asUtc = Date.UTC(
            Number(year),
            Number(month) - 1,
            Number(day),
            Number(hour),
            Number(minute),
            Number(second),
        );

        let utcMillis = asUtc;
        for (let i = 0; i < 3; i++) {
            const parts = zonedParts(new Date(utcMillis), timeZone);
            const shownAsUtc = Date.UTC(
                Number(parts.year),
                Number(parts.month) - 1,
                Number(parts.day),
                Number(parts.hour),
                Number(parts.minute),
                Number(parts.second),
            );
            utcMillis += asUtc - shownAsUtc;
        }

        const date = new Date(utcMillis);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    /**
     * @param {string|Date|null|undefined} time
     * @returns {Date|null}
     */
    function parseTime(time) {
        if (time == null || time === '') {
            return null;
        }

        if (time instanceof Date) {
            return Number.isNaN(time.getTime()) ? null : time;
        }

        const value = String(time).trim();
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) {
            return parseNaiveInTimeZone(value, appTimeZone());
        }

        const date = new Date(value);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    /**
     * @param {number} day
     * @returns {string}
     */
    function dayOrdinal(day) {
        const mod100 = day % 100;
        if (mod100 >= 11 && mod100 <= 13) {
            return `${day}th`;
        }

        switch (day % 10) {
            case 1:
                return `${day}st`;
            case 2:
                return `${day}nd`;
            case 3:
                return `${day}rd`;
            default:
                return `${day}th`;
        }
    }

    /**
     * @param {number} secondsElapsed Positive when in the past.
     * @returns {string}
     */
    function formatOlder(secondsElapsed) {
        const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'always' });
        const abs = Math.abs(secondsElapsed);
        const sign = secondsElapsed >= 0 ? -1 : 1;

        if (abs < 3600) {
            return rtf.format(sign * Math.round(abs / 60), 'minute');
        }

        if (abs < 86400) {
            return rtf.format(sign * Math.round(abs / 3600), 'hour');
        }

        if (abs < 2592000) {
            return rtf.format(sign * Math.round(abs / 86400), 'day');
        }

        if (abs < 31536000) {
            return rtf.format(sign * Math.round(abs / 2592000), 'month');
        }

        return rtf.format(sign * Math.round(abs / 31536000), 'year');
    }

    /**
     * @param {string|Date|null|undefined} time
     * @returns {string}
     */
    function timeAgo(time) {
        const date = parseTime(time);
        if (!date) {
            return '';
        }

        const secondsElapsed = Math.floor((Date.now() - date.getTime()) / 1000);
        if (secondsElapsed < 0) {
            return formatOlder(secondsElapsed);
        }

        if (secondsElapsed > 300) {
            return formatOlder(secondsElapsed);
        }

        if (secondsElapsed < 60) {
            return `${secondsElapsed}s ago`;
        }

        const minutes = Math.floor(secondsElapsed / 60);
        const seconds = secondsElapsed % 60;

        return `${minutes}:${String(seconds).padStart(2, '0')}m ago`;
    }

    /**
     * Format in Speculum app timezone (not necessarily the browser TZ).
     *
     * @param {string|Date|null|undefined} time
     * @returns {string}
     */
    function localTime(time) {
        const date = parseTime(time);
        if (!date) {
            return '';
        }

        const timeZone = appTimeZone();
        const parts = zonedParts(date, timeZone);
        const month = new Intl.DateTimeFormat('en', { month: 'long', timeZone }).format(date);
        const day = dayOrdinal(Number(parts.day));
        const year = parts.year;
        const clock = new Intl.DateTimeFormat('en', {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone,
        }).format(date);

        return `${month} ${day} ${year}, ${clock}`;
    }

    return {
        timeAgo,
        localTime,
    };
}
