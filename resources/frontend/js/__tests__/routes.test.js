import { describe, expect, it } from 'vitest';
import routes from '../routes';

describe('routes', () => {
    const paths = routes.map((route) => route.path);

    it('includes /queries/:id', () => {
        expect(paths).toContain('/queries/:id');
    });

    it('includes /cache', () => {
        expect(paths).toContain('/cache');
    });

    it('includes /requests', () => {
        expect(paths).toContain('/requests');
    });

    it('includes /authorization index and /authorization/:id preview', () => {
        expect(paths).toContain('/authorization');
        expect(paths).toContain('/authorization/:id');

        const named = routes.map((route) => route.name).filter(Boolean);
        expect(named).toContain('authorization');
        expect(named).toContain('authorization-preview');
    });

    it('includes Queries and Cache sidebar targets', () => {
        expect(paths).toContain('/queries');
        expect(paths).toContain('/cache');
        expect(paths).toContain('/mongo');
        expect(paths).toContain('/searches');
        expect(paths).toContain('/mongo-queries');
        expect(paths).toContain('/mongo-query-logs');

        const named = routes.map((route) => route.name).filter(Boolean);
        expect(named).toContain('queries');
        expect(named).toContain('cache');
        expect(named).toContain('mongo');
        expect(named).toContain('searches');
        expect(named).toContain('mongo-queries');
        expect(named).toContain('mongo-query-logs');
    });
});
