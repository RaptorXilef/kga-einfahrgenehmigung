import { api } from '../core/Api.js';
import { notifier } from '../core/Notifier.js';

/**
 * Modulares Management des Antragsformulars (Frontend & Admin).
 * Übernimmt dynamische Sichtbarkeiten, API-Preis-Berechnung, Feiertagsprüfung
 * und Datums-Synchronisation (Start-/Enddatum).
 * Scope-basiert: Kann mehrfach pro Seite (z.B. Admin-Tools) instanziiert werden.
 */
export class PermitForm {
    constructor(container) {
        this.container = container;

        // Formularfelder dynamisch aus dem Container fischen (Unterstützt Frontend & Admin)
        this.tplSelect = this.container.querySelector('[name="template_key"]');
        this.typSelect = this.container.querySelector('[name="typ"]');
        this.vonInput = this.container.querySelector('[name="datum_von"]');
        this.bisInput = this.container.querySelector('[name="datum_bis"]');
        this.parzelleInput = this.container.querySelector('[name="parzelle"]');
        this.kennzeichenInput = this.container.querySelector('[name="kennzeichen"]');
        this.firmaInput = this.container.querySelector('[name="firma"]');
        this.firmaWrapper = this.firmaInput?.closest('.c-form-group');
        this.labelKennzeichen =
            this.container.querySelector('#label_kennzeichen') ||
            this.kennzeichenInput?.closest('.c-form-group')?.querySelector('label');

        // Frontend & Admin Preis-Anzeige
        this.priceDisplay = this.container.querySelector('#price-display');
        this.voucherInput = this.container.querySelector('[name="voucher"]');

        // Admin-Spezifische Felder (Gutschein-Generator & Manuelle Anlage)
        this.voucherDiscountType = this.container.querySelector('[name="voucher_discount_type"]');
        this.voucherValueWrap = this.container.querySelector('#v_val_wrap');
        this.zweckSelect = this.container.querySelector('[name="zweck"], [name="_unused_zweck"]');
        this.zweckManual = this.container.querySelector('#zweck_manual');
        this.toggleZweckBtn = this.container.querySelector('#toggle_zweck_btn');

        // Frontend-Spezifische Info-Boxen
        this.warningBox = this.container.querySelector('#date-warning');
        this.warningText = this.container.querySelector('#date-warning-text');
        this.dateInfoContainer = this.container.querySelector('#dynamic-date-info');
        this.openingEl = this.container.querySelector('#dynamic-opening-hours');
        this.holidayEl = this.container.querySelector('#dynamic-holiday-notice');

        // Config sicher abrufen
        this.config = window.KGA_CONFIG || { vehicleConfig: {} };
        this.templates = window.KGA_TEMPLATES || {}; // Metadaten müssen vom PHP in window.KGA_TEMPLATES geschrieben werden!

        this.init();
    }

