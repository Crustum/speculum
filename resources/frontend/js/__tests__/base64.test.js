import { describe, expect, it } from 'vitest';
import { decodeBase64, decodeBase64Json } from '../utils/base64';

describe('decodeBase64', () => {
    it('decodes UTF-8 text', () => {
        expect(decodeBase64(btoa('hello'))).toBe('hello');
    });

    it('returns null for invalid input', () => {
        expect(decodeBase64('!!!')).toBeNull();
        expect(decodeBase64('')).toBeNull();
        expect(decodeBase64(null)).toBeNull();
    });
});

describe('decodeBase64Json', () => {
    it('decodes and parses JSON', () => {
        const payload = { code: '$x = 1;', timeout: 45 };
        const encoded = btoa(JSON.stringify(payload));

        expect(decodeBase64Json(encoded)).toEqual(payload);
    });

    it('returns null when JSON is invalid', () => {
        expect(decodeBase64Json(btoa('not-json'))).toBeNull();
    });
});
