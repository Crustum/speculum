/**
 * Decode a Base64 string to UTF-8 text.
 *
 * @param {string|null|undefined} value Base64 input.
 * @returns {string|null} Decoded text, or null when empty / invalid.
 */
export function decodeBase64(value) {
    if (value == null) {
        return null;
    }

    const encoded = String(value).replace(/\s+/g, '');
    if (encoded === '') {
        return null;
    }

    try {
        const binary = atob(encoded);
        const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));

        return new TextDecoder('utf-8').decode(bytes);
    } catch (_error) {
        return null;
    }
}

/**
 * Decode Base64 and parse JSON.
 *
 * @param {string|null|undefined} value Base64-encoded JSON.
 * @returns {unknown|null} Parsed value, or null on failure.
 */
export function decodeBase64Json(value) {
    const decoded = decodeBase64(value);
    if (decoded == null) {
        return null;
    }

    try {
        return JSON.parse(decoded);
    } catch (_error) {
        return null;
    }
}
