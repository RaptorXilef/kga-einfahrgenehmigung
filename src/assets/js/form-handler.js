/**
 * Modulares Management des öffentlichen Antragsformulars.
 * Übernimmt die dynamische Sichtbarkeit von Firmenfeldern, die Validierung von Terminen gegen
 * Berliner Sonn- und Feiertage (Gauß-Osterformel) sowie asynchrone Tarifpreis-Berechnungen
 * inklusive Gutschein-Livechecks über die API.
 * Kontext: Validierungs- und Berechnungs-Layer des clientseitigen Antrags-Formulars.
 *
 * Path: src/assets/js/form-handler.js
 * Path: public/assets/js/form-handler.min.js
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 *
 * Beinhaltet:
 * - Fahrzeugtyp-Umschaltung
 * - Echtzeit-Formatierung (Kennzeichen, Parzelle)
 * - Berliner Feiertags- und Sonntagsprüfung
 */

export class PermitFormHandler {
    // Export hinzugefügt
    constructor() {
        // DOM Elemente
        this.form = document.getElementById('permitForm');
        this.typSelect = document.getElementById('typ');
        this.groupFirma = document.getElementById('group_firma');
        this.kennzeichenInput = document.getElementById('kennzeichen');
        this.labelKennzeichen = document.getElementById('label_kennzeichen');
        this.parzelleInput = document.getElementById('parzelle');
        this.dateInputs = [
            document.getElementById('datum_von'),
            document.getElementById('datum_bis'),
        ];

        // Initialisierung
        if (this.form) {
            this.init();
        }
    }

    /**
     * Registriert sämtliche Interaktions-Listener (blur, change) für Formular-Elemente.
     * Triggert zudem die initiale Preiskalkulation und die initiale Feldsteuerung für Fahrzeuge.
     *
     * @return {void}
     */
    init() {
        // Event-Listener registrieren
        this.typSelect?.addEventListener('change', (e) => this.toggleVehicleFields(e.target.value));
        this.kennzeichenInput?.addEventListener('blur', (e) => this.formatLicensePlate(e.target));
        this.parzelleInput?.addEventListener('blur', (e) => this.formatPlotNumber(e.target));

        this.dateInputs.forEach((input) => {
            input?.addEventListener('change', () => this.validateBerlinRestrictions());
        });

        if (this.typSelect) {
            // Initialer Check für die Ansicht
            this.toggleVehicleFields(this.typSelect.value);
        }

        this.tplSelect = document.getElementById('template_key');
        this.priceDisplay = document.getElementById('price-display');

        this.tplSelect?.addEventListener('change', () => this.updatePrice());
        this.typSelect?.addEventListener('change', () => this.updatePrice());

        // Initialer Preisaufruf
        this.updatePrice();
    }

    /**
     * Steuert die Sichtbarkeit und Pflichtfeld-Attribute firmenrelevanter Eingabefelder.
     * Schaltet Felder zwischen PKW und LKW/Firma um.
     *
     * @param {string} type Der gewählte Fahrzeugtyp-Key (z.B. 'pkw', 'lkw').
     *
     * @return {void}
     */
    toggleVehicleFields(type) {
        // Holen der Info aus der v0.28.0 Config (pkw)
        const cfg = window.KGA_CONFIG.vehicleConfig[type] || { show_company: false };
        const isCompanyRequired = cfg.show_company;

        if (this.groupFirma) this.groupFirma.classList.toggle('u-hidden', !isCompanyRequired);

        if (this.labelKennzeichen) {
            this.labelKennzeichen.innerText = isCompanyRequired
                ? 'Amtl. Kennzeichen (Optional)'
                : '* Amtl. Kennzeichen';
        }
        if (this.kennzeichenInput) this.kennzeichenInput.required = !isCompanyRequired;
    }

    /**
     * Normalisiert Kennzeichen-Eingaben über Regex-Ersetzungen auf deutsche Standard-Formate.
     * Fügt Bindestriche und Leerzeichen ein (z.B. "B-MW 1234E") bei Blur des Feldes.
     *
     * @param {HTMLInputElement} input Das DOM-Objekt des Kennzeichen-Textfelds.
     *
     * @return {void} Modifiziert den Value des Elements direkt.
     */
    formatLicensePlate(input) {
        const val = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (val.length <= 3) return;

        // RegEx erweitert um [EH]? am Ende für E-Autos und Oldtimer
        // 1. Spezialfall Berlin: Falls es mit B beginnt und 3 Buchstaben hat (B-XX 123)
        const berlinPattern = /^(B)([A-Z]{1,2})([0-9]{1,4}[EH]?)$/;
        // 2. Standard Komplex: Stadt (1-3) + Zeichen (1-2) + Zahlen (1-4)
        const complexPattern = /^([A-Z]{1,3})([A-Z]{1,2})([0-9]{1,4}[EH]?)$/;
        // 3. Standard Simpel: Stadt (1-3) + Zahlen (1-4)
        const simplePattern = /^([A-Z]{1,3})([0-9]{1,4}[EH]?)$/;

        if (berlinPattern.test(val)) {
            input.value = val.replace(berlinPattern, '$1-$2 $3');
        } else if (complexPattern.test(val)) {
            input.value = val.replace(complexPattern, '$1-$2 $3');
        } else if (simplePattern.test(val)) {
            input.value = val.replace(simplePattern, '$1 $2');
        }
    }

    /**
     * Formatiert die Parzellennummer via Left-Padding mit Nullen auf exakt 4 Stellen (z.B. "0042").
     *
     * @param {HTMLInputElement} input Das Parzellen-Eingabefeld.
     *
     * @return {void}
     */
    formatPlotNumber(input) {
        if (input.value) {
            input.value = input.value.toString().padStart(4, '0');
        }
    }

