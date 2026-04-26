import { beforeEach, describe, expect, it } from 'vitest';
import { updateHeroText } from '../../src/assets/js/domUtils';

describe('DOM Utils', () => {
    beforeEach(() => {
        // Wir bauen uns für jeden Test ein sauberes Stück HTML im Speicher
        document.body.innerHTML = '<h1 id="hero-title">Alt</h1>';
    });

    it('sollte den Text des Hero-Elements ändern', () => {
        const title = document.getElementById('hero-title');

        updateHeroText(title, 'Vite ist cool!');

        expect(title.textContent).toBe('Vite ist cool!');
    });

    it('sollte nicht abstürzen, wenn das Element fehlt', () => {
        expect(() => updateHeroText(null, 'Fehler')).not.toThrow();
    });
});
