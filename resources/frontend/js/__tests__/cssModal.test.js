import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { hideCssModal, showCssModal } from '../utils/cssModal';

describe('cssModal', () => {
    /** @type {HTMLElement} */
    let element;

    beforeEach(() => {
        element = document.createElement('div');
        element.className = 'modal';
        document.body.appendChild(element);
    });

    afterEach(() => {
        hideCssModal(element);
        element.remove();
        document.querySelectorAll('.modal-backdrop[data-speculum-modal]').forEach((node) => node.remove());
        document.body.classList.remove('modal-open');
    });

    it('shows with Bootstrap CSS classes and backdrop', () => {
        showCssModal(element);

        expect(element.classList.contains('show')).toBe(true);
        expect(element.style.display).toBe('block');
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(document.querySelector('.modal-backdrop[data-speculum-modal]')).not.toBeNull();
    });

    it('hides and removes backdrop when last modal closes', () => {
        showCssModal(element);
        hideCssModal(element);

        expect(element.classList.contains('show')).toBe(false);
        expect(element.style.display).toBe('none');
        expect(document.body.classList.contains('modal-open')).toBe(false);
        expect(document.querySelector('.modal-backdrop[data-speculum-modal]')).toBeNull();
    });

    it('closes the topmost modal on Escape', () => {
        const onEscape = vi.fn(() => {
            hideCssModal(element);
        });
        showCssModal(element, { onEscape });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(onEscape).toHaveBeenCalledTimes(1);
        expect(element.classList.contains('show')).toBe(false);
    });

    it('Escape closes only the top modal when stacked', () => {
        const second = document.createElement('div');
        second.className = 'modal';
        document.body.appendChild(second);

        const firstEscape = vi.fn();
        const secondEscape = vi.fn(() => {
            hideCssModal(second);
        });

        showCssModal(element, { onEscape: firstEscape });
        showCssModal(second, { onEscape: secondEscape });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(secondEscape).toHaveBeenCalledTimes(1);
        expect(firstEscape).not.toHaveBeenCalled();

        hideCssModal(second);
        second.remove();
    });
});
