import { describe, expect, it } from 'vitest';
import { prettyPrintPhp } from '../utils/formatPhp';

describe('prettyPrintPhp', () => {
    it('indents braces and statements', () => {
        const result = prettyPrintPhp('$a = 1; if ($a) { return $a; }');

        expect(result).toContain('$a = 1;');
        expect(result).toContain('if ($a) {');
        expect(result).toContain('    return $a;');
        expect(result).toContain('}');
    });

    it('returns empty string for empty input', () => {
        expect(prettyPrintPhp('')).toBe('');
        expect(prettyPrintPhp(null)).toBe('');
    });
});
