import { beforeEach, describe, expect, it } from 'vitest';
import { AccordionCard } from '../../src/assets/js/ui/GlobalInteractions.js';

describe('GlobalInteractions: AccordionCard', () => {
    let cardElement;
    let toggleElement;
    let accordion;

    beforeEach(() => {
        // DOM Setup
        document.body.innerHTML = `
            <div class="c-category-card is-closed" id="test-card">
                <header class="c-category-card__header">
                    <div class="js-toggle-parent">Klick mich</div>
                </header>
                <div class="c-category-card__body">Versteckter Inhalt</div>
            </div>
        `;

        cardElement = document.getElementById('test-card');
        toggleElement = cardElement.querySelector('.js-toggle-parent');

        // Instanziierung
        accordion = new AccordionCard(cardElement);
    });

    it('sollte native Button-Semantik und ARIA-Attribute bei Initialisierung setzen', () => {
        expect(toggleElement.getAttribute('role')).toBe('button');
        expect(toggleElement.getAttribute('tabindex')).toBe('0');
        expect(toggleElement.getAttribute('aria-expanded')).toBe('false');
    });

    it('sollte beim Klicken die Klasse is-closed toggeln und aria-expanded aktualisieren', () => {
        // 1. Klick: Öffnen
        toggleElement.click();
        expect(cardElement.classList.contains('is-closed')).toBe(false);
        expect(toggleElement.getAttribute('aria-expanded')).toBe('true');

        // 2. Klick: Schließen
        toggleElement.click();
        expect(cardElement.classList.contains('is-closed')).toBe(true);
        expect(toggleElement.getAttribute('aria-expanded')).toBe('false');
    });

    it('sollte auf die Enter-Taste wie auf einen Klick reagieren (Keyboard Accessibility)', () => {
        const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
        toggleElement.dispatchEvent(enterEvent);

        expect(cardElement.classList.contains('is-closed')).toBe(false);
        expect(toggleElement.getAttribute('aria-expanded')).toBe('true');
    });

    it('sollte auf die Leertaste reagieren (Keyboard Accessibility)', () => {
        const spaceEvent = new KeyboardEvent('keydown', { key: ' ' });
        toggleElement.dispatchEvent(spaceEvent);

        expect(cardElement.classList.contains('is-closed')).toBe(false);
        expect(toggleElement.getAttribute('aria-expanded')).toBe('true');
    });
});
