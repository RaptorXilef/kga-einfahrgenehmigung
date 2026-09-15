import { api } from '../core/Api.js';
import { notifier } from '../core/Notifier.js';

/**
 * Modulares Management des Antragsformulars (Frontend & Admin).
 * Übernimmt dynamische Sichtbarkeiten, API-Preis-Berechnung, Feiertagsprüfung
 * und Datums-Synchronisation (Start-/Enddatum).
 * Nutzt strikte '.js-pf-*' BEM-Hooks für absolute Entkopplung von CSS und Form-Names.
 */
export class PermitForm {
    constructor(container) {
        this.container = container;

        // Async Request Guards (Race Condition Protection)
        this.dateFetchId = 0;
        this.priceFetchId = 0;

        // Zentraler Controller für restlose Garbage Collection
        this.abortController = new AbortController();

        // Strikte BEM JS-Hooks statt fragiler IDs oder Name-Attribute
        this.tplSelect = this.container.querySelector('.js-pf-template');
        this.typSelect = this.container.querySelector('.js-pf-typ');
        this.vonInput = this.container.querySelector('.js-pf-von');
        this.bisInput = this.container.querySelector('.js-pf-bis');
        this.parzelleInput = this.container.querySelector('.js-pf-parzelle');
        this.kennzeichenInput = this.container.querySelector('.js-pf-kennzeichen');
        this.firmaInput = this.container.querySelector('.js-pf-firma');

        this.firmaWrapper = this.container.querySelector('.js-pf-firma-wrapper');
        this.labelKennzeichen = this.container.querySelector('.js-pf-label-kennzeichen');

        // Frontend & Admin Preis-Anzeige
        this.priceDisplay = this.container.querySelector('.js-pf-price-display');
        this.voucherInput = this.container.querySelector('.js-pf-voucher-input');

        // Admin-Spezifische Felder
        this.voucherDiscountType = this.container.querySelector('.js-pf-voucher-type');
        this.voucherValueWrap = this.container.querySelector('.js-pf-voucher-value-wrap');
        this.zweckSelect = this.container.querySelector('.js-pf-zweck-select');
        this.zweckManual = this.container.querySelector('.js-pf-zweck-manual');
        this.toggleZweckBtn = this.container.querySelector('.js-pf-toggle-zweck');
        this.voucherMultiCb = this.container.querySelector('.js-pf-voucher-multi');
        this.voucherMaxWrap = this.container.querySelector('.js-pf-voucher-max-wrap');

        // Frontend-Spezifische Info-Boxen
        this.warningBox = this.container.querySelector('.js-pf-date-warning');
        this.warningText = this.container.querySelector('.js-pf-date-warning-text');
        this.dateInfoContainer = this.container.querySelector('.js-pf-dynamic-date-info');
        this.openingEl = this.container.querySelector('.js-pf-dynamic-opening');
        this.holidayEl = this.container.querySelector('.js-pf-dynamic-holiday');

        // Config sicher abrufen
        this.config = window.KGA_CONFIG || { vehicleConfig: {} };
        this.templates = window.KGA_TEMPLATES || {};

        this.init();
    }

    init() {
        const options = { signal: this.abortController.signal };

        this.voucherMultiCb?.addEventListener(
            'change',
            (e) => {
                if (this.voucherMaxWrap) {
                    this.voucherMaxWrap.hidden = !e.target.checked;
                }
            },
            options
        );

        this.typSelect?.addEventListener(
            'change',
            () => {
                this.toggleVehicleFields();
                this.updatePrice();
            },
            options
        );

        this.kennzeichenInput?.addEventListener(
            'blur',
            (e) => this.formatLicensePlate(e.target),
            options
        );

        this.parzelleInput?.addEventListener(
            'blur',
            (e) => this.formatPlotNumber(e.target),
            options
        );

        // Datums-Synchronisation (Vorgabe-Tage vs Custom)
        this.tplSelect?.addEventListener(
            'change',
            () => {
                this.handleDateChange('template');
                this.updatePrice();
            },
            options
        );

        this.vonInput?.addEventListener(
            'change',
            () => {
                this.handleDateChange('von');
                this.updatePrice();
                this.validateBerlinRestrictions();
            },
            options
        );

        this.bisInput?.addEventListener(
            'change',
            () => {
                this.handleDateChange('bis');
                this.updatePrice();
                this.validateBerlinRestrictions();
            },
            options
        );

        // Admin: Gutschein-Rabattart Toggle
        this.voucherDiscountType?.addEventListener(
            'change',
            () => this.updateAdminVoucherUI(),
            options
        );
        // Admin: Zweck Toggle (Dropdown vs Text)

        this.toggleZweckBtn?.addEventListener('click', () => this.toggleZweckMode(), options);

        // Frontend: Gutschein-Toggle
        const voucherToggle = this.container.querySelector('.js-voucher-toggle');
        const voucherWrap = this.container.querySelector('.js-voucher-container');

        if (voucherToggle && voucherWrap && !voucherToggle.dataset.bound) {
            voucherToggle.dataset.bound = 'true'; // Doppeltes Binden verhindern
            voucherToggle.addEventListener(
                'click',
                () => voucherWrap.classList.toggle('is-open'),
                options
            );
        }

        // Initiale Aufrufe
        this.enforceMinDates();
        this.toggleVehicleFields();
        this.handleDateChange('init');
        this.updatePrice();
        this.updateAdminVoucherUI();
    }

