import { beforeEach, describe, expect, it, vi } from 'vitest';
// Wir importieren die Klasse. Falls du kein Build-System nutzt,
// muss der Pfad absolut oder relativ sein.
import { PermitFormHandler } from '../../src/assets/js/form-handler.js';

describe('PermitFormHandler', () => {
    let handler;

    // Wir bauen das HTML-Gerüst vor jedem Test nach
    beforeEach(() => {
        // Mocking der Config für das JS
        window.KGA_CONFIG = {
            baseUrl: 'http://kga-einfahrgenehmigung.local/',
            vehicleConfig: {
                pkw: { show_company: false },
                lkw: { show_company: true },
            },
        };

        document.body.innerHTML = `
            <form id="permitForm">
                <select id="typ">
                    <option value="pkw">PKW</option>
                    <option value="lkw">LKW</option>
                </select>
                <div id="u_firma_wrapper" class="u-hidden"> <input id="u_firma">
                </div>
                </form>
        `;

        // Wir holen uns die Instanz, die im Original-Skript am Ende erstellt wird
        // Klasse manuell instanziieren für volle Kontrolle
        handler = new PermitFormHandler();
        vi.spyOn(window, 'alert').mockImplementation(() => {});
    });

    describe('Formatierung', () => {
        it('sollte Parzellennummern auf 4 Stellen auffüllen (Padding)', () => {
            const input = document.getElementById('parzelle');
            input.value = '20';
            handler.formatPlotNumber(input);
            expect(input.value).toBe('0020');
        });

        it('sollte Kennzeichen korrekt formatieren (BHD7398 -> B-HD 7398)', () => {
            const input = document.getElementById('kennzeichen');
            input.value = 'bhd7398';
            handler.formatLicensePlate(input);
            expect(input.value).toBe('B-HD 7398');
        });
    });

    describe('Fahrzeug-Logik', () => {
        it('sollte das Firmenfeld bei LKW einblenden', () => {
            const groupFirma = document.getElementById('group_firma');
            handler.toggleVehicleFields('lkw');
            expect(groupFirma.classList.contains('u-hidden')).toBe(false);
        });

        it('sollte das Kennzeichen bei LKW optional machen', () => {
            const input = document.getElementById('kennzeichen');
            handler.toggleVehicleFields('lkw');
            expect(input.required).toBe(false);
        });
    });

    describe('Berliner Feiertags-Logik (Sperrtage)', () => {
        it('sollte einen Sonntag als gesperrt erkennen', () => {
            const sunday = new Date('2026-04-26'); // Ein Sonntag
            expect(handler.isRestrictedDay(sunday)).toBe(true);
        });

        it('sollte den Frauentag (8. März) in Berlin als gesperrt erkennen', () => {
            const frauentag = new Date('2026-03-08');
            expect(handler.isRestrictedDay(frauentag)).toBe(true);
        });

        it('sollte Karfreitag 2026 erkennen', () => {
            const karfreitag = new Date('2026-04-03');
            expect(handler.isRestrictedDay(karfreitag)).toBe(true);
        });

        it('sollte einen normalen Werktag erlauben', () => {
            const workday = new Date('2026-04-28'); // Dienstag
            expect(handler.isRestrictedDay(workday)).toBe(false);
        });
    });

    describe('Validierung', () => {
        it('sollte ein Alert auslösen, wenn ein gesperrtes Datum gewählt wird', () => {
            const dateInput = document.getElementById('datum_von');
            dateInput.value = '2026-04-26'; // Sonntag
            handler.validateBerlinRestrictions();
            expect(window.alert).toHaveBeenCalled();
        });
    });
});