    init() {
        // Honeypot nur im Frontend-Formular injizieren
        if (this.container.id === 'permitForm' && !window.location.pathname.includes('/admin')) {
            this.injectSmartHoneypot();
        }

        // Basis-Event-Listener
        this.typSelect?.addEventListener('change', () => {
            this.toggleVehicleFields();
            this.updatePrice();
        });

        this.kennzeichenInput?.addEventListener('blur', (e) => this.formatLicensePlate(e.target));
        this.parzelleInput?.addEventListener('blur', (e) => this.formatPlotNumber(e.target));

        // Datums-Synchronisation (Vorgabe-Tage vs Custom)
        this.tplSelect?.addEventListener('change', () => {
            this.handleDateChange('template');
            this.updatePrice();
        });

        this.vonInput?.addEventListener('change', () => {
            this.handleDateChange('von');
            this.updatePrice();
            this.validateBerlinRestrictions();
        });

        this.bisInput?.addEventListener('change', () => {
            this.handleDateChange('bis');
            this.updatePrice();
            this.validateBerlinRestrictions();
        });

        // Admin: Gutschein-Rabattart Toggle
        this.voucherDiscountType?.addEventListener('change', () => this.updateAdminVoucherUI());

        // Admin: Zweck Toggle (Dropdown vs Text)
        this.toggleZweckBtn?.addEventListener('click', () => this.toggleZweckMode());

        // Frontend: Gutschein-Toggle (Die Elemente liegen in der public View außerhalb des form-Tags!)
        const voucherToggle = document.querySelector('.c-voucher-toggle');
        const voucherWrap = document.querySelector('#voucher-container');

        if (voucherToggle && voucherWrap && !voucherToggle.dataset.bound) {
            voucherToggle.dataset.bound = 'true'; // Doppeltes Binden verhindern
            voucherToggle.addEventListener('click', () => {
                voucherWrap.classList.toggle('is-open');
            });
        }

        // Initiale Aufrufe, um das UI beim Laden glattzuziehen
        this.enforceMinDates();
        this.toggleVehicleFields();
        this.handleDateChange('init');
        this.updatePrice();
        this.updateAdminVoucherUI();
    }

    enforceMinDates() {
        if (!this.vonInput) return;
        const todayStr = new Date().toISOString().split('T')[0];
        this.vonInput.min = todayStr;
    }

    injectSmartHoneypot() {
        const hpContainer = document.createElement('div');
        hpContainer.className = 'c-form-group-hp';
        hpContainer.setAttribute('aria-hidden', 'true');

        const hpLabel = document.createElement('label');
        hpLabel.innerText = 'Bitte lassen Sie dieses Feld leer, wenn Sie ein Mensch sind.';
        hpLabel.htmlFor = 'hp_contact_website';

        const hpInput = document.createElement('input');
        hpInput.type = 'text';
        hpInput.name = 'hp_contact_website';
        hpInput.id = 'hp_contact_website';
        hpInput.tabIndex = -1;
        hpInput.autocomplete = 'off';

        hpContainer.appendChild(hpLabel);
        hpContainer.appendChild(hpInput);

        const submitBtn = this.container.querySelector('button[type="submit"]');
        if (submitBtn) {
            this.container.insertBefore(hpContainer, submitBtn);
        } else {
            this.container.appendChild(hpContainer);
        }
    }

    toggleVehicleFields() {
        if (!this.typSelect) return;

        const type = this.typSelect.value;
        const cfg = this.config.vehicleConfig[type] || { show_company: false };
        const isCompanyRequired = cfg.show_company;

        if (this.firmaWrapper) {
            this.firmaWrapper.classList.toggle('u-hidden', !isCompanyRequired);
        }

        if (this.labelKennzeichen) {
            this.labelKennzeichen.innerText = isCompanyRequired
                ? 'Amtl. Kennzeichen (Optional)'
                : '* Amtl. Kennzeichen';
        }

        if (this.kennzeichenInput) {
            this.kennzeichenInput.required = !isCompanyRequired;
        }

        if (!isCompanyRequired && this.firmaInput) {
            this.firmaInput.value = '';
        }
    }

