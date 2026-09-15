import { beforeEach, describe, expect, it, vi } from 'vitest';
import { notifier } from '../../src/assets/js/core/Notifier.js';
import { PermitForm } from '../../src/assets/js/modules/PermitForm.js';

// Notifier Singleton für Vitest mocken, da wir kein echtes DOM/CSS-Rendering für Toasts haben
vi.mock('../../src/assets/js/core/Notifier.js', () => ({
    notifier: { show: vi.fn() },
}));

describe('PermitFormHandler', () => {
    let handler;

    // Wir bauen das HTML-Gerüst vor jedem Test nach (jetzt mit strikten JS-Hooks)
    beforeEach(() => {
        // Mocking der Config für das JS
        window.KGA_CONFIG = {
            baseUrl: 'https://kga-einfahrgenehmigung.local/',
            vehicleConfig: {
                pkw: { show_company: false },
                lkw: { show_company: true },
            },
        };

        document.body.innerHTML = `
      <form class="js-permit-form">
        <div class="c-form-group">
          <label for="typ">Fahrzeugtyp</label>
          <select id="typ" name="typ" class="js-pf-typ">
            <option value="pkw">PKW</option>
            <option value="lkw">LKW</option>
          </select>
        </div>
        <div class="c-form-group">
          <label id="label_kennzeichen" class="js-pf-label-kennzeichen" for="kennzeichen">Kennzeichen</label>
          <input id="kennzeichen" name="kennzeichen" class="js-pf-kennzeichen">
        </div>
        <div class="c-form-group js-pf-firma-wrapper" id="group_firma" hidden>
          <label for="firma">Firma</label>
          <input id="firma" name="firma" class="js-pf-firma">
        </div>
        <input id="parzelle" name="parzelle" class="js-pf-parzelle">
        <input id="datum_von" name="datum_von" class="js-pf-von">
        <input id="datum_bis" name="datum_bis" class="js-pf-bis">
        <select name="template_key" class="js-pf-template"><option value="std_7">7 Tage</option></select>

        <div class="js-pf-price-display"></div>
      </form>
    `;

        // Instanziierung mit document.body (Da die Klasse nun ein Element erwartet)
        handler = new PermitForm(document.body);
        vi.clearAllMocks();
    });

    describe('Formatierung', () => {
        it('sollte Parzellennummern auf 4 Stellen auffüllen (Padding)', () => {
            const input = document.querySelector('.js-pf-parzelle');
            input.value = '20';
            handler.formatPlotNumber(input);
            expect(input.value).toBe('0020');
        });

        it('sollte Kennzeichen korrekt formatieren (BHD7398 -> B-HD 7398)', () => {
            const input = document.querySelector('.js-pf-kennzeichen');
            input.value = 'bhd7398';
            handler.formatLicensePlate(input);
            expect(input.value).toBe('B-HD 7398');
        });
    });

    describe('Fahrzeug-Logik', () => {
        it('sollte das Firmenfeld bei LKW einblenden', () => {
            const groupFirma = document.querySelector('.js-pf-firma-wrapper');
            handler.typSelect.value = 'lkw';
            handler.toggleVehicleFields();
            expect(groupFirma.hidden).toBe(false);
        });

        it('sollte das Kennzeichen bei LKW optional machen', () => {
            const input = document.querySelector('.js-pf-kennzeichen');
            handler.typSelect.value = 'lkw';
            handler.toggleVehicleFields();
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
        it('sollte eine Notifier-Meldung auslösen, wenn ein gesperrtes Datum gewählt wird', () => {
            const dateInput = document.querySelector('.js-pf-von');
            dateInput.value = '2026-04-26'; // Sonntag
            handler.validateBerlinRestrictions();

            expect(notifier.show).toHaveBeenCalledWith(
                expect.stringContaining('ist ein Sonn- oder Feiertag'),
                'error'
            );
        });
    });
});
