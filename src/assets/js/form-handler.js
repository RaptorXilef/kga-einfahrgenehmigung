/**
 * @file form-handler.js
 * Modulares Management des Antragsformulars (v0.4.0).
 * * Beinhaltet:
 * - Fahrzeugtyp-Umschaltung
 * - Echtzeit-Formatierung (Kennzeichen, Parzelle)
 * - Berliner Feiertags- und Sonntagsprüfung
 */

class PermitFormHandler {
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
        this.typSelect.addEventListener('change', (e) => this.toggleVehicleFields(e.target.value));
        this.kennzeichenInput.addEventListener('blur', (e) => this.formatLicensePlate(e.target));
        this.parzelleInput.addEventListener('blur', (e) => this.formatPlotNumber(e.target));

        this.dateInputs.forEach((input) => {
            input.addEventListener('change', () => this.validateBerlinRestrictions());
        });

        // Initialer Check
        this.toggleVehicleFields(this.typSelect.value);
    }

    /**
     * Schaltet Felder zwischen PKW und LKW/Firma um.
     */
    toggleVehicleFields(type) {
        const isLkw = type === 'lkw';
        this.groupFirma.classList.toggle('u-hidden', !isLkw);
        this.labelKennzeichen.innerText = isLkw
            ? 'Amtl. Kennzeichen (Optional)'
            : '* Amtl. Kennzeichen';
        this.kennzeichenInput.required = !isLkw;
    }

    /**
     * Formatiert Kennzeichen in deutsches Standard-Format (B-HD 7398).
     */
    formatLicensePlate(input) {
        const val = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (val.length > 3) {
            input.value = val.replace(/^([A-Z]{1,3})([A-Z]{0,2})([0-9]{1,4})$/, '$1-$2 $3').trim();
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
        const alerts = [];
        this.dateInputs.forEach((input) => {
            const date = new Date(input.value);
            if (isNaN(date.getTime())) return;

            if (this.isRestrictedDay(date)) {
                alerts.push(
                    `Hinweis: Der ${date.toLocaleDateString('de-DE')} ist ein Sonn- oder Feiertag. Die Einfahrt ist an diesem Tag untersagt.`
                );
            }
        });

        if (alerts.length > 0) {
            // Hier könnte man ein schöneres UI-Element nutzen, vorerst Standard-Alert
            alert(alerts.join('\n'));
        }
    }

    /**
     * Logik zur Erkennung von Sperrtagen in Berlin.
     */
    isRestrictedDay(date) {
        // 1. Sonntag?
        if (date.getDay() === 0) return true;

        const year = date.getFullYear();
        const month = date.getMonth() + 1;
        const day = date.getDate();
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
        const diffDays = Math.floor((date - easter) / (24 * 60 * 60 * 1000));

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
}

// Global instanziieren
document.addEventListener('DOMContentLoaded', () => {
    window.permitForm = new PermitFormHandler();
});
