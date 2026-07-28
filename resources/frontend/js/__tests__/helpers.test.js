import { describe, expect, it, beforeEach, afterEach } from 'vitest';
import { useHelpers } from '../composables/useHelpers';
import { useTimeAgo } from '../composables/useTimeAgo';

describe('useHelpers.truncate', () => {
    const { truncate } = useHelpers();

    it('returns empty string for falsy input', () => {
        expect(truncate('')).toBe('');
        expect(truncate(null)).toBe('');
        expect(truncate(undefined)).toBe('');
    });

    it('returns the full string when under the length limit', () => {
        expect(truncate('short', 70)).toBe('short');
    });

    it('truncates long strings with an ellipsis', () => {
        const result = truncate('abcdefghijklmnopqrstuvwxyz', 10);

        expect(result.endsWith('...')).toBe(true);
        expect(result.length).toBeLessThanOrEqual(10);
    });
});

describe('useTimeAgo.timeAgo', () => {
    const { timeAgo, localTime } = useTimeAgo();
    /** @type {unknown} */
    let previousSpeculum;

    beforeEach(() => {
        previousSpeculum = window.Speculum;
        window.Speculum = { path: 'speculum', timezone: 'Europe/Moscow', recording: true };
    });

    afterEach(() => {
        window.Speculum = previousSpeculum;
    });

    it('returns a relative time string for a recent timestamp', () => {
        const recent = new Date(Date.now() - 10_000).toISOString();
        const result = timeAgo(recent);

        expect(typeof result).toBe('string');
        expect(result.length).toBeGreaterThan(0);
        expect(result).toMatch(/ago$/);
    });

    it('returns a relative time string for an older timestamp', () => {
        const older = new Date(Date.now() - 10 * 60_000).toISOString();
        const result = timeAgo(older);

        expect(typeof result).toBe('string');
        expect(result.length).toBeGreaterThan(0);
    });

    it('formats localTime for ISO timestamps in Speculum timezone', () => {
        const formatted = localTime('2026-07-24T02:30:00Z');

        expect(formatted).toMatch(/July/);
        expect(formatted).toMatch(/2026/);
        expect(formatted).toMatch(/5:30:00 AM/);
    });

    it('treats legacy naive datetimes as Speculum timezone wall clock', () => {
        const formatted = localTime('2026-07-24 05:30:00');

        expect(formatted).toMatch(/July/);
        expect(formatted).toMatch(/5:30:00 AM/);
    });
});