    /**
     * Wandelt ein lokales Date-Objekt sicher in einen YYYY-MM-DD String um,
     * OHNE auf UTC zurückzugreifen (Verhindert Timezone Off-by-One Bugs).
     */
    getLocalIsoDate(dateObj = new Date()) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    enforceMinDates() {
        if (!this.vonInput) return;
        this.vonInput.min = this.getLocalIsoDate();
    }

    toggleVehicleFields() {
        if (!this.typSelect) return;

        const type = this.typSelect.value;
        const cfg = this.config.vehicleConfig[type] || { show_company: false };
        const isCompanyRequired = cfg.show_company;

        if (this.firmaWrapper) {
            this.firmaWrapper.hidden = !isCompanyRequired;
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

        const todayStr = this.getLocalIsoDate();
        if (source === 'von' && this.vonInput.value < todayStr) {
            this.vonInput.value = todayStr;
        }

        const isCustom = config.days === 'custom';
        const duration = isCustom ? 0 : parseInt(config.days, 10);
        const durationOffset = Math.max(0, duration - 1);

        if (this.warningBox) this.warningBox.hidden = true;

        if (!isCustom) {
            // Min-Datum für das Bis-Feld basierend auf dem aktuellen Tag berechnen
            const minBisDate = new Date();
            minBisDate.setDate(minBisDate.getDate() + durationOffset);
            const minBisStr = this.getLocalIsoDate(minBisDate);
            this.bisInput.min = minBisStr;

            if (source === 'bis') {
                if (this.bisInput.value < minBisStr) {
                    this.bisInput.value = minBisStr;
                    if (this.warningText) {
                        this.warningText.innerText =
                            'Das Datum wurde auf die Mindestdauer der Vorlage korrigiert.';
                        this.warningBox.hidden = false;
                    }
                }
                // Strikte lokale Datums-Berechnung anhand der String-Bestandteile
                const [y, m, d] = this.bisInput.value.split('-').map(Number);
                const dateObj = new Date(y, m - 1, d);
                dateObj.setDate(dateObj.getDate() - durationOffset);
                this.vonInput.value = this.getLocalIsoDate(dateObj);
            } else {
                if (!this.vonInput.value || this.vonInput.value < todayStr) {
                    this.vonInput.value = todayStr;
                }
                // Strikte lokale Datums-Berechnung
                const [y, m, d] = this.vonInput.value.split('-').map(Number);
                const dateObj = new Date(y, m - 1, d);
                dateObj.setDate(dateObj.getDate() + durationOffset);
                this.bisInput.value = this.getLocalIsoDate(dateObj);
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

        // Race Condition Guard. Zähler erhöhen und aktuellen Wert sichern
        const currentFetchId = ++this.dateFetchId;

        const res = await api.post('api/get_date_info', {
            von: this.vonInput.value,
            bis: this.bisInput.value,
        });

        // Wenn der Request veraltet ist (weil in der Zwischenzeit ein neuer gestartet wurde), abbruch!
        if (currentFetchId !== this.dateFetchId) return;

        if (res.success) {
            // Sicheres DOM-Building ohne DOMPurify/innerHTML
            this.openingEl.textContent = '';

            const title = document.createElement('strong');
            title.className = 'u-font-bold u-display-block';
            title.textContent = '⏰ Erlaubte Einfahrzeiten (Ruhezeiten beachten):';
            this.openingEl.appendChild(title);

            const subtitle = document.createElement('span');
            subtitle.className = 'u-display-block u-margin-block-end-xs';
            subtitle.textContent =
                'Das Befahren der Anlage ist ausschließlich zu folgenden Zeiten gestattet:';
            this.openingEl.appendChild(subtitle);

            const timesContainer = document.createElement('div');
            timesContainer.className = 'u-color-primary u-font-bold';

            if (res.openingData && res.openingData.length > 0) {
                const isMulti = res.openingData.length > 1;
                res.openingData.forEach((block) => {
                    const blockDiv = document.createElement('div');

                    if (isMulti) {
                        blockDiv.className = 'u-margin-block-end-xs';
                        const labelSpan = document.createElement('span');
                        labelSpan.className = 'u-color-primary';
                        labelSpan.textContent = `${block.from} - ${block.to}: `;
                        blockDiv.appendChild(labelSpan);
                        blockDiv.appendChild(document.createElement('br'));
                    }

                    block.hours_text.forEach((text, index) => {
                        const parts = text.split(':');
                        const span = document.createElement('span');
                        span.className = 'u-text-nowrap';

                        if (parts.length === 2) {
                            const strong = document.createElement('strong');
                            strong.className = 'u-font-bold';
                            strong.textContent = `${parts[0]}:`;
                            span.appendChild(strong);
                            span.appendChild(document.createTextNode(parts[1]));
                        } else {
                            span.textContent = text;
                        }

                        blockDiv.appendChild(span);
                        if (index < block.hours_text.length - 1) {
                            const separator = document.createElement('span');
                            separator.innerHTML = ' &nbsp;|&nbsp; '; // Safe HTML entity insertion
                            blockDiv.appendChild(separator);
                        }
                    });
                    timesContainer.appendChild(blockDiv);
                });
            }
            this.openingEl.appendChild(timesContainer);

            if (res.holidays && res.holidays.length > 0) {
                this.holidayEl.innerHTML = `🚫 An folgenden Feier- und Ruhetagen ist die Einfahrt untersagt:<br>${res.holidays.join(', ')}.`;
                this.holidayEl.hidden = false;
            } else if (this.holidayEl) {
                this.holidayEl.hidden = true;
            }

            if (this.dateInfoContainer) this.dateInfoContainer.hidden = false;
        } else {
            this.openingEl.textContent =
                'Zeitraum konnte aufgrund eines Netzwerkfehlers nicht geprüft werden.';
            this.openingEl.className = 'u-text-muted';
            if (this.holidayEl) this.holidayEl.hidden = true;
            notifier.show(
                'Netzwerkfehler: Einfahrtszeiten konnten nicht abgefragt werden.',
                'error'
            );
        }
    }

    async updatePrice() {
        if (!this.tplSelect || !this.typSelect || !this.priceDisplay) return;

        const voucherCode = this.voucherInput ? this.voucherInput.value : '';
        const currentFetchId = ++this.priceFetchId;

        const res = await api.post('api/get_template_price', {
            key: this.tplSelect.value,
            typ: this.typSelect.value,
            voucher: voucherCode,
        });

        // Stale Request ignorieren
        if (currentFetchId !== this.priceFetchId) return;
        this.priceDisplay.classList.remove('c-price-display--free', 'c-price-display--error');

        if (res.success) {
            if (this.priceDisplay.tagName !== 'SPAN' && res.discountText) {
                // Sicheres DOM-Building ohne DOMPurify
                this.priceDisplay.textContent = '';

                const orig = document.createElement('div');
                orig.className = 'c-price-original';
                orig.textContent = `Original: ${res.original.toFixed(2).replace('.', ',')} €`;

                const fee = document.createElement('div');
                fee.textContent = `Gebühr: ${res.formatted}`;

                const hint = document.createElement('div');
                hint.className = 'c-price-discount-hint';
                hint.textContent = `${res.discountText} angewendet`;

                this.priceDisplay.append(orig, fee, hint);
            } else {
                // Admin Darstellung (Reiner Text im Span)
                this.priceDisplay.innerText =
                    this.priceDisplay.tagName === 'SPAN'
                        ? res.formatted
                        : `Gebühr: ${res.formatted}`;
                if (res.discountText) this.priceDisplay.title = res.discountText;
            }

            if (res.isFree) this.priceDisplay.classList.add('c-price-display--free');
        } else {
            // Silent-Failure beheben und UI Error-State setzen
            this.priceDisplay.innerText =
                this.priceDisplay.tagName === 'SPAN'
                    ? 'Fehler'
                    : 'Gebühr: Berechnung fehlgeschlagen';
            this.priceDisplay.classList.add('c-price-display--error');
            notifier.show(
                'Preis konnte aufgrund eines Netzwerkfehlers nicht berechnet werden.',
                'error'
            );
        }
    }

    validateBerlinRestrictions() {
        const dateInputs = [this.vonInput, this.bisInput];
        dateInputs.forEach((input) => {
            if (!input?.value) return;

            // Verhindert Timezone-Shift (Off-by-One Day) durch striktes, lokales Parsing der String-Bestandteile
            const [y, m, d] = input.value.split('-').map(Number);
            const date = new Date(y, m - 1, d);

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
        this.voucherValueWrap.hidden = this.voucherDiscountType.value === 'free';
    }

    toggleZweckMode() {
        if (!this.zweckSelect || !this.zweckManual || !this.toggleZweckBtn) return;

        if (this.zweckSelect.hidden) {
            this.zweckSelect.hidden = false;
            this.zweckSelect.name = 'zweck';
            this.zweckManual.hidden = true;
            this.zweckManual.name = '_unused_zweck';
            this.toggleZweckBtn.innerHTML = `<img src="${this.config.baseUrl}assets/img/icons/icon-crayon.webp" class="c-icon" alt=""> Manuell`;
        } else {
            this.zweckSelect.hidden = true;
            this.zweckSelect.name = '_unused_zweck';
            this.zweckManual.hidden = false;
            this.zweckManual.name = 'zweck';
            this.toggleZweckBtn.innerHTML = `<img src="${this.config.baseUrl}assets/img/icons/icon-open-file-folder.webp" class="c-icon" alt=""> Aus Liste`;
            this.zweckManual.focus();
        }
    }

    /**
     * Wird vom Bootstrapper aufgerufen, wenn das Element aus dem DOM entfernt wird.
     */
    destroy() {
        this.abortController.abort();
    }
}
