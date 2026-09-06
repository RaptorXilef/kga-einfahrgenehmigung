import { api } from '../core/Api.js';

/**
 * Steuert die Auslösung von Überweisungen und integriert die externen PayPal-Buttons.
 */
export class CheckoutPayment {
    constructor(container) {
        this.container = container;
        this.wireBtns = this.container.querySelectorAll('.js-finalize-wire');
        this.paypalContainer = this.container.querySelector('#paypal-button-container');

        const dataScript = this.container.querySelector('#payment-data');
        this.paymentData = dataScript ? JSON.parse(dataScript.textContent || '{}') : {};

        this.init();
    }

    init() {
        this.wireBtns.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.finalizeWire();
            });
        });

        if (
            this.paypalContainer &&
            typeof paypal !== 'undefined' &&
            this.paymentData.isPayPalEnabled
        ) {
            this.initPayPal();
        }
    }

    async finalizeWire() {
        const params = new URLSearchParams();
        params.append('token', this.paymentData.token);
        params.append('csrf_token', this.paymentData.csrfToken);

        const data = await api.post('api/finalize_wire', params);
        if (data.success) {
            window.location.href = `success?code=${data.code}&method=wire`;
        } else {
            alert(`Fehler beim Abschluss: ${data.error}`);
        }
    }

    initPayPal() {
        paypal
            .Buttons({
                createOrder: async () => {
                    const params = new URLSearchParams();
                    params.append('token', this.paymentData.token);
                    params.append('csrf_token', this.paymentData.csrfToken);

                    const orderData = await api.post('api/create_order_for_token', params);
                    if (orderData.success) {
                        return orderData.id;
                    } else {
                        alert(`PayPal-Sitzungsfehler: ${orderData.error}`);
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
                        alert(`Zahlungsverifizierung fehlgeschlagen: ${details.error}`);
                    }
                },
                onError: (err) => {
                    console.error('PayPal-Fehlerkanal:', err);
                    alert('Ein kritischer Fehler ist bei der Zahlungsabwicklung aufgetreten.');
                },
            })
            .render('#paypal-button-container');
    }
}
