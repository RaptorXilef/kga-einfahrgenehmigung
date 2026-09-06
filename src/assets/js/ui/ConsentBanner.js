/**
 * Modulares Consent-Banner für DSGVO-konforme Google Analytics Integration.
 */
export class ConsentBanner {
    constructor(container) {
        this.container = container;

        const dataScript = this.container.querySelector('#consent-config');
        this.config = dataScript ? JSON.parse(dataScript.textContent || '{}') : {};

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
        if (!this.getCookie()) {
            this.container.style.display = 'block';
        } else {
            this.applyConsent(JSON.parse(this.getCookie()));
        }

        this.btnAcceptAll?.addEventListener('click', () => this.acceptAll());
        this.btnAcceptEssential?.addEventListener('click', () => this.acceptEssential());
        this.btnSaveSelection?.addEventListener('click', () => this.saveSelection());
        this.btnToggleDetails?.addEventListener('click', () => this.toggleDetails());
    }

    setCookie(value) {
        const d = new Date();
        d.setTime(d.getTime() + 365 * 24 * 60 * 60 * 1000);
        // LINTER-FIX: document.cookie muss zum Setzen von Cookies genutzt werden, die Deaktivierung des Linters ist hier beabsichtigt.
        // biome-ignore lint/suspicious/noDocumentCookie: Necessary for vanilla JS cookie handling
        document.cookie = `${this.cookieName}=${JSON.stringify(value)};expires=${d.toUTCString()};path=/;SameSite=Lax`;
        this.container.style.display = 'none';
        this.applyConsent(value);
    }

    getCookie() {
        // LINTER-FIX: Template Literal
        const name = `${this.cookieName}=`;
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            // LINTER-FIX: Strict Equality
            while (c.charAt(0) === ' ') c = c.substring(1);
            if (c.indexOf(name) === 0) return c.substring(name.length, c.length);
        }
        return '';
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
        if (this.detailsContainer.style.display === 'none') {
            this.detailsContainer.style.display = 'block';
            this.btnSaveSelection.style.display = 'block';
            this.btnToggleDetails.innerText = this.config.texts.hide_details;
        } else {
            this.detailsContainer.style.display = 'none';
            this.btnSaveSelection.style.display = 'none';
            this.btnToggleDetails.innerText = this.config.texts.show_details;
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
        // LINTER-FIX: Google Analytics Tag MUSS zwingend das klassische `arguments` Array pushen, um zu funktionieren.
        function gtag() {
            // biome-ignore lint/style/noArguments: Google Analytics requires the exact arguments object
            window.dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', this.gaId, { anonymize_ip: true });
    }
}
