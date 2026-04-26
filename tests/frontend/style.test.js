import { describe, expect, it } from 'vitest';

//import '../../src/assets/scss/main.scss'; // Importiert das komplette CSS für den Test

describe('SCSS Variable Check', () => {
    it('sollte ein Element mit einer Klasse erstellen, die wir später stylen', () => {
        const div = document.createElement('div');
        div.className = 'btn--primary';
        document.body.appendChild(div);

        // Wir prüfen hier, ob die Klasse da ist.
        // Dank jsdom könnten wir sogar Styles prüfen, falls nötig:
        // const style = window.getComputedStyle(div);
        expect(div.classList.contains('btn--primary')).toBe(true);
    });
});
