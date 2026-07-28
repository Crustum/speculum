import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ExceptionCodePreview from '../components/ExceptionCodePreview.vue';

describe('ExceptionCodePreview', () => {
    it('escapes HTML on the highlighted line via hljs', () => {
        const wrapper = mount(ExceptionCodePreview, {
            props: {
                lines: {
                    10: '<img src=x onerror=alert(1)>',
                    11: '$x = 1;',
                },
                highlightedLine: 10,
            },
        });

        const html = wrapper.html();
        expect(html).not.toMatch(/<img\s/i);
        expect(html).toContain('&lt;img');
    });
});
