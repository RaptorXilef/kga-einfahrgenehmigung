/**
 * Modulares Consent-Banner für DSGVO-konforme Google Analytics Integration.
 * Inklusive A11y DOM-Steuerung.
 */
export class ConsentBanner {
    constructor(container) {
        this.container = container;

        // Defensive Error Boundary für JSON Konfiguration
        try {
            const dataScript = this.container.querySelector('#consent-config');
            this.config = dataScript ? JSON.parse(dataScript.textContent || '{}') : {};
        } catch {
            this.config = {};
        }

        this.cookieName = 'kga_cookie_consent';
        this.gaId = this.config.gaId || '';

        this.btnAcceptAll = this.container.querySelector('.js-accept-all');
        this.btnAcceptEssential = this.container.querySelector('.js-accept-essential');
        this.btnToggleDetails = this.container.querySelector('.js-toggle-details');
        this.btnSaveSelection = this.container.querySelector('.js-save-selection');
        this.detailsContainer = this.container.querySelector('.js-consent-details');
        this.chkAnalytics = this.container.querySelector('#consent-chk-analytics');

        this.init();
    }

    init() {
        // Schutz gegen manipulierte (SyntaxError) oder blockierte (SecurityError) Cookies
        try {
            const cookieVal = this.getCookie();
            if (!cookieVal) {
                this.container.hidden = false;
                this.container.classList.remove('u-hidden'); // Rückwärtskompatibilität
                // A11Y Dialog-Semantik setzen
                this.container.setAttribute('role', 'dialog');
                this.container.setAttribute('aria-modal', 'false'); // Non-blocking
                this.container.setAttribute('aria-labelledby', 'consent-banner-title');
            } else {
                this.applyConsent(JSON.parse(cookieVal));
            }
        } catch {
            this.container.hidden = false;
            this.container.classList.remove('u-hidden');
        }

        // Event-Prevention hinzufügen, um unbeabsichtigte Form-Submits zu blockieren
        this.btnAcceptAll?.addEventListener('click', (e) => {
            e.preventDefault();
            this.acceptAll();
        });
        this.btnAcceptEssential?.addEventListener('click', (e) => {
            e.preventDefault();
            this.acceptEssential();
        });
        this.btnSaveSelection?.addEventListener('click', (e) => {
            e.preventDefault();
            this.saveSelection();
        });
        this.btnToggleDetails?.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleDetails();
        });
    }

    setCookie(value) {
        const d = new Date();
        d.setTime(d.getTime() + 365 * 24 * 60 * 60 * 1000);

        // Secure Flag dynamisch anfügen, wenn HTTPS aktiv ist (Man-in-the-Middle Schutz)
        const secureFlag = window.isSecureContext ? ';Secure' : '';

        try {
            // biome-ignore lint/suspicious/noDocumentCookie: Necessary for vanilla JS cookie handling
            document.cookie = `${this.cookieName}=${JSON.stringify(value)};expires=${d.toUTCString()};path=/;SameSite=Lax${secureFlag}`;
        } catch {
            console.warn('[ConsentBanner] Speichern von Cookies blockiert.');
        }
        this.container.hidden = true;
        this.applyConsent(value);
    }

    getCookie() {
        try {
            const name = `${this.cookieName}=`;
            const decodedCookie = decodeURIComponent(document.cookie);
            const ca = decodedCookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1);
                if (c.indexOf(name) === 0) return c.substring(name.length, c.length);
            }
            return '';
        } catch {
            return '';
        }
    }

    acceptAll() {
        this.setCookie({ essential: true, analytics: true });
    }
    acceptEssential() {
        this.setCookie({ essential: true, analytics: false });
    }

    saveSelection() {
        this.setCookie({ essential: true, analytics: this.chkAnalytics?.checked || false });
    }

    toggleDetails() {
        const isCurrentlyHidden =
            this.detailsContainer.hidden || this.detailsContainer.classList.contains('u-hidden');
        if (isCurrentlyHidden) {
            this.detailsContainer.hidden = false;
            this.detailsContainer.classList.remove('u-hidden');
            this.btnSaveSelection.hidden = false;
            this.btnSaveSelection.classList.remove('u-hidden');
            this.btnToggleDetails.innerText = this.config.texts.hide_details;
            this.btnToggleDetails.setAttribute('aria-expanded', 'true');
        } else {
            this.detailsContainer.hidden = true;
            this.btnSaveSelection.hidden = true;
            this.btnToggleDetails.innerText = this.config.texts.show_details;
            this.btnToggleDetails.setAttribute('aria-expanded', 'false');
        }
    }

    applyConsent(consentObj) {
        if (consentObj.analytics && this.gaId !== '') {
            this.loadGoogleAnalytics();
        }
    }

    loadGoogleAnalytics() {
        if (document.getElementById('ga-script')) return;

        const script = document.createElement('script');
        script.id = 'ga-script';
        script.src = `https://www.googletagmanager.com/gtag/js?id=${this.gaId}`;
        script.async = true;
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];

        // Die gtag Funktion MUSS zwingend im globalen (window) Scope liegen!
        window.gtag = function gtag() {
            // biome-ignore lint/complexity/noArguments: Google Analytics requires the exact arguments object
            window.dataLayer.push(arguments);
        };

        window.gtag('js', new Date());
        window.gtag('config', this.gaId, { anonymize_ip: true });
    }
}
