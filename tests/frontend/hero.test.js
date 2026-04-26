import { describe, expect, it } from 'vitest';
import { createHero } from '../../src/assets/js/components';

describe('Hero Component Snapshots', () => {
    it('sollte das Hero-HTML korrekt rendern', () => {
        const heroElement = createHero('Willkommen', 'Dies ist ein Test');

        // Hier passiert die Magie:
        // Vitest erstellt beim ersten Mal einen Ordner "__snapshots__"
        // und speichert das HTML dort ab.
        expect(heroElement).toMatchSnapshot();
    });
});
