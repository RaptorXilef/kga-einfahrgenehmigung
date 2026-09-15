import { api } from '../core/Api.js';
import { notifier } from '../core/Notifier.js';

/**
 * Steuert die Auslösung von Überweisungen und integriert die externen PayPal-Buttons.
 */
export class CheckoutPayment {
    constructor(container) {
        this.container = container;
        this.wireBtns = this.container.querySelectorAll('.js-finalize-wire');

        // FIX: Strikte JS Hooks anstatt harter IDs
        this.paypalContainer = this.container.querySelector('.js-paypal-button-container');
        const dataScript = this.container.querySelector('.js-payment-data');

        // FIX: Zentraler Controller für GC
        this.abortController = new AbortController();

        try {
            this.paymentData = dataScript ? JSON.parse(dataScript.textContent || '{}') : {};
        } catch {
            this.paymentData = {};
        }

        this.init();
    }

    init() {
        const options = { signal: this.abortController.signal };

        this.wireBtns.forEach((btn) => {
            btn.addEventListener(
                'click',
                async (e) => {
                    e.preventDefault();
                    if (btn.disabled) return;
                    btn.disabled = true;
                    await this.finalizeWire(btn);
                },
                options
            );
        });

        if (
            this.paypalContainer &&
            typeof paypal !== 'undefined' &&
            this.paymentData.isPayPalEnabled
        ) {
            this.initPayPal();
        }
    }

    async finalizeWire(btn) {
        const params = new URLSearchParams();
        params.append('token', this.paymentData.token);
        params.append('csrf_token', this.paymentData.csrfToken);

        // Abgesichert via API Singleton Signal
        const data = await api.post('api/finalize_wire', params);

        if (data.success) {
            window.location.href = `success?code=${data.code}&method=wire`;
        } else {
            notifier.show(`Fehler beim Abschluss: ${data.error}`, 'error');
            if (btn) btn.disabled = false;
        }
    }

    initPayPal() {
        paypal
            .Buttons({
                createOrder: async () => {
                    const params = new URLSearchParams();
                    params.append('token', this.paymentData.token);
                    params.append('csrf_token', this.paymentData.csrfToken);

                    const orderData = await api.post('api/create_order', params);
                    if (orderData.success) {
                        return orderData.id;
                    } else {
                        notifier.show(`PayPal-Sitzungsfehler: ${orderData.error}`, 'error');
                        throw new Error(orderData.error);
                    }
                },
                onApprove: async (data) => {
                    const details = await api.post('api/capture', {
                        orderID: data.orderID,
                        token: this.paymentData.token,
                    });

                    if (details.success) {
                        window.location.href = `success?code=${this.paymentData.token}&method=paypal`;
                    } else {
                        notifier.show(
                            `Zahlungsverifizierung fehlgeschlagen: ${details.error}`,
                            'error'
                        );
                    }
                },
                onError: (err) => {
                    console.error('PayPal-Fehlerkanal:', err);
                    notifier.show(
                        'Ein kritischer Fehler ist bei der Zahlungsabwicklung aufgetreten.',
                        'error'
                    );
                },
            })
            // FIX: Übergabe der echten DOM-Referenz statt eines ID-Strings!
            .render(this.paypalContainer);
    }

    destroy() {
        this.abortController.abort();
    }
}