    formatLicensePlate(input) {
        if (input.readOnly) return;
        let val = input.value.toUpperCase().trim();
        if (val.length <= 1) return;

        val = val.replace(/[^A-ZÄÖÜ0-9\-\s]/g, '');

        if (val.includes('-')) {
            val = val.replace(/-+/g, '-').replace(/\s+/g, ' ');
            input.value = val.replace(/([A-ZÄÖÜ])(\d)/g, '$1 $2').trim();
            return;
        }

        const clean = val.replace(/[^A-ZÄÖÜ0-9]/g, '');
        const patternBerlin = /^(B)([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/;
        const pattern32 = /^([A-ZÄÖÜ]{3})([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/;
        const pattern12 = /^([A-ZÄÖÜ]{1,2})([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/;
        const fallback = /^([A-ZÄÖÜ]{1,3})(\d{1,4}[EH]?)$/;

        if (patternBerlin.test(clean)) input.value = clean.replace(patternBerlin, '$1-$2 $3');
        else if (pattern32.test(clean)) input.value = clean.replace(pattern32, '$1-$2 $3');
        else if (pattern12.test(clean)) input.value = clean.replace(pattern12, '$1-$2 $3');
        else if (fallback.test(clean)) input.value = clean.replace(fallback, '$1 $2');
        else input.value = val.replace(/\s+/g, ' ').trim();
    }

    formatPlotNumber(input) {
        if (input.value && !input.readOnly) {
            input.value = input.value.toString().padStart(4, '0');
        }
    }

    handleDateChange(source) {
        if (!this.bisInput || !this.vonInput || !this.tplSelect) return;
        if (source === 'bis' && this.bisInput.readOnly) return;
        if (source === 'von' && this.vonInput.readOnly) return;

        const config = this.templates[this.tplSelect.value];
        if (!config) return;

        const todayStr = new Date().toISOString().split('T')[0];
        if (source === 'von' && this.vonInput.value < todayStr) {
            this.vonInput.value = todayStr;
        }

        const isCustom = config.days === 'custom';
        const duration = isCustom ? 0 : parseInt(config.days, 10);
        const durationOffset = Math.max(0, duration - 1);

        if (this.warningBox) this.warningBox.style.display = 'none';

        if (!isCustom) {
            const minBisDate = new Date();
            minBisDate.setDate(minBisDate.getDate() + durationOffset);
            const minBisStr = minBisDate.toISOString().split('T')[0];
            this.bisInput.min = minBisStr;

            if (source === 'bis') {
                if (this.bisInput.value < minBisStr) {
                    this.bisInput.value = minBisStr;
                    if (this.warningText) {
                        this.warningText.innerText =
                            'Das Datum wurde auf die Mindestdauer der Vorlage korrigiert.';
                        this.warningBox.style.display = 'block';
                    }
                }
                const d = new Date(this.bisInput.value);
                d.setDate(d.getDate() - durationOffset);
                this.vonInput.value = d.toISOString().split('T')[0];
            } else {
                if (!this.vonInput.value || this.vonInput.value < todayStr)
                    this.vonInput.value = todayStr;
                const d = new Date(this.vonInput.value);
                d.setDate(d.getDate() + durationOffset);
                this.bisInput.value = d.toISOString().split('T')[0];
            }
        } else {
            this.bisInput.min = this.vonInput.value;
            if (this.bisInput.value < this.vonInput.value) {
                this.bisInput.value = this.vonInput.value;
            }
        }

        this.fetchDateInfo();
    }

    async fetchDateInfo() {
        if (!this.vonInput || !this.bisInput || !this.openingEl) return;

        const res = await api.post('api/get_date_info', {
            von: this.vonInput.value,
            bis: this.bisInput.value,
        });

        if (res.success) {
            const sanitize = (html) => window.DOMPurify?.sanitize(html) ?? html;

            this.openingEl.innerHTML = sanitize(res.openingHours);

            if (res.holidayNotice && this.holidayEl) {
                this.holidayEl.innerHTML = sanitize(res.holidayNotice);
                this.holidayEl.style.display = 'block';
            } else if (this.holidayEl) {
                this.holidayEl.style.display = 'none';
            }

            if (this.dateInfoContainer) this.dateInfoContainer.style.display = 'block';
        }
    }

    async updatePrice() {
        if (!this.tplSelect || !this.typSelect || !this.priceDisplay) return;

        const voucherCode = this.voucherInput ? this.voucherInput.value : '';

        const res = await api.post('api/get_template_price', {
            key: this.tplSelect.value,
            typ: this.typSelect.value,
            voucher: voucherCode,
        });

        if (res.success) {
            // Nutze DOMPurify wenn vorhanden, ansonsten weise HTML zu
            const sanitize = (html) => window.DOMPurify?.sanitize(html) ?? html;

            // Frontend Darstellung (mit Rabatt-HTML)
            if (this.priceDisplay.tagName !== 'SPAN' && res.discountText) {
                const rawHtml = `
                    <div class="c-price-original">Original: ${res.original.toFixed(2).replace('.', ',')} €</div>
                    <div>Gebühr: ${res.formatted}</div>
                    <div class="c-price-discount-hint">${res.discountText} angewendet</div>
                `;
                this.priceDisplay.innerHTML = sanitize(rawHtml);
            } else {
                // Admin Darstellung (Reiner Text im Span)
                this.priceDisplay.innerText =
                    res.tagName === 'SPAN' ? res.formatted : `Gebühr: ${res.formatted}`;
                if (res.discountText) this.priceDisplay.title = res.discountText;
            }

            this.priceDisplay.style.color = res.isFree ? '#059669' : 'var(--primary-color)';
            this.priceDisplay.style.background = res.isFree ? '#ecfdf5' : 'var(--primary-soft)';
        }
    }

    validateBerlinRestrictions() {
        const dateInputs = [this.vonInput, this.bisInput];
        dateInputs.forEach((input) => {
            if (!input?.value) return;
            const date = new Date(input.value);
            if (Number.isNaN(date.getTime())) return;

            if (this.isRestrictedDay(date)) {
                // Modernes Toast-Feedback anstatt blockierendem Alert!
                notifier.show(
                    `Hinweis: Der ${date.toLocaleDateString('de-DE')} ist ein Sonn- oder Feiertag. Die Einfahrt ist untersagt.`,
                    'error'
                );
            }
        });
    }

    isRestrictedDay(date) {
        const checkDate = new Date(date);
        checkDate.setHours(0, 0, 0, 0);

        if (checkDate.getDay() === 0) return true;

        const year = checkDate.getFullYear();
        const month = checkDate.getMonth() + 1;
        const day = checkDate.getDate();
        const dateStr = `${month}-${day}`;

        const fixedHolidays = ['1-1', '3-8', '5-1', '10-3', '12-25', '12-26'];
        if (fixedHolidays.includes(dateStr)) return true;

        const easter = this.getEaster(year);
        easter.setHours(0, 0, 0, 0);

        const diffDays = Math.round((checkDate - easter) / (24 * 60 * 60 * 1000));
        const relativeHolidays = [-2, 1, 39, 50]; // Karfreitag, Ostermontag, Himmelfahrt, Pfingsten

        return relativeHolidays.includes(diffDays);
    }

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

    // --- Admin-spezifische Helfer ---

    updateAdminVoucherUI() {
        if (!this.voucherDiscountType || !this.voucherValueWrap) return;
        this.voucherValueWrap.style.display =
            this.voucherDiscountType.value === 'free' ? 'none' : 'block';
    }

    toggleZweckMode() {
        if (!this.zweckSelect || !this.zweckManual || !this.toggleZweckBtn) return;

        if (this.zweckSelect.classList.contains('u-hidden')) {
            this.zweckSelect.classList.remove('u-hidden');
            this.zweckSelect.name = 'zweck';
            this.zweckManual.classList.add('u-hidden');
            this.zweckManual.name = '_unused_zweck';
            this.toggleZweckBtn.innerHTML = `<img src="${this.config.baseUrl}assets/img/icons/icon-crayon.webp" class="c-icon" alt=""> Manuell`;
        } else {
            this.zweckSelect.classList.add('u-hidden');
            this.zweckSelect.name = '_unused_zweck';
            this.zweckManual.classList.remove('u-hidden');
            this.zweckManual.name = 'zweck';
            this.toggleZweckBtn.innerHTML = `<img src="${this.config.baseUrl}assets/img/icons/icon-open-file-folder.webp" class="c-icon" alt=""> Aus Liste`;
            this.zweckManual.focus();
        }
    }
}
