import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { describe, expect, it } from 'vitest';

const root = dirname(fileURLToPath(import.meta.url));
const appSource = readFileSync(resolve(root, '../App.vue'), 'utf8');

describe('App.vue sidebar nav', () => {
    it('defines Queries and Cache links', () => {
        expect(appSource).toContain("to: '/queries'");
        expect(appSource).toContain("label: 'Queries'");
        expect(appSource).toContain("to: '/cache'");
        expect(appSource).toContain("label: 'Cache'");
        expect(appSource).toContain("to: '/mongo'");
        expect(appSource).toContain("label: 'Mongo'");
    });

    it('includes a sidebar menu filter input', () => {
        expect(appSource).toContain('Filter menu');
        expect(appSource).toContain('filteredNavItems');
        expect(appSource).toContain('navFilter');
    });

    it('dedupes extension nav against built-in watchers', () => {
        expect(appSource).toContain('builtInWatchers');
        expect(appSource).toContain('builtInPaths');
    });
});
