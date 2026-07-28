import { describe, expect, it } from 'vitest';
import { formatSql } from '../utils/formatSql';

describe('formatSql', () => {
    it('formats a normal SELECT statement', () => {
        const result = formatSql('SELECT id, name FROM users WHERE id = 1');

        expect(result).toContain('SELECT');
        expect(result).toContain('FROM');
        expect(result).toContain('users');
        expect(result).not.toBe('SELECT id, name FROM users WHERE id = 1');
    });

    it('returns the raw string on parse error without throwing', () => {
        const raw = 'connection={connection} SELECT 1';

        expect(() => formatSql(raw)).not.toThrow();
        expect(formatSql(raw)).toBe(raw);
    });

    it('returns an empty string for empty or null input', () => {
        expect(formatSql('')).toBe('');
        expect(formatSql(null)).toBe('');
        expect(formatSql(undefined)).toBe('');
    });

    it('maps pgsql to postgresql dialect', () => {
        const result = formatSql('SELECT 1', 'pgsql');

        expect(result).toContain('SELECT');
        expect(typeof result).toBe('string');
    });

    it('maps sqlsrv to transactsql dialect', () => {
        const result = formatSql('SELECT 1', 'sqlsrv');

        expect(result).toContain('SELECT');
        expect(typeof result).toBe('string');
    });
});
