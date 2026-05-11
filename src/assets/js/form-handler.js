// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/assets/js/form-handler.js
 * Path: public/assets/js/form-handler.min.js
 *
 * Modulares Management des Antragsformulars
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
     * Schaltet Felder zwischen PKW und LKW/Firma um.
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
     * Intelligente Kennzeichen-Formatierung (v0.4.1)
     * Berücksichtigt die Priorität für Berlin-Kennzeichen.
     */
    formatLicensePlate(input) {
        const val = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (val.length <= 3) return;

        // 1. Spezialfall Berlin: Falls es mit B beginnt und 3 Buchstaben hat (B-XX 123)
        const berlinPattern = /^(B)([A-Z]{1,2})([0-9]{1,4})$/;
        // 2. Standard Komplex: Stadt (1-3) + Zeichen (1-2) + Zahlen (1-4)
        const complexPattern = /^([A-Z]{1,3})([A-Z]{1,2})([0-9]{1,4})$/;
        // 3. Standard Simpel: Stadt (1-3) + Zahlen (1-4)
        const simplePattern = /^([A-Z]{1,3})([0-9]{1,4})$/;

        if (berlinPattern.test(val)) {
            input.value = val.replace(berlinPattern, '$1-$2 $3');
        } else if (complexPattern.test(val)) {
            input.value = val.replace(complexPattern, '$1-$2 $3');
        } else if (simplePattern.test(val)) {
            input.value = val.replace(simplePattern, '$1 $2');
        }
    }

    /**
     * Stellt sicher, dass die Parzelle immer 4-stellig ist (0020).
     */
    formatPlotNumber(input) {
        if (input.value) {
            input.value = input.value.toString().padStart(4, '0');
        }
    }

    /**
     * Prüft auf Sonntage und Berliner Feiertage.
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

    async updatePrice() {
        if (!this.tplSelect || !this.typSelect) return;

        // Nutzt die injizierte baseUrl
        const baseUrl = window.KGA_CONFIG?.baseUrl || '';

        // Gutschein suchen (wir schauen nach dem Feld 'voucher')
        const voucherInput = document.getElementsByName('voucher')[0];
        const voucher = voucherInput ? voucherInput.value : '';

        try {
            const response = await fetch(
                `${baseUrl}api/get_template_price.php?key=${this.tplSelect.value}&typ=${this.typSelect.value}&voucher=${voucher}`
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