    /**
     * Überprüft die gewählten Datumsfelder und warnt den Nutzer bei Sonn- und Feiertagen.
     * Erzeugt bei einem Treffer ein natives Browser-Alert-Fenster.
     *
     * @return {void}
     */
    validateBerlinRestrictions() {
        this.dateInputs.forEach((input) => {
            if (!input.value) return;
            const date = new Date(input.value);
            if (Number.isNaN(date.getTime())) return;

            if (this.isRestrictedDay(date)) {
                // Wir feuern den Alert sofort pro Feld
                alert(
                    `Hinweis: Der ${date.toLocaleDateString('de-DE')} ist ein Sonn- oder Feiertag. Die Einfahrt ist untersagt.`
                );
            }
        });
    }

    /**
     * Logik zur Erkennung von Sperrtagen in Berlin.
     *
     * Kern-Prüfalgorithmus zur Identifizierung von Berliner Restriktionstagen.
     * Gleicht das Datum gegen Sonntage (Day 0), feste Feiertage (Frauentag, Knaben, etc.)
     * und variable, Oster-abhängige Feiertage (Karfreitag, Ostermontag, Himmelfahrt, Pfingsten) ab.
     *
     * @param {Date} date Das zu evaluierende JavaScript Date-Objekt.
     *
     * @return {boolean} True, wenn an diesem Tag ein Einfahrtsverbot vorliegt.
     */
    isRestrictedDay(date) {
        // Zeit auf 0 setzen für sauberen Vergleich
        // 1. Sonntag?
        const checkDate = new Date(date);
        checkDate.setHours(0, 0, 0, 0);

        if (checkDate.getDay() === 0) return true;

        const year = checkDate.getFullYear();
        const month = checkDate.getMonth() + 1;
        const day = checkDate.getDate();
        const dateStr = `${month}-${day}`;

        // 2. Feste Berliner Feiertage
        const fixedHolidays = [
            '1-1', // Neujahr
            '3-8', // Frauentag (Berlin Spezial)
            '5-1', // Tag der Arbeit
            '10-3', // Einheit
            '12-25', // 1. Weihnacht
            '12-26', // 2. Weihnacht
        ];
        if (fixedHolidays.includes(dateStr)) return true;

        // 3. Bewegliche Feiertage (Ostern-basiert)
        const easter = this.getEaster(year);
        easter.setHours(0, 0, 0, 0);

        const diffDays = Math.round((checkDate - easter) / (24 * 60 * 60 * 1000));
        const relativeHolidays = [
            -2, // Karfreitag
            1, // Ostermontag
            39, // Himmelfahrt
            50, // Pfingstmontag
        ];

        return relativeHolidays.includes(diffDays);
    }

    /**
     * Gauß-Formel zur Berechnung des Ostersonntags.
     *
     * Berechnet den Ostersonntag für ein bestimmtes Jahr (Meeus/Jones/Butcher-Algorithmus).
     *
     * @param {number} year Das Zieljahr (z.B. 2026).
     *
     * @return {Date} Das berechnete Date-Objekt des Ostersonntags für das Jahr.
     */
    getEaster(year) {
        const a = year % 19,
            b = Math.floor(year / 100),
            c = year % 100,
            d = Math.floor(b / 4),
            e = b % 4,
            f = Math.floor((b + 8) / 25),
            g = Math.floor((b - f + 1) / 3),
            h = (19 * a + b - d - g + 15) % 30,
            i = Math.floor(c / 4),
            k = c % 4,
            l = (32 + 2 * e + 2 * i - h - k) % 7,
            m = Math.floor((a + 11 * h + 22 * l) / 451),
            month = Math.floor((h + l - 7 * m + 114) / 31),
            day = ((h + l - 7 * m + 114) % 31) + 1;
        return new Date(year, month - 1, day);
    }

    /**
     * Fragt asynchron den finalen Bruttobetrag inklusive Gutschein-Abschlägen über die REST-API ab.
     * Übergibt den CSRF-Token im Header und aktualisiert das UI-Element `priceDisplay` sowie dessen Title-Attribut.
     *
     * @async
     * @return {Promise<void>}
     */
    async updatePrice() {
        if (!this.tplSelect || !this.typSelect) return;

        // Nutzt die injizierte baseUrl
        const baseUrl = window.KGA_CONFIG?.baseUrl || '';

        // Gutschein suchen (wir schauen nach dem Feld 'voucher')
        const voucherInput = document.getElementsByName('voucher')[0];
        const voucher = voucherInput ? voucherInput.value : '';

        try {
            const response = await fetch(
                `${baseUrl}api/get_template_price.php?key=${this.tplSelect.value}&typ=${this.typSelect.value}&voucher=${voucher}`,
                {
                    // Den Schlüssel im Header mitsenden (API-Key)
                    headers: {
                        'X-CSRF-Token': window.KGA_CONFIG.csrfToken,
                    },
                }
            );
            const data = await response.json();

            if (this.priceDisplay && data.success) {
                this.priceDisplay.innerText = `Gebühr: ${data.formatted}`;

                // Falls ein Rabatt aktiv ist, zeigen wir das an
                if (data.discountText) {
                    this.priceDisplay.title = data.discountText; // Als Tooltip
                }
            }
        } catch (e) {
            // Oben über den try-catch Block oder direkt über die Zeile: // TODO Später entfernen
            // biome-ignore lint/suspicious/noConsole: Fehler müssen in der Konsole zur Diagnose sichtbar sein
            console.error('Preis-Update fehlgeschlagen', e);
        }
    }
}

// Auto-Init für den Browser (wird im Test ignoriert, wenn wir die Klasse manuell instanziieren)
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        window.permitForm = new PermitFormHandler();
    });
}
